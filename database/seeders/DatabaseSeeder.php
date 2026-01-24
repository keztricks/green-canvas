<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\KnockResult;
use App\Models\User;
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
        
        // Get Wainhouse ward for sample addresses
        $wainhouseWard = Ward::where('name', 'Wainhouse')->first();

        // Get created users for knock results
        $users = User::all();

        // Create sample addresses for different streets in Wainhouse ward
        $streets = [
            [
                'street_name' => 'Skircoat Green Road',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['253', '273', '275', '277', '279', '283', '285', '287', '287A', '289', '291', '293'],
                'postcode_prefix' => 'HX3 0B',
            ],
            [
                'street_name' => 'Upper Washer Lane',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['39', '41', '43', '44', '45', '46', '47', '48', '49', '50', '52', '54', '58', '60', '62', '64', '68', '70', '76'],
                'postcode_prefix' => 'HX2 7D',
            ],
            [
                'street_name' => 'Wakefield Road',
                'town' => 'Sowerby Bridge',
                'constituency' => 'Halifax',
                'houses' => ['99', '231', '233', '235', '237', '239', '241', '243', '245', '247', '249'],
                'postcode_prefix' => 'HX6 2U',
            ],
            [
                'street_name' => 'Arden Road',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['1', '3', '5', '7', '9', '11', '13', '15', '17', '19', '21', '23', '25', '2', '4', '6', '8', '10', '12', '14', '16', '18', '20', '22', '24'],
                'postcode_prefix' => 'HX1 3A',
            ],
            [
                'street_name' => 'Washer Lane',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['5', '41', 'Birch House', 'Hawthorn House'],
                'postcode_prefix' => 'HX2 7D',
            ],
        ];

        foreach ($streets as $streetData) {
            foreach ($streetData['houses'] as $index => $houseNumber) {
                $address = Address::create([
                    'ward_id' => $wainhouseWard->id,
                    'house_number' => $houseNumber,
                    'street_name' => $streetData['street_name'],
                    'town' => $streetData['town'],
                    'postcode' => $streetData['postcode_prefix'] . chr(65 + ($index % 26)),
                    'constituency' => $streetData['constituency'],
                    'sort_order' => (int) preg_replace('/[^0-9]/', '', $houseNumber),
                ]);

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

        $this->command->info('Sample addresses and knock results created successfully!');
    }
}
