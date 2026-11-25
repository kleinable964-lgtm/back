<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Dedoc\Scramble\Scramble;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_installs_exactly_the_three_supported_roles(): void
    {
        $this->assertDatabaseCount('roles', 3);
        $this->assertSame(['admin' => 'Admin', 'agent' => 'Agent', 'manager' => 'Manager'], Role::orderBy('name')->pluck('label', 'name')->all());
        $this->assertEqualsCanonicalizing(array_keys(Role::DEFINITIONS), Role::pluck('name')->all());
    }

    public function test_role_seeding_is_repeatable_and_restores_missing_reference_records(): void
    {
        Role::query()->delete();
        Role::factory()->create(['name' => 'agent', 'label' => 'Agent']);
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->assertDatabaseCount('roles', 3);
        $this->assertSame(Role::DEFINITIONS['manager'], Role::findOrFail('manager')->label);
    }

    public function test_only_active_authenticated_coworkers_can_read_roles_and_there_are_no_role_write_endpoints(): void
    {
        $this->getJson('/api/v1/roles')->assertUnauthorized();
        foreach (['admin', 'manager', 'agent'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'status' => 'active']));
            $this->getJson('/api/v1/roles')->assertOk()->assertExactJson(['data' => [
                ['name' => 'admin', 'label' => 'Admin'],
                ['name' => 'agent', 'label' => 'Agent'],
                ['name' => 'manager', 'label' => 'Manager'],
            ]]);
        }
        $this->postJson('/api/v1/roles', ['name' => 'other'])->assertStatus(405);
        $this->patchJson('/api/v1/roles/agent', ['label' => 'Other'])->assertNotFound();
        $this->deleteJson('/api/v1/roles/agent')->assertNotFound();
        foreach (['pending', 'disabled'] as $status) {
            Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => $status]));
            $this->getJson('/api/v1/roles')->assertForbidden();
        }
    }

    public function test_users_keep_string_roles_and_can_resolve_their_database_role_definition(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $this->assertSame('manager', $user->fresh()->role);
        $this->assertSame('Manager', $user->roleDefinition->label);
        $this->assertTrue(Role::findOrFail('manager')->users->sole()->is($user));
        $this->assertTrue($user->hasRole('admin', 'manager'));
        $this->assertFalse($user->hasRole('admin'));
        $unassigned = User::factory()->create();
        $this->assertNull($unassigned->roleDefinition);
        $this->assertFalse($unassigned->hasRole('agent', 'manager', 'admin'));
    }

    public function test_admin_assignments_accept_only_supported_database_roles_and_keep_the_existing_api_shape(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $target = User::factory()->create(['role' => 'agent']);
        foreach (['admin', 'manager', 'agent', null] as $role) {
            $this->patchJson('/api/v1/users/'.$target->uuid, ['role' => $role])
                ->assertOk()->assertJsonPath('data.role', $role)->assertJsonMissingPath('data.role_id');
            $this->assertSame($role, $target->fresh()->role);
        }
        foreach (['root', 'super-admin', 'ADMIN', 3, ['agent']] as $role) {
            $this->patchJson('/api/v1/users/'.$target->uuid, ['role' => $role])
                ->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->assertNull($target->fresh()->role);
    }

    public function test_database_rejects_unknown_user_roles_even_when_bypassing_request_validation(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['role' => 'unknown']);
    }

    public function test_an_assigned_role_cannot_be_deleted_from_the_database(): void
    {
        User::factory()->create(['role' => 'agent']);
        $this->expectException(QueryException::class);
        Role::findOrFail('agent')->delete();
    }

    public function test_existing_manage_crm_gate_still_requires_an_active_admin(): void
    {
        foreach ([['admin', 'active', true], ['admin', 'disabled', false], ['manager', 'active', false], ['agent', 'active', false]] as [$role, $status, $allowed]) {
            $user = User::factory()->create(['role' => $role, 'status' => $status, 'permission_group' => 'admin']);
            $this->assertSame($allowed, Gate::forUser($user)->allows('manage-crm'));
        }
    }

    public function test_upgrade_refuses_unknown_legacy_roles_without_reassigning_the_user(): void
    {
        $migration = require database_path('migrations/2026_11_23_102151_create_roles_table_and_link_users.php');
        $migration->down();
        $user = User::factory()->create(['role' => 'legacy-role']);
        try {
            $migration->up();
            $this->fail('Unsupported legacy roles must be resolved before changing the schema.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unsupported roles', $exception->getMessage());
        }
        $this->assertSame('legacy-role', $user->fresh()->role);
        $this->assertFalse(Schema::hasTable('roles'));
        DB::table('users')->where('id', $user->id)->update(['role' => 'agent']);
        $migration->up();
        $this->assertSame('agent', $user->fresh()->role);
    }

    public function test_swagger_documents_the_roles_endpoint_and_supported_assignment_values(): void
    {
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
        Scramble::throwOnError();
        $spec = $this->getJson('/api/openapi.json')->assertOk()->json();
        $this->assertArrayHasKey('get', $spec['paths']['/roles']);
        $this->assertArrayNotHasKey('post', $spec['paths']['/roles']);
        $this->assertEqualsCanonicalizing(['name', 'label'], array_keys($spec['components']['schemas']['RoleResource']['properties']));
        $body = $spec['paths']['/users']['post']['requestBody']['content']['application/json']['schema'];
        if (isset($body['$ref'])) {
            $body = $spec['components']['schemas'][basename($body['$ref'])];
        }
        foreach (array_keys(Role::DEFINITIONS) as $role) {
            $this->assertContains($role, $body['properties']['role']['enum']);
        }

    }
}
