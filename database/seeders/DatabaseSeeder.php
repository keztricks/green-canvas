<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Election;
use App\Models\Export;
use App\Models\KnockResult;
use App\Models\User;
use App\Models\UserWardExportSchedule;
use App\Models\Ward;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Demo data seeder.
 *
 * Populates the database with synthetic users, wards, addresses, knock
 * results and elections for local development. Not intended for
 * production — never run this on a live deployment.
 *
 * For production first-run, use `php artisan canvassing:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(FeatureFlagSeeder::class);

        // ── Demo users ────────────────────────────────────────────────────
        // Passwords are all "password". Change or remove before any real use.
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        User::create([
            'name' => 'Canvasser User',
            'email' => 'canvasser@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CANVASSER,
        ]);

        $wardAdmin = User::create([
            'name' => 'Ward Admin User',
            'email' => 'wardadmin@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_WARD_ADMIN,
        ]);

        $canvasser2 = User::create([
            'name' => 'Canvasser Two',
            'email' => 'canvasser2@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CANVASSER,
        ]);

        // ── Demo wards ────────────────────────────────────────────────────
        $wardNames = [
            'North Ward',
            'South Ward',
            'East Ward',
            'West Ward',
            'Central Ward',
            'Riverside Ward',
        ];

        foreach ($wardNames as $wardName) {
            Ward::create(['name' => $wardName]);
        }

        $wardAdmin->wards()->attach([
            Ward::where('name', 'North Ward')->first()->id,
            Ward::where('name', 'South Ward')->first()->id,
        ]);

        $canvasser2->wards()->attach([
            Ward::where('name', 'North Ward')->first()->id,
        ]);

        // ── Demo elections ────────────────────────────────────────────────
        Election::create([
            'name' => 'Demo General Election',
            'election_date' => now()->subMonths(6),
            'type' => 'general',
            'active' => true,
        ]);

        $localElection = Election::create([
            'name' => 'Demo Local Elections',
            'election_date' => now()->addMonths(3),
            'type' => 'local',
            'active' => true,
        ]);

        $localElection->wards()->attach([
            Ward::where('name', 'North Ward')->first()->id,
            Ward::where('name', 'South Ward')->first()->id,
            Ward::where('name', 'Central Ward')->first()->id,
        ]);

        $byElection = Election::create([
            'name' => 'Demo East Ward By-Election',
            'election_date' => now()->addMonths(1),
            'type' => 'by-election',
            'active' => true,
        ]);

        $byElection->wards()->attach([
            Ward::where('name', 'East Ward')->first()->id,
        ]);

        // ── Demo addresses + knock results ───────────────────────────────
        $users = User::all();
        $allWards = Ward::all();
        $elections = Election::all();
        $createdAddresses = [];
        $demoConstituency = config('canvassing.default_constituency') ?: 'Demo Constituency';

        $streetsTemplate = [
            [
                'street_name' => 'High Street',
                'houses' => ['1', '3', '5', '7', '9', '11', '13', '15', '17', '19'],
            ],
            [
                'street_name' => 'Church Lane',
                'houses' => ['2', '4', '6', '8', '10', '12', '14', '16', '18', '20'],
            ],
            [
                'street_name' => 'Park Road',
                'houses' => ['21', '23', '25', '27', '29', '31', '33', '35'],
            ],
        ];

        foreach ($allWards as $index => $ward) {
            foreach ($streetsTemplate as $streetTemplate) {
                foreach ($streetTemplate['houses'] as $houseIndex => $houseNumber) {
                    $address = Address::create([
                        'ward_id' => $ward->id,
                        'house_number' => $houseNumber,
                        'street_name' => $streetTemplate['street_name'],
                        'town' => 'Demoville',
                        'postcode' => 'DE' . (($index % 9) + 1) . ' ' . chr(65 + ($index % 26)) . chr(65 + ($houseIndex % 26)),
                        'constituency' => $demoConstituency,
                        'sort_order' => (int) preg_replace('/[^0-9]/', '', $houseNumber),
                    ]);

                    $createdAddresses[] = $address;

                    if (rand(1, 3) === 1) {
                        $this->createKnockResults($address, $users);
                    }
                }
            }
        }

        // ── Election associations (voted/not_voted status) ───────────────
        $activeElections = $elections->where('active', true);
        foreach ($createdAddresses as $address) {
            foreach ($activeElections as $election) {
                if ($election->wards->isNotEmpty() && !$election->wards->pluck('id')->contains($address->ward_id)) {
                    continue;
                }

                if (rand(1, 100) <= 20) {
                    $statuses = ['voted', 'not_voted', 'unknown'];
                    $address->elections()->attach($election->id, [
                        'status' => $statuses[array_rand($statuses)],
                        'notes' => rand(1, 3) === 1 ? null : ['Postal vote requested', 'Confirmed voter', 'Planning to vote', 'Needs reminder'][array_rand(['Postal vote requested', 'Confirmed voter', 'Planning to vote', 'Needs reminder'])],
                    ]);
                }
            }
        }

        // ── Demo exports + export schedules ──────────────────────────────
        $northWard = Ward::where('name', 'North Ward')->first();
        Export::create([
            'filename' => 'green-canvas-export-north-ward-v1.xlsx',
            'record_count' => 45,
            'version' => 'v1',
            'ward_id' => $northWard->id,
            'date_from' => now()->subDays(30),
            'date_to' => now(),
            'notes' => 'Demo export for North Ward',
        ]);

        Export::create([
            'filename' => 'green-canvas-export-all-wards-v2.xlsx',
            'record_count' => 200,
            'version' => 'v2',
            'ward_id' => null,
            'date_from' => now()->subDays(60),
            'date_to' => now()->subDays(30),
            'notes' => 'Historical demo export for all wards',
        ]);

        UserWardExportSchedule::create([
            'user_id' => $wardAdmin->id,
            'ward_id' => $northWard->id,
            'frequency' => 'weekly',
        ]);

        UserWardExportSchedule::create([
            'user_id' => $wardAdmin->id,
            'ward_id' => Ward::where('name', 'South Ward')->first()->id,
            'frequency' => 'none',
        ]);

        $this->command->info('Demo data created:');
        $this->command->info('  Users: ' . User::count());
        $this->command->info('  Wards: ' . Ward::count());
        $this->command->info('  Addresses: ' . Address::count());
        $this->command->info('  Knock Results: ' . KnockResult::count());
        $this->command->info('  Elections: ' . Election::count());
        $this->command->info('  Exports: ' . Export::count());
    }

    private function createKnockResults(Address $address, $users): void
    {
        $knockedDaysAgo = rand(0, 30);
        $responses = ['green', 'labour', 'conservative', 'lib_dem', 'undecided', 'not_home', 'refused'];
        $response = $responses[array_rand($responses)];
        $voteLikelihood = in_array($response, ['not_home', 'refused']) ? null : rand(1, 5);
        $notes = ['Very friendly', 'Asked about climate policy', 'Concerned about local issues', 'Will consider voting', 'Wants leaflets', 'No interest', 'Busy right now'];

        KnockResult::create([
            'address_id' => $address->id,
            'user_id' => $users->random()->id,
            'response' => $response,
            'vote_likelihood' => $voteLikelihood,
            'notes' => rand(1, 3) === 1 ? null : $notes[array_rand($notes)],
            'knocked_at' => now()->subDays($knockedDaysAgo),
        ]);

        if (rand(1, 10) <= 3) {
            $olderResponses = ['green', 'labour', 'conservative', 'lib_dem', 'undecided', 'not_home'];
            $olderResponse = $olderResponses[array_rand($olderResponses)];
            $olderVoteLikelihood = in_array($olderResponse, ['not_home', 'refused']) ? null : rand(1, 5);
            KnockResult::create([
                'address_id' => $address->id,
                'user_id' => $users->random()->id,
                'response' => $olderResponse,
                'vote_likelihood' => $olderVoteLikelihood,
                'notes' => rand(1, 2) === 1 ? null : ['Previous visit', 'Was undecided before', 'Spoke to different resident'][array_rand(['Previous visit', 'Was undecided before', 'Spoke to different resident'])],
                'knocked_at' => now()->subDays($knockedDaysAgo + rand(7, 60)),
            ]);
        }
    }
}
