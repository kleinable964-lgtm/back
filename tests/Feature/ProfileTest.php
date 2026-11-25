<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_timezone_is_saved_only_for_current_user_and_survives_a_new_request(): void
    {
        $user = User::factory()->create(['status' => 'active', 'role' => 'agent']);
        $other = User::factory()->create();
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/auth/preferences', ['timezone' => 'UTC-05:00', 'user_id' => $other->uuid, 'role' => 'admin'])
            ->assertOk()->assertJsonPath('data.timezone', 'UTC-05:00');
        $this->assertSame('UTC-05:00', $user->fresh()->timezone);
        $this->assertSame('agent', $user->fresh()->role);
        $this->assertSame('UTC', $other->fresh()->timezone);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.timezone', 'UTC-05:00');
    }

    public function test_timezone_requires_authentication_and_rejects_invalid_values(): void
    {
        $this->patchJson('/api/v1/auth/preferences', ['timezone' => 'UTC'])->assertUnauthorized();
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);
        foreach ([[], ['timezone' => 'UTC-3'], ['timezone' => 'Invalid/Zone'], ['timezone' => 'America/New_York'], ['timezone' => 'UTC+15:00'], ['timezone' => 'UTC+05:99'], ['timezone' => null], ['timezone' => 3]] as $invalid) {
            $this->patchJson('/api/v1/auth/preferences', $invalid)->assertUnprocessable()->assertJsonValidationErrors('timezone');
        }
        $this->assertSame('UTC', $user->fresh()->timezone);
        $user->forceFill(['status' => 'disabled'])->save();
        $this->patchJson('/api/v1/auth/preferences', ['timezone' => 'UTC'])->assertForbidden();
    }

    public function test_avatar_endpoints_and_resource_fields_are_removed(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']));
        $this->getJson('/api/v1/auth/avatar')->assertNotFound();
        $this->postJson('/api/v1/auth/avatar', [])->assertNotFound();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonMissingPath('data.avatar_url')->assertJsonMissingPath('data.avatar_path');
        $this->assertFalse(Schema::hasColumn('users', 'avatar_path'));
    }

    public function test_fixed_offset_options_are_unique_and_accept_hour_and_fractional_values(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);
        $offsets = config('timezones.offsets');
        $this->assertCount(count($offsets), array_unique($offsets));
        foreach (['UTC-12:00', 'UTC-11:00', 'UTC-10:00', 'UTC-03:30', 'UTC', 'UTC+05:45', 'UTC+13:45', 'UTC+14:00'] as $offset) {
            $this->patchJson('/api/v1/auth/preferences', ['timezone' => $offset])->assertOk()->assertJsonPath('data.timezone', $offset);
            $this->assertSame($offset, $user->fresh()->timezone);
        }
    }

    public function test_openapi_documents_the_fixed_offset_choices(): void
    {
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
        $spec = $this->getJson('/api/openapi.json')->assertOk()->json();
        $schema = $spec['paths']['/auth/preferences']['patch']['requestBody']['content']['application/json']['schema'];
        if (isset($schema['$ref'])) {
            $schema = $spec['components']['schemas'][basename($schema['$ref'])];
        }
        $this->assertEqualsCanonicalizing(config('timezones.offsets'), $schema['properties']['timezone']['enum']);
    }

    public function test_avatar_removal_preserves_existing_user_identity_password_and_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
        $password = $user->password;
        $migration = require database_path('migrations/2026_11_18_121339_remove_avatar_path_from_users_table.php');
        $migration->down();
        DB::table('users')->where('id', $user->id)->update(['avatar_path' => 'avatars/previous.png']);
        $migration->up();
        $this->assertFalse(Schema::hasColumn('users', 'avatar_path'));
        $this->assertSame($user->uuid, $user->fresh()->uuid);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame('Asia/Tokyo', $user->fresh()->timezone);
    }
}
