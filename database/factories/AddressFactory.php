<?php

namespace Database\Factories;

use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ward_id' => Ward::factory(),
            'house_number' => (string) fake()->numberBetween(1, 200),
            'street_name' => fake()->streetName(),
            'town' => fake()->city(),
            'postcode' => fake()->postcode(),
            'constituency' => fake()->city(),
            'sort_order' => fake()->numberBetween(1, 10000),
            'elector_count' => 1,
            'do_not_knock' => false,
        ];
    }
}
