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
                'key' => 'export_email_schedules',
                'name' => 'Export Email Schedules',
                'description' => 'Allow users to configure automatic email delivery of exports on a daily or weekly schedule',
                'is_enabled' => false,
            ],
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
