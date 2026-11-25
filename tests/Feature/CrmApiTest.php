<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Desk;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login|127.0.0.1');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_public_registration_is_unavailable_and_pending_accounts_cannot_log_in(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Coworker', 'email' => 'new@example.test', 'password' => 'LongPassword123', 'password_confirmation' => 'LongPassword123'])->assertNotFound();
        $this->assertDatabaseCount('users', 0);
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/v1/users', [])->assertUnauthorized();
    }

    public function test_login_me_logout_use_real_revocable_bearer_tokens(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonPath('token_type', 'Bearer');
        $token = $response->json('access_token');
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.id', $user->uuid)->assertJsonMissingPath('data.password');
        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$token])->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_and_unauthenticated_users_cannot_access_directories(): void
    {
        $this->getJson('/api/v1/desks')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/desks')->assertForbidden();
    }

    public function test_non_admin_cannot_write_and_only_sees_assigned_clients(): void
    {
        $user = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $mine = Client::factory()->for($user, 'assignedUser')->create();
        $other = Client::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/clients')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id)->assertJsonPath('data.0.assigned_user_id', $user->uuid);
        $this->getJson('/api/v1/clients/'.$other->id)->assertNotFound();
        $this->postJson('/api/v1/brands', [])->assertForbidden();
        $this->patchJson('/api/v1/users/'.$user->uuid, ['role' => 'admin'])->assertForbidden();
    }

    public function test_admin_can_create_and_update_coworkers_using_public_uuid(): void
    {
        $this->admin();
        Desk::factory()->create();
        $response = $this->postJson('/api/v1/users', ['name' => 'Agent', 'email' => 'agent@example.test', 'password' => 'LongPassword123', 'password_confirmation' => 'LongPassword123', 'role' => 'agent', 'status' => 'active'])->assertCreated();
        $response->assertJsonPath('data.desks', []);
        $this->assertDatabaseCount('desk_user', 0);
        $id = $response->json('data.id');
        $this->getJson('/api/v1/users/'.$id)->assertOk()->assertJsonPath('data.email', 'agent@example.test')->assertJsonMissingPath('data.password');
        $this->patchJson('/api/v1/users/'.$id, ['email' => 'agent@example.test', 'status' => 'disabled'])->assertOk()->assertJsonPath('data.status', 'disabled');
        $this->getJson('/api/v1/users/999999')->assertNotFound();
    }

    public function test_admin_cannot_remove_own_access(): void
    {
        $user = $this->admin();
        $this->patchJson('/api/v1/users/'.$user->uuid, ['status' => 'disabled'])->assertUnprocessable();
    }

    public function test_desks_have_no_default_field_in_write_or_read_contracts(): void
    {
        $this->admin();
        $response = $this->postJson('/api/v1/desks', ['name' => 'Manual assignment desk', 'is_default' => true])
            ->assertCreated()->assertJsonMissingPath('data.is_default');
        $id = $response->json('data.id');
        $this->patchJson('/api/v1/desks/'.$id, ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonMissingPath('data.is_default');
        $this->getJson('/api/v1/desks/'.$id)->assertOk()->assertJsonMissingPath('data.is_default');
        $this->getJson('/api/v1/desks')->assertOk()->assertJsonMissingPath('data.0.is_default');
        $this->assertArrayNotHasKey('is_default', Desk::findOrFail($id)->getAttributes());
    }

    public function test_desk_membership_replaces_users_and_validates_uuid_pairs(): void
    {
        $this->admin();
        $desk = Desk::factory()->create();
        $user = User::factory()->create();
        $this->putJson('/api/v1/desks/'.$desk->id.'/users', ['user_ids' => [$user->id]])->assertUnprocessable();
        $this->putJson('/api/v1/desks/'.$desk->id.'/users', ['user_ids' => [$user->uuid, $user->uuid]])->assertUnprocessable();
        $this->putJson('/api/v1/desks/'.$desk->id.'/users', ['user_ids' => [$user->uuid]])->assertOk()->assertJsonCount(1, 'data.users')->assertJsonPath('data.users.0.id', $user->uuid);
        $this->putJson('/api/v1/desks/'.$desk->id.'/users', ['user_ids' => []])->assertOk()->assertJsonCount(0, 'data.users');
        $this->assertModelExists($user);
    }

    public function test_brand_domain_validation_and_referenced_delete_conflict(): void
    {
        $this->admin();
        $this->postJson('/api/v1/brands', ['name' => 'Website', 'domain' => 'https://example.test/path'])->assertUnprocessable()->assertJsonValidationErrors('domain');
        $response = $this->postJson('/api/v1/brands', ['name' => 'Website', 'domain' => 'EXAMPLE.TEST', 'transactional_email_templates' => ['welcome' => 'Hello']])->assertCreated()->assertJsonPath('data.domain', 'example.test');
        $id = $response->json('data.id');
        $this->patchJson('/api/v1/brands/'.$id, ['domain' => 'example.test'])->assertOk();
        $this->postJson('/api/v1/brands', ['name' => 'Duplicate', 'domain' => 'example.test'])->assertUnprocessable();
        Client::factory()->create(['brand_id' => $id]);
        $this->deleteJson('/api/v1/brands/'.$id)->assertConflict();
        $this->deleteJson('/api/v1/clients/'.Client::first()->id)->assertNoContent();
        $this->deleteJson('/api/v1/brands/'.$id)->assertNoContent();
    }

    public function test_client_validation_and_assignment_uuid_mapping(): void
    {
        $this->admin();
        $brand = Brand::factory()->create();
        $user = User::factory()->create();
        $this->postJson('/api/v1/clients', ['first_name' => 'Test', 'last_name' => 'Client', 'email' => 'bad', 'brand_id' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'brand_id']);
        $response = $this->postJson('/api/v1/clients', ['first_name' => 'Test', 'last_name' => 'Client', 'email' => 'client@example.test', 'brand_id' => $brand->id, 'assigned_user_id' => $user->uuid])->assertCreated()->assertJsonPath('data.assigned_user_id', $user->uuid);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('clients', ['id' => $id, 'assigned_user_id' => $user->id]);
        $this->patchJson('/api/v1/clients/'.$id, ['assigned_user_id' => null])->assertOk()->assertJsonPath('data.assigned_user_id', null);
    }

    public function test_pagination_validation_and_lookup_routes(): void
    {
        $this->admin();
        $this->getJson('/api/v1/users?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        foreach (['users', 'desks', 'brands', 'clients', 'countries', 'departments', 'customer-statuses', 'affiliates'] as $path) {
            $this->getJson('/api/v1/'.$path.'?per_page=1')->assertOk()->assertJsonStructure(['data']);
        }
    }

    public function test_trusted_command_bootstraps_an_admin(): void
    {
        $user = User::factory()->create();
        $this->artisan('crm:approve-user', ['email' => $user->email, '--admin' => true])->assertSuccessful();
        $this->assertSame('admin', $user->fresh()->role);
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_unknown_login_and_multibyte_passwords_return_validation_errors(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $password = 'Abc123'.str_repeat('é', 34);
        $this->admin();
        $this->postJson('/api/v1/users', [
            'name' => 'Test', 'email' => 'test@example.test',
            'password' => $password, 'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_disabled_and_expired_tokens_cannot_read_protected_records(): void
    {
        $user = User::factory()->create(['status' => 'disabled']);
        $token = $user->createToken('disabled')->plainTextToken;
        $this->getJson('/api/v1/desks', ['Authorization' => 'Bearer '.$token])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $user->status = 'active';
        $user->save();
        $expired = $user->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $this->getJson('/api/v1/desks', ['Authorization' => 'Bearer '.$expired])->assertUnauthorized();
    }

    public function test_changing_account_status_revokes_all_device_tokens(): void
    {
        $this->admin();
        $user = User::factory()->create(['status' => 'active']);
        $user->createToken('device-one');
        $user->createToken('device-two');
        $this->patchJson('/api/v1/users/'.$user->uuid, ['status' => 'disabled'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])
                ->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_openapi_is_served_and_documents_all_versioned_operations(): void
    {
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
        Scramble::throwOnError();
        $connection = DB::getDefaultConnection();
        $spec = $this->getJson('/api/openapi.json')->assertOk()->json();
        $this->assertSame($connection, DB::getDefaultConnection());
        $this->assertStringStartsWith('3.1.', $spec['openapi']);
        $this->assertArrayHasKey('securitySchemes', $spec['components']);
        $this->assertSame([], $spec['paths']['/auth/login']['post']['security']);
        $this->assertNotEmpty($spec['paths']['/clients']['get']['security'] ?? $spec['security']);
        $this->assertArrayNotHasKey('patch', $spec['paths']['/desks/{desk}/users']);
        $this->assertArrayNotHasKey('/auth/register', $spec['paths']);
        foreach (['/users', '/desks', '/brands', '/clients'] as $path) {
            $body = $spec['paths'][$path]['post']['requestBody']['content']['application/json']['schema'];
            if (isset($body['$ref'])) {
                $body = $spec['components']['schemas'][basename($body['$ref'])];
            }
            $this->assertNotEmpty($body['properties'], $path.' request fields');
            $this->assertNotEmpty($body['required'], $path.' required fields');
            if ($path === '/desks') {
                $this->assertArrayNotHasKey('is_default', $body['properties']);
            }
        }
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $path = '/'.substr($route->uri(), 7);
            $path = str_replace('{user:uuid}', '{user}', $path);
            if ($path === '/{lookup}') {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $this->assertArrayHasKey(strtolower($method), $spec['paths'][$path], $method.' '.$path);
            }
        }
    }

    public function test_swagger_ui_and_assets_are_served_locally(): void
    {
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
        $this->get('/swagger')->assertOk()->assertSee('SwaggerUIBundle')->assertSee('openapi.json');
        $this->get('/swagger/assets/swagger-ui.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
        $this->get('/swagger/assets/swagger-ui-bundle.js')->assertOk()->assertHeader('Content-Type', 'application/javascript');
        $this->get('/swagger/assets/composer.json')->assertNotFound();
    }

    public function test_documentation_is_restricted_outside_local_environment(): void
    {
        $this->get('/swagger')->assertForbidden();
        $this->getJson('/api/openapi.json')->assertForbidden();
        $this->get('/swagger/assets/swagger-ui-bundle.js')->assertForbidden();
    }
}
