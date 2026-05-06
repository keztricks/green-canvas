<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $flags = [
            [
                'key' => 'dark_mode',
                'name' => 'Dark Mode',
                'description' => 'Allow users to switch between light, dark, and system theme preferences',
                'is_enabled' => false,
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::firstOrCreate(
                ['key' => $flag['key']],
                $flag
            );
        }

        $this->command->info('Feature flags seeded successfully!');
    }
}
