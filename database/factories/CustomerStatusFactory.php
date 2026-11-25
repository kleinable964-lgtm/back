<?php

namespace Database\Factories;

use App\Models\CustomerStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerStatus> */
class CustomerStatusFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->word(), 'color' => '#64748b', 'is_default' => false, 'disabled_for' => [], 'is_ftd_status' => false, 'order_weight' => 0];
    }
}
