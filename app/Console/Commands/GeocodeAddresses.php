<?php

namespace App\Console\Commands;

use App\Models\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeAddresses extends Command
{
    protected $signature = 'addresses:geocode {--ward= : Only geocode addresses in this ward ID} {--force : Re-geocode addresses that already have coordinates}';
    protected $description = 'Geocode addresses via postcodes.io and store lat/lon';

    public function handle(): int
    {
        $query = Address::query();

        if ($wardId = $this->option('ward')) {
            $query->where('ward_id', $wardId);
        }

        if (!$this->option('force')) {
            $query->whereNull('latitude');
        }

        $uniquePostcodes = $query->distinct()->pluck('postcode')->filter()->values();

        if ($uniquePostcodes->isEmpty()) {
            $this->info('No postcodes to geocode.');
            return self::SUCCESS;
        }

        $this->info("Geocoding {$uniquePostcodes->count()} unique postcodes...");
        $bar = $this->output->createProgressBar($uniquePostcodes->count());
        $bar->start();

        $geocoded = 0;
        $failed = 0;

        foreach ($uniquePostcodes->chunk(100) as $batch) {
            $results = $this->lookupPostcodes($batch->all());
            $bar->advance($batch->count());

            foreach ($results as $postcode => $coords) {
                if (!$coords) {
                    $failed++;
                    continue;
                }

                Address::where('postcode', $postcode)
                    ->when($wardId = $this->option('ward'), fn($q) => $q->where('ward_id', $wardId))
                    ->update(['latitude' => $coords['lat'], 'longitude' => $coords['lng']]);

                $geocoded++;
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$geocoded} postcodes geocoded, {$failed} failed.");

        return self::SUCCESS;
    }

    public static function lookupPostcodes(array $postcodes): array
    {
        try {
            $response = Http::timeout(15)->post('https://api.postcodes.io/postcodes', [
                'postcodes' => $postcodes,
            ]);

            if (!$response->successful()) {
                return [];
            }

            $results = [];
            foreach ($response->json('result', []) as $item) {
                $postcode = $item['query'];
                $results[$postcode] = $item['result']
                    ? ['lat' => $item['result']['latitude'], 'lng' => $item['result']['longitude']]
                    : null;
            }

            return $results;
        } catch (\Exception) {
            return [];
        }
    }
}
