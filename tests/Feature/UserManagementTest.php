<?php

namespace Tests\Feature;

use App\Models\Desk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function input(): array
    {
        return ['name' => 'New coworker', 'email' => 'coworker@example.test', 'password' => 'SyntheticPassword123!', 'password_confirmation' => 'SyntheticPassword123!', 'role' => 'agent', 'permission_group' => 'conversion-agent', 'status' => 'active'];
    }

    public function test_admin_creates_user_and_desk_memberships_atomically_without_exposing_passwords(): void
    {
        $this->signIn();
        $desks = Desk::factory()->count(2)->create();
        $response = $this->postJson('/api/v1/users', $this->input() + ['desk_ids' => $desks->modelKeys()])
            ->assertCreated()->assertJsonPath('data.permission_group', 'conversion-agent')
            ->assertJsonPath('data.desk_count', 2)->assertJsonCount(2, 'data.desks')
            ->assertJsonPath('data.last_login_at', null)->assertJsonMissingPath('data.password');
        $user = User::where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertTrue(Hash::check($this->input()['password'], $user->password));
        $this->getJson('/api/v1/users/'.$user->uuid)->assertOk()->assertJsonPath('data.desk_count', 2);
    }

    public function test_blank_password_keeps_hash_while_edits_replace_or_preserve_desks(): void
    {
        $this->signIn();
        $user = User::factory()->create();
        $hash = $user->password;
        $desk = Desk::factory()->create();
        $user->desks()->attach($desk);
        $this->patchJson('/api/v1/users/'.$user->uuid, ['name' => 'Renamed', 'password' => '', 'permission_group' => 'retention-tl'])
            ->assertOk()->assertJsonPath('data.desk_count', 1);
        $this->assertSame($hash, $user->fresh()->password);
        $replacement = Desk::factory()->create();
        $this->patchJson('/api/v1/users/'.$user->uuid, ['desk_ids' => [$replacement->id]])->assertOk()->assertJsonPath('data.desks.0.id', $replacement->id);
        $this->patchJson('/api/v1/users/'.$user->uuid, ['desk_ids' => []])->assertOk()->assertJsonPath('data.desk_count', 0);
        $this->assertModelExists($desk);
    }

    public function test_invalid_groups_desks_and_passwords_are_rejected_without_partial_writes(): void
    {
        $this->signIn();
        foreach ([['permission_group' => 'super-admin'], ['desk_ids' => ['not-a-uuid']], ['desk_ids' => ['d20e7a0b-e9ca-4bb3-966d-badbdc14c5dd']], ['password' => 'short'], ['last_login_at' => '2026-01-01T00:00:00Z']] as $invalid) {
            $this->postJson('/api/v1/users', array_replace($this->input(), $invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('desk_user', 0);
        $user = User::factory()->create(['name' => 'Original']);
        $this->patchJson('/api/v1/users/'.$user->uuid, ['name' => 'Should not save', 'desk_ids' => ['not-a-uuid']])->assertUnprocessable();
        $this->assertSame('Original', $user->fresh()->name);
    }

    public function test_all_five_groups_are_metadata_and_do_not_grant_admin_access(): void
    {
        $this->signIn();
        $user = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        foreach (['admin', 'conversion-agent', 'retention-agent', 'conversion-tl', 'retention-tl'] as $group) {
            $this->patchJson('/api/v1/users/'.$user->uuid, ['permission_group' => $group])->assertOk()->assertJsonPath('data.permission_group', $group);
        }
        $user->forceFill(['permission_group' => 'admin'])->save();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/users', $this->input())->assertForbidden();
        $this->patchJson('/api/v1/users/'.$user->uuid, ['permission_group' => 'admin'])->assertForbidden();
    }

    public function test_password_replacement_revokes_tokens_and_current_password_is_never_returned(): void
    {
        $this->signIn();
        $user = User::factory()->create();
        $user->createToken('existing');
        $this->patchJson('/api/v1/users/'.$user->uuid, ['password' => 'NewSyntheticPassword123!', 'password_confirmation' => 'NewSyntheticPassword123!'])
            ->assertOk()->assertJsonMissingPath('data.password');
        $this->assertTrue(Hash::check('NewSyntheticPassword123!', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_last_login_changes_only_after_successful_login(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $updated = $user->updated_at->toISOString();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->assertNull($user->fresh()->last_login_at);
        $this->travel(1)->hours();
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->assertNotNull($user->fresh()->last_login_at);
        $response->assertJsonPath('data.last_login_at', $user->fresh()->last_login_at->toISOString());
        $this->assertSame($updated, $user->fresh()->updated_at->toISOString());
    }

    public function test_swagger_includes_group_and_desk_assignment_inputs(): void
    {
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
        $spec = $this->getJson('/api/openapi.json')->assertOk()->json();
        $schema = $spec['paths']['/users']['post']['requestBody']['content']['application/json']['schema'];
        if (isset($schema['$ref'])) {
            $schema = $spec['components']['schemas'][basename($schema['$ref'])];
        }
        $this->assertArrayHasKey('permission_group', $schema['properties']);
        $this->assertArrayHasKey('desk_ids', $schema['properties']);
    }
}
