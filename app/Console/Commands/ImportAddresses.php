<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Street;
use App\Services\AddressNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use League\Csv\Reader;

class ImportAddresses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addresses:import {file} {--geocode=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import addresses from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle(AddressNormalizer $normalizer): int
    {
        $file = $this->argument('file');
        $geocode = $this->option('geocode') === 'true' || $this->option('geocode') === '1';

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return Command::FAILURE;
        }

        $this->info("Reading CSV file: {$file}");

        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        // Detect address column name
        $addressColumn = null;
        foreach (['address', 'raw_address', 'Address'] as $candidate) {
            if (in_array($candidate, $headers)) {
                $addressColumn = $candidate;
                break;
            }
        }

        if (!$addressColumn) {
            $this->error("Could not find address column in CSV. Expected one of: address, raw_address, Address");
            return Command::FAILURE;
        }

        $this->info("Using address column: {$addressColumn}");

        $records = $csv->getRecords();
        $count = 0;
        $streetsCache = [];
        $geocodeCache = [];

        foreach ($records as $record) {
            $rawAddress = $record[$addressColumn] ?? null;

            if (!$rawAddress) {
                continue;
            }

            // Normalize address
            $postcode = $normalizer->findPostcode($rawAddress);
            $parts = $normalizer->extractHouseAndStreet($rawAddress);
            $streetKey = $normalizer->makeKey($parts);
            $norm = $normalizer->normText($rawAddress);

            // Geocode if enabled and postcode found
            $lat = null;
            $lon = null;
            if ($geocode && $postcode) {
                // Use cache to avoid repeated API calls
                if (!isset($geocodeCache[$postcode])) {
                    $baseUrl = config('app.postcodes_io_base', 'https://api.postcodes.io');
                    $response = Http::get("{$baseUrl}/postcodes/" . urlencode($postcode));
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['result'])) {
                            $geocodeCache[$postcode] = [
                                'lat' => $data['result']['latitude'] ?? null,
                                'lon' => $data['result']['longitude'] ?? null,
                            ];
                        }
                    } else {
                        $geocodeCache[$postcode] = ['lat' => null, 'lon' => null];
                    }
                }

                $lat = $geocodeCache[$postcode]['lat'] ?? null;
                $lon = $geocodeCache[$postcode]['lon'] ?? null;
            }

            // Create or get street
            $streetId = null;
            if ($streetKey) {
                if (!isset($streetsCache[$streetKey])) {
                    $street = Street::firstOrCreate(
                        ['street_norm' => $streetKey],
                        ['display_name' => $parts['street_name'] ?? $streetKey]
                    );
                    $streetsCache[$streetKey] = $street->id;
                }
                $streetId = $streetsCache[$streetKey];
            }

            // Insert address
            Address::create([
                'raw_address' => $rawAddress,
                'postcode' => $postcode,
                'house_number' => $parts['house_number'],
                'street_name' => $parts['street_name'],
                'street_norm' => $streetKey,
                'norm' => $norm,
                'street_id' => $streetId,
                'lat' => $lat,
                'lon' => $lon,
                'status' => 'unvisited',
            ]);

            $count++;

            if ($count % 100 === 0) {
                $this->info("Imported {$count} addresses...");
            }
        }

        $this->info("Import complete. Total addresses imported: {$count}");

        return Command::SUCCESS;
    }
}
