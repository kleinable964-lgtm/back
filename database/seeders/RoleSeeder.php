<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [];
        foreach (Role::DEFINITIONS as $name => $label) {
            $roles[] = ['name' => $name, 'label' => $label];
        }
        Role::upsert($roles, ['name'], ['label', 'updated_at']);
    }
}
