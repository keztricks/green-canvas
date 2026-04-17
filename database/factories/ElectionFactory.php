<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ElectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city() . ' ' . fake()->year() . ' Election',
            'election_date' => fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'type' => fake()->randomElement(['general', 'local', 'by-election', 'other']),
            'active' => true,
        ];
    }
}
