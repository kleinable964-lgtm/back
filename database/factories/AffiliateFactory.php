<?php

namespace Database\Factories;

use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Affiliate> */
class AffiliateFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company()];
    }
}
