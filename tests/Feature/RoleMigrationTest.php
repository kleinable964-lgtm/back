<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_role_migration_preserves_existing_identity_tokens_desks_customers_and_role_assignments(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $desk = Desk::factory()->create();
        $user->desks()->attach($desk);
        $client = Client::factory()->for($user, 'assignedUser')->for($desk)->create();
        $token = $user->createToken('synthetic-role-migration')->accessToken;
        $password = $user->password;
        $migration = require database_path('migrations/2026_11_23_102151_create_roles_table_and_link_users.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('roles'));
        $migration->up();
        $this->assertSame('manager', $user->fresh()->role);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame($user->uuid, $user->fresh()->uuid);
        $this->assertSame('active', $user->fresh()->status);
        $this->assertTrue($token->fresh()->tokenable->is($user));
        $this->assertTrue($user->fresh()->desks->sole()->is($desk));
        $this->assertSame($user->id, $client->fresh()->assigned_user_id);
        $this->assertSame($user->updated_at->toISOString(), $user->fresh()->updated_at->toISOString());
    }
}
