<?php

namespace Database\Factories;

use App\Models\Desk;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Desk> */
class DeskFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(2, true), 'category' => null, 'is_active' => true, 'department_id' => null];
    }
}
