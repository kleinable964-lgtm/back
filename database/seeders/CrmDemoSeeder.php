<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Client;
use App\Models\CustomerStatus;
use App\Models\Department;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CrmDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('CRM demo data is only available locally or in tests.');
        }
        $department = Department::factory()->create();
        $desk = Desk::factory()->for($department)->create(['name' => 'Demo conversion', 'category' => 'conversion']);
        $user = User::factory()->create([
            'role' => 'agent',
            'status' => 'active',
            'password' => Str::random(40),
        ]);
        $desk->users()->attach($user);
        $brand = Brand::factory()->create();
        $status = CustomerStatus::factory()->create(['name' => 'New', 'is_default' => true]);
        Client::factory()->count(3)->for($brand)->for($status)->for($desk)->for($user, 'assignedUser')->create();
    }
}
