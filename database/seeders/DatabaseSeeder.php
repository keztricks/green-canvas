<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Canvasser;
use App\Models\KnockResult;
use App\Models\User;
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

        // Create sample canvassers
        $canvasserNames = ['Sarah Thompson', 'John Davies', 'Emma Wilson', 'Michael Brown', 'Lucy Chen'];
        foreach ($canvasserNames as $name) {
            Canvasser::create(['name' => $name]);
        }

        // Create sample addresses for different streets in Skircoat ward, Halifax
        $streets = [
            [
                'street_name' => 'Skircoat Green Road',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['1', '3', '5', '7', '9', '11', '13', '15', '17', '19', '21', '23', '25', '27', '29', '2', '4', '6', '8', '10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30'],
                'postcode_prefix' => 'HX3 0A',
            ],
            [
                'street_name' => 'King Cross Road',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['45', '47', '49', '51', '53', '55', '57', '59', '61', '63', '65', '67', '69', '71', '46', '48', '50', '52', '54', '56', '58', '60', '62', '64', '66', '68', '70'],
                'postcode_prefix' => 'HX1 3J',
            ],
            [
                'street_name' => 'Free School Lane',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25'],
                'postcode_prefix' => 'HX1 3R',
            ],
            [
                'street_name' => 'Hopwood Lane',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['2', '4', '6', '8', '10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30', '1', '3', '5', '7', '9', '11', '13', '15', '17', '19', '21', '23', '25', '27', '29'],
                'postcode_prefix' => 'HX1 3T',
            ],
            [
                'street_name' => "Queen's Road",
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['25', '27', '29', '31', '33', '35', '37', '39', '41', '43', '45', '47', '49', '26', '28', '30', '32', '34', '36', '38', '40', '42', '44', '46', '48', '50'],
                'postcode_prefix' => 'HX1 4N',
            ],
            [
                'street_name' => 'Dryclough Lane',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['1', '3', '5', '7', '9', '11', '13', '15', '17', '19', '2', '4', '6', '8', '10', '12', '14', '16', '18', '20'],
                'postcode_prefix' => 'HX3 9J',
            ],
            [
                'street_name' => 'Savile Park Road',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['100', '102', '104', '106', '108', '110', '112', '114', '116', '118', '120', '122', '124', '99', '101', '103', '105', '107', '109', '111', '113', '115', '117', '119', '121', '123'],
                'postcode_prefix' => 'HX1 3E',
            ],
            [
                'street_name' => 'Arden Road',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30', '32', '34', '11', '13', '15', '17', '19', '21', '23', '25', '27', '29', '31', '33'],
                'postcode_prefix' => 'HX1 3A',
            ],
            [
                'street_name' => 'Gibbet Street',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['1', '3', '5', '7', '9', '11', '13', '15', '17', '2', '4', '6', '8', '10', '12', '14', '16', '18', '20'],
                'postcode_prefix' => 'HX1 4L',
            ],
            [
                'street_name' => 'Haley Hill',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['50', '52', '54', '56', '58', '60', '62', '64', '66', '68', '70', '51', '53', '55', '57', '59', '61', '63', '65', '67', '69', '71'],
                'postcode_prefix' => 'HX1 3N',
            ],
            [
                'street_name' => 'Parkinson Lane',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['5', '7', '9', '11', '13', '15', '17', '19', '21', '6', '8', '10', '12', '14', '16', '18', '20', '22'],
                'postcode_prefix' => 'HX1 3T',
            ],
            [
                'street_name' => 'Skircoat Road',
                'town' => 'Halifax',
                'constituency' => 'Halifax',
                'houses' => ['200', '202', '204', '206', '208', '210', '212', '214', '216', '218', '220', '199', '201', '203', '205', '207', '209', '211', '213', '215', '217', '219'],
                'postcode_prefix' => 'HX3 0B',
            ],
        ];

        foreach ($streets as $streetData) {
            foreach ($streetData['houses'] as $index => $houseNumber) {
                $address = Address::create([
                    'house_number' => $houseNumber,
                    'street_name' => $streetData['street_name'],
                    'town' => $streetData['town'],
                    'postcode' => $streetData['postcode_prefix'] . chr(65 + ($index % 26)),
                    'constituency' => $streetData['constituency'],
                    'sort_order' => (int) preg_replace('/[^0-9]/', '', $houseNumber),
                ]);

                // Add some knock results to a few random addresses
                if (rand(1, 3) === 1) {
                    KnockResult::create([
                        'address_id' => $address->id,
                        'response' => ['green', 'labour', 'conservative', 'lib_dem', 'undecided', 'not_home', 'refused'][array_rand(['green', 'labour', 'conservative', 'lib_dem', 'undecided', 'not_home', 'refused'])],
                        'notes' => rand(1, 2) === 1 ? null : ['Very friendly', 'Asked about climate policy', 'Concerned about local issues', 'Will consider voting'][array_rand(['Very friendly', 'Asked about climate policy', 'Concerned about local issues', 'Will consider voting'])],
                        'canvasser_name' => ['Sarah', 'John', 'Emma', 'Michael', null][array_rand(['Sarah', 'John', 'Emma', 'Michael', null])],
                        'knocked_at' => now()->subDays(rand(0, 7)),
                    ]);
                }
            }
        }

        $this->command->info('Sample addresses and knock results created successfully!');
    }
}
