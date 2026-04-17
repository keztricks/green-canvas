<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KnockResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'address_id' => Address::factory(),
            'user_id' => User::factory(),
            'response' => 'not_home',
            'knocked_at' => now(),
        ];
    }
}
