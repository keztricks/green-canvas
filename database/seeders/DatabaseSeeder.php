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

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed feature flags (safe to run multiple times)
        $this->call(FeatureFlagSeeder::class);

        // Create sample users
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

        // Create wards
        $wardNames = [
            'Brighouse',
            'Elland',
            'Greetland',
            'Halifax Town',
            'Hebden Bridge & Todmorden East',
            'Hipperholme & Lightcliffe',
            'Illingworth & Mixenden',
            'Luddendenfoot',
            'Northowram & Shelf',
            'Ovenden',
            'Park',
            'Rastrick',
            'Ryburn',
            'Salterhebble Southowram and Skircoat Green',
            'Sowerby Bridge',
            'Todmorden West',
            'Wainhouse',
            'Warley',
        ];
        
        foreach ($wardNames as $wardName) {
            Ward::create(['name' => $wardName]);
        }
        
        // Assign some wards to the ward admin user
        $wardAdmin->wards()->attach([
            Ward::where('name', 'Wainhouse')->first()->id,
            Ward::where('name', 'Sowerby Bridge')->first()->id,
        ]);
        
        // Assign wards to canvasser users
        $canvasser2->wards()->attach([
            Ward::where('name', 'Wainhouse')->first()->id,
        ]);

        // Create elections
        $generalElection = Election::create([
            'name' => 'Example General Election',
            'election_date' => now()->subMonths(6),
            'type' => 'general',
            'active' => true,
        ]);
        
        $localElection = Election::create([
            'name' => 'Example Local Elections',
            'election_date' => now()->addMonths(3),
            'type' => 'local',
            'active' => true,
        ]);
        
        // Attach some wards to the local election
        $localElection->wards()->attach([
            Ward::where('name', 'Wainhouse')->first()->id,
            Ward::where('name', 'Sowerby Bridge')->first()->id,
            Ward::where('name', 'Park')->first()->id,
        ]);
        
        $byElection = Election::create([
            'name' => 'Example Elland By-Election',
            'election_date' => now()->addMonths(1),
            'type' => 'by-election',
            'active' => true,
        ]);
        
        // Attach specific ward to by-election
        $byElection->wards()->attach([
            Ward::where('name', 'Elland')->first()->id,
        ]);

        // Get created users for knock results
        $users = User::all();
        
        // Get all wards
        $allWards = Ward::all();
        
        // Get all elections
        $elections = Election::all();
        
        // Store created addresses for later use
        $createdAddresses = [];

        // Create sample addresses for different streets - distributed across all wards
        $streetsTemplate = [
            [
                'street_name' => 'High Street',
                'houses' => ['1', '3', '5', '7', '9', '11', '13', '15', '17', '19'],
                'postcode_prefix' => 'HX1 1A',
            ],
            [
                'street_name' => 'Church Lane',
                'houses' => ['2', '4', '6', '8', '10', '12', '14', '16', '18', '20'],
                'postcode_prefix' => 'HX2 2B',
            ],
            [
                'street_name' => 'Park Road',
                'houses' => ['21', '23', '25', '27', '29', '31', '33', '35'],
                'postcode_prefix' => 'HX3 3C',
            ],
        ];
        
        // Create streets for each ward
        $streets = [];
        foreach ($allWards as $index => $ward) {
            foreach ($streetsTemplate as $streetTemplate) {
                $streets[] = [
                    'ward_id' => $ward->id,
                    'street_name' => $streetTemplate['street_name'],
                    'town' => $ward->name,
                    'constituency' => 'Halifax',
                    'houses' => $streetTemplate['houses'],
                    'postcode_prefix' => 'HX' . (($index % 9) + 1) . ' ' . chr(65 + ($index % 26)),
                ];
            }
        }

        foreach ($streets as $streetData) {
            foreach ($streetData['houses'] as $index => $houseNumber) {
                $address = Address::create([
                    'ward_id' => $streetData['ward_id'],
                    'house_number' => $houseNumber,
                    'street_name' => $streetData['street_name'],
                    'town' => $streetData['town'],
                    'postcode' => $streetData['postcode_prefix'] . chr(65 + ($index % 26)),
                    'constituency' => $streetData['constituency'],
                    'sort_order' => (int) preg_replace('/[^0-9]/', '', $houseNumber),
                ]);
                
                // Store address for later use
                $createdAddresses[] = $address;

                // Add some knock results to a few random addresses
                if (rand(1, 3) === 1) {
                    // 70% chance of having a knock result
                    $knockedDaysAgo = rand(0, 30);
                    $response = ['green', 'labour', 'conservative', 'lib_dem', 'undecided', 'not_home', 'refused'][array_rand(['green', 'labour', 'conservative', 'lib_dem', 'undecided', 'not_home', 'refused'])];
                    
                    // Vote likelihood only for party responses (not for not_home or refused)
                    $voteLikelihood = null;
                    if (!in_array($response, ['not_home', 'refused'])) {
                        $voteLikelihood = rand(1, 5);
                    }
                    
                    KnockResult::create([
                        'address_id' => $address->id,
                        'user_id' => $users->random()->id,
                        'response' => $response,
                        'vote_likelihood' => $voteLikelihood,
                        'notes' => rand(1, 3) === 1 ? null : ['Very friendly', 'Asked about climate policy', 'Concerned about local issues', 'Will consider voting', 'Wants leaflets', 'No interest', 'Busy right now'][array_rand(['Very friendly', 'Asked about climate policy', 'Concerned about local issues', 'Will consider voting', 'Wants leaflets', 'No interest', 'Busy right now'])],
                        'knocked_at' => now()->subDays($knockedDaysAgo),
                    ]);
                    
                    // 30% chance of having a second historical knock result
                    if (rand(1, 10) <= 3) {
                        $olderResponse = ['green', 'labour', 'conservative', 'lib_dem', 'undecided', 'not_home'][array_rand(['green', 'labour', 'conservative', 'lib_dem', 'undecided', 'not_home'])];
                        $olderVoteLikelihood = null;
                        if (!in_array($olderResponse, ['not_home', 'refused'])) {
                            $olderVoteLikelihood = rand(1, 5);
                        }
                        
                        KnockResult::create([
                            'address_id' => $address->id,
                            'user_id' => $users->random()->id,
                            'response' => $olderResponse,
                            'vote_likelihood' => $olderVoteLikelihood,
                            'notes' => rand(1, 2) === 1 ? null : ['Previous visit', 'Was undecided before', 'Spoke to different resident'][array_rand(['Previous visit', 'Was undecided before', 'Spoke to different resident'])],
                            'knocked_at' => now()->subDays($knockedDaysAgo + rand(7, 60)),
                        ]);
                    }
                    
                    // 10% chance of having a third historical knock result
                    if (rand(1, 10) === 1) {
                        $oldestResponse = ['green', 'labour', 'conservative', 'undecided', 'not_home'][array_rand(['green', 'labour', 'conservative', 'undecided', 'not_home'])];
                        $oldestVoteLikelihood = null;
                        if (!in_array($oldestResponse, ['not_home', 'refused'])) {
                            $oldestVoteLikelihood = rand(1, 5);
                        }
                        
                        KnockResult::create([
                            'address_id' => $address->id,
                            'user_id' => $users->random()->id,
                            'response' => $oldestResponse,
                            'vote_likelihood' => $oldestVoteLikelihood,
                            'notes' => rand(1, 2) === 1 ? null : ['Initial contact', 'First visit'][array_rand(['Initial contact', 'First visit'])],
                            'knocked_at' => now()->subDays($knockedDaysAgo + rand(70, 120)),
                        ]);
                    }
                }
            }
        }

        // Add election associations to some addresses (voted/not_voted status)
        $activeElections = $elections->where('active', true);
        foreach ($createdAddresses as $address) {
            // Check if this address is in a ward that has an active election
            foreach ($activeElections as $election) {
                // Skip if election has ward restrictions and this address isn't in one of them
                if ($election->wards->isNotEmpty() && !$election->wards->pluck('id')->contains($address->ward_id)) {
                    continue;
                }
                
                // 20% chance of having an election association (voting status)
                if (rand(1, 100) <= 20) {
                    $statuses = ['voted', 'not_voted', 'unknown'];
                    $address->elections()->attach($election->id, [
                        'status' => $statuses[array_rand($statuses)],
                        'notes' => rand(1, 3) === 1 ? null : ['Postal vote requested', 'Confirmed voter', 'Planning to vote', 'Needs reminder'][array_rand(['Postal vote requested', 'Confirmed voter', 'Planning to vote', 'Needs reminder'])],
                    ]);
                }
            }
        }

        // Create sample exports
        $wainhouseWard = Ward::where('name', 'Wainhouse')->first();
        Export::create([
            'filename' => 'green-canvas-export-wainhouse-v1.xlsx',
            'record_count' => 45,
            'version' => 'v1',
            'ward_id' => $wainhouseWard->id,
            'date_from' => now()->subDays(30),
            'date_to' => now(),
            'notes' => 'Sample export for Wainhouse ward',
        ]);
        
        Export::create([
            'filename' => 'green-canvas-export-all-wards-v2.xlsx',
            'record_count' => 200,
            'version' => 'v2',
            'ward_id' => null,
            'date_from' => now()->subDays(60),
            'date_to' => now()->subDays(30),
            'notes' => 'Historical export for all wards',
        ]);

        // Create export schedules for ward admin
        UserWardExportSchedule::create([
            'user_id' => $wardAdmin->id,
            'ward_id' => $wainhouseWard->id,
            'frequency' => 'weekly',
        ]);
        
        UserWardExportSchedule::create([
            'user_id' => $wardAdmin->id,
            'ward_id' => Ward::where('name', 'Sowerby Bridge')->first()->id,
            'frequency' => 'none',
        ]);

        $this->command->info('Sample data created successfully!');
        $this->command->info('- Users: ' . User::count());
        $this->command->info('- Wards: ' . Ward::count());
        $this->command->info('- Addresses: ' . Address::count());
        $this->command->info('- Knock Results: ' . KnockResult::count());
        $this->command->info('- Elections: ' . Election::count());
        $this->command->info('- Exports: ' . Export::count());
    }
}
