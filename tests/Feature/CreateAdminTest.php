<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_operator_can_create_an_admin_with_a_private_password_prompt(): void
    {
        $this->artisan('crm:create-admin', ['email' => 'ADMIN@example.test', '--name' => 'First Admin'])
            ->expectsQuestion('Password', 'LongPassword123')
            ->expectsQuestion('Confirm password', 'LongPassword123')
            ->expectsOutput('Administrator created. You can now sign in.')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('LongPassword123', $user->password));
    }

    public function test_weak_or_mismatched_password_does_not_create_an_admin(): void
    {
        $this->artisan('crm:create-admin', ['email' => 'admin@example.test'])
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_coworker_cannot_be_replaced_or_promoted_by_creation(): void
    {
        $user = User::factory()->create();
        $this->artisan('crm:create-admin', ['email' => $user->email])
            ->expectsQuestion('Password', 'LongPassword123')
            ->expectsQuestion('Confirm password', 'LongPassword123')
            ->assertFailed();

        $this->assertNull($user->fresh()->role);
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertDatabaseCount('users', 1);
    }
}
