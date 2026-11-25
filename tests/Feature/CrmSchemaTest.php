<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Country;
use App\Models\CustomerStatus;
use App\Models\Department;
use App\Models\Desk;
use App\Models\User;
use Database\Seeders\CrmDemoSeeder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_coworkers_and_clients_are_separate_identities(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['email' => $user->email]);
        $this->assertTrue(Str::isUuid($user->uuid));
        $this->assertIsInt($user->id);
        $this->assertNull($user->fresh()->role);
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertSame('pending', $client->fresh()->signup_status);
        $this->assertFalse(Schema::hasColumn('clients', 'password'));
        $this->assertNotInstanceOf(Authenticatable::class, $client);
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $token = $user->createToken('schema-test');
        $this->assertTrue($token->accessToken->tokenable->is($user));
    }

    public function test_profile_mass_assignment_cannot_change_role_status_or_public_identity(): void
    {
        $user = User::factory()->create();
        $user->fill(['role' => 'admin', 'status' => 'active', 'uuid' => (string) Str::uuid()])->save();
        $this->assertNull($user->fresh()->role);
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertSame($user->uuid, $user->fresh()->uuid);
    }

    public function test_a_user_can_belong_to_multiple_desks_and_a_desk_to_multiple_users(): void
    {
        $department = Department::factory()->create();
        $first = Desk::factory()->for($department)->create();
        $second = Desk::factory()->create();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first->users()->attach([$user->id, $other->id]);
        $second->users()->attach($user);
        $this->assertCount(2, $user->desks);
        $this->assertCount(2, $first->users);
        $this->assertTrue($first->department->is($department));
        $this->assertNull($second->department);
        $this->assertNull($second->category);
        $this->assertTrue($second->is_active);
        $this->assertFalse(Schema::hasColumn('desks', 'is_default'));
    }

    public function test_duplicate_desk_membership_is_rejected(): void
    {
        $desk = Desk::factory()->create();
        $user = User::factory()->create();
        $desk->users()->attach($user);
        $this->expectException(QueryException::class);
        $desk->users()->attach($user);
    }

    public function test_removing_default_flag_preserves_desks_and_manual_memberships(): void
    {
        $department = Department::factory()->create();
        $desk = Desk::factory()->for($department)->create();
        $user = User::factory()->create();
        $desk->users()->attach($user);
        $migration = require database_path('migrations/2026_11_18_103925_remove_default_flag_from_desks_table.php');
        $migration->down();
        DB::table('desks')->where('id', $desk->id)->update(['is_default' => true]);
        $migration->up();

        $this->assertFalse(Schema::hasColumn('desks', 'is_default'));
        $this->assertSame($desk->name, $desk->fresh()->name);
        $this->assertSame($department->id, $desk->fresh()->department_id);
        $this->assertTrue($desk->fresh()->users->sole()->is($user));
    }

    public function test_clients_link_to_websites_and_existing_crm_assignments(): void
    {
        $brand = Brand::factory()->create(['design_version' => '1.10', 'transactional_email_templates' => (object) []]);
        $desk = Desk::factory()->create();
        $user = User::factory()->create();
        $country = Country::factory()->create();
        $status = CustomerStatus::factory()->create();
        $affiliate = Affiliate::factory()->create();
        $client = Client::factory()->for($brand)->for($desk)->for($country)->for($status)->for($affiliate)->for($user, 'assignedUser')->create();
        $this->assertTrue($client->brand->is($brand));
        $this->assertTrue($client->desk->is($desk));
        $this->assertTrue($client->assignedUser->is($user));
        $this->assertTrue($client->country->is($country));
        $this->assertTrue($client->customerStatus->is($status));
        $this->assertTrue($client->affiliate->is($affiliate));
        $this->assertTrue($user->assignedClients->first()->is($client));
        $this->assertSame('1.10', $brand->fresh()->design_version);
        $this->assertEquals((object) [], $brand->fresh()->transactional_email_templates);
        $this->assertTrue($brand->fresh()->require_kyc_for_withdrawals);
    }

    public function test_clients_can_share_an_email_across_and_within_brands(): void
    {
        $client = Client::factory()->create();
        Client::factory()->create(['email' => $client->email, 'brand_id' => $client->brand_id]);
        Client::factory()->create(['email' => $client->email]);
        $this->assertDatabaseCount('clients', 3);
        $this->assertNull($client->desk_id);
        $this->assertNull($client->assigned_user_id);
    }

    public function test_referenced_brand_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        $this->expectException(QueryException::class);
        $client->brand->delete();
    }

    public function test_assigned_coworker_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Client::factory()->for($user, 'assignedUser')->create();
        $this->expectException(QueryException::class);
        $user->delete();
    }

    public function test_invalid_assignment_is_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);
        Client::factory()->create(['assigned_user_id' => 999999]);
    }

    public function test_deleting_an_unused_desk_removes_memberships_without_deleting_users(): void
    {
        $desk = Desk::factory()->create();
        $user = User::factory()->create();
        $desk->users()->attach($user);
        $desk->delete();
        $this->assertDatabaseCount('desk_user', 0);
        $this->assertModelExists($user);
    }

    public function test_existing_coworkers_are_backfilled_without_password_or_token_changes(): void
    {
        $core = require database_path('migrations/2026_11_17_105531_create_crm_core_tables.php');
        $extension = require database_path('migrations/2026_11_17_105530_extend_users_for_crm.php');
        $roles = require database_path('migrations/2026_11_23_102151_create_roles_table_and_link_users.php');
        $roles->down();
        $core->down();
        $extension->down();
        $password = Hash::make('existing-secret');
        $id = DB::table('users')->insertGetId(['name' => 'Existing coworker', 'email' => 'existing@example.test', 'password' => $password]);
        $user = User::findOrFail($id);
        $token = $user->createToken('existing-token');
        $extension->up();
        $core->up();
        $roles->up();
        $user->refresh();
        $this->assertSame($id, $user->id);
        $this->assertSame($password, $user->password);
        $this->assertTrue(Str::isUuid($user->uuid));
        $this->assertNull($user->role);
        $this->assertSame('pending', $user->status);
        $this->assertTrue($token->accessToken->fresh()->tokenable->is($user));
    }

    public function test_demo_seeder_creates_only_synthetic_connected_records(): void
    {
        $this->seed(CrmDemoSeeder::class);
        $this->assertDatabaseCount('clients', 3);
        $this->assertDatabaseCount('desk_user', 1);
        $this->assertDatabaseCount('users', 1);
    }
}
