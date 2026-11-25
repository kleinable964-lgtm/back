<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return ['first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'email' => fake()->safeEmail(), 'phone' => fake()->e164PhoneNumber(), 'brand_id' => Brand::factory(), 'signup_status' => 'pending'];
    }
}
