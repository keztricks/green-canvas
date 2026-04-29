<?php

namespace App\Console\Commands;

use App\Models\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeAddresses extends Command
{
    protected $signature = 'addresses:geocode {--ward= : Only geocode addresses in this ward ID} {--force : Re-geocode addresses that already have coordinates}';
    protected $description = 'Geocode addresses via OS Places (address-level) with postcodes.io centroid fallback';

    public function handle(): int
    {
        $apiKey = config('services.os_places.key');

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

        $mode = $apiKey ? 'OS Places (address-level) + postcodes.io fallback' : 'postcodes.io centroid only';
        $this->info("Geocoding {$uniquePostcodes->count()} unique postcodes via {$mode}...");

        if (!$apiKey) {
            $this->line('  Tip: set OS_PLACES_API_KEY in .env for address-level precision.');
        }

        $bar = $this->output->createProgressBar($uniquePostcodes->count());
        $bar->start();

        $precise = 0;
        $centroid = 0;
        $failed = 0;
        $fallbackNeeded = [];

        foreach ($uniquePostcodes as $postcode) {
            $bar->advance();

            if (!$apiKey) {
                $fallbackNeeded[] = $postcode;
                continue;
            }

            $osResults = static::fetchOsPlaces($postcode, $apiKey);

            $addressesInPostcode = Address::where('postcode', $postcode)
                ->when($this->option('ward'), fn($q) => $q->where('ward_id', $this->option('ward')))
                ->when(!$this->option('force'), fn($q) => $q->whereNull('latitude'))
                ->get();

            foreach ($addressesInPostcode as $address) {
                $coords = static::matchAddress($address, $osResults);
                if ($coords) {
                    $address->update(['latitude' => $coords['lat'], 'longitude' => $coords['lng']]);
                    $precise++;
                } else {
                    $fallbackNeeded[] = $postcode;
                    break; // whole postcode needs centroid fallback
                }
            }
        }

        // Batch centroid fallback via postcodes.io
        $fallbackPostcodes = array_unique($fallbackNeeded);
        foreach (array_chunk($fallbackPostcodes, 100) as $batch) {
            $results = static::lookupPostcodes(array_values($batch));
            foreach ($results as $postcode => $coords) {
                if (!$coords) {
                    $failed++;
                    continue;
                }
                Address::where('postcode', $postcode)
                    ->when($this->option('ward'), fn($q) => $q->where('ward_id', $this->option('ward')))
                    ->whereNull('latitude')
                    ->update(['latitude' => $coords['lat'], 'longitude' => $coords['lng']]);
                $centroid++;
            }
        }

        $bar->finish();
        $this->newLine();

        if ($apiKey) {
            $this->info("Done. {$precise} addresses matched precisely, {$centroid} used postcode centroid, {$failed} failed.");
        } else {
            $this->info("Done. {$centroid} postcodes geocoded (centroid), {$failed} failed.");
        }

        return self::SUCCESS;
    }

    public static function fetchOsPlaces(string $postcode, string $apiKey): array
    {
        try {
            $response = Http::timeout(10)->get('https://api.os.uk/search/places/v1/postcode', [
                'postcode'   => $postcode,
                'key'        => $apiKey,
                'maxresults' => 100,
                'output_srs' => 'WGS84',
            ]);

            return $response->successful() ? $response->json('results', []) : [];
        } catch (\Exception) {
            return [];
        }
    }

    public static function matchAddress(Address $address, array $osResults): ?array
    {
        if (empty($osResults)) {
            return null;
        }

        $houseUpper = strtoupper(trim($address->house_number));

        // Extract leading number/letter combo ("12A", "14", etc.)
        preg_match('/^(\d+[A-Z]?)/', $houseUpper, $m);
        $numericPart = $m[1] ?? null;

        $candidates = [];

        foreach ($osResults as $result) {
            $dpa = $result['DPA'] ?? [];
            if (empty($dpa['LATITUDE'])) {
                continue;
            }

            $bn    = strtoupper(trim($dpa['BUILDING_NUMBER'] ?? ''));
            $bname = strtoupper(trim($dpa['BUILDING_NAME'] ?? ''));

            if ($numericPart && $bn === $numericPart) {
                $candidates[] = $dpa;
            } elseif ($bname && str_contains($houseUpper, $bname)) {
                $candidates[] = $dpa;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Average coordinates — handles multiple flats at the same building number
        return [
            'lat' => array_sum(array_column($candidates, 'LATITUDE')) / count($candidates),
            'lng' => array_sum(array_column($candidates, 'LONGITUDE')) / count($candidates),
        ];
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
                $postcode           = $item['query'];
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
