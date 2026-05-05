<?php

namespace App\Console\Commands;

use App\Models\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodePrecise extends Command
{
    protected $signature = 'addresses:geocode-precise {--ward= : Only process this ward ID} {--limit=0 : Maximum addresses to process per run (0 = all)} {--force : Re-geocode addresses already marked precise} {--throttle=1100 : Milliseconds between requests}';
    protected $description = 'Geocode addresses to property level via Nominatim (slow, throttled to ~1 req/sec)';

    public function handle(): int
    {
        $userAgent = config('app.name', 'GreenCanvas') . ' canvassing geocoder (' . config('app.url', 'localhost') . ')';
        $throttleMs = max(1100, (int) $this->option('throttle'));

        $query = Address::query()
            ->whereNotNull('postcode')
            ->where('postcode', '!=', '');

        if ($wardId = $this->option('ward')) {
            $query->where('ward_id', $wardId);
        }
        if (!$this->option('force')) {
            $query->where('precise_position', false);
        }

        $ids = $query->orderBy('id')->pluck('id');
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $ids = $ids->take($limit);
        }

        if ($ids->isEmpty()) {
            $this->info('No addresses to geocode.');
            return self::SUCCESS;
        }

        $eta = gmdate('H:i:s', (int) ceil($ids->count() * $throttleMs / 1000));
        $this->info("Geocoding {$ids->count()} addresses via Nominatim (~1 req/sec, ETA {$eta}). Ctrl-C to stop.");
        $bar = $this->output->createProgressBar($ids->count());
        $bar->start();

        $found = 0;
        $missing = 0;

        foreach ($ids as $id) {
            $address = Address::find($id);
            $bar->advance();
            if (!$address) continue;

            $coords = $this->lookup($address, $userAgent);
            if ($coords) {
                $address->update([
                    'latitude'         => $coords['lat'],
                    'longitude'        => $coords['lng'],
                    'precise_position' => true,
                ]);
                $found++;
            } else {
                $missing++;
            }

            usleep($throttleMs * 1000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$found} matched precisely, {$missing} not found (will retry next run).");

        return self::SUCCESS;
    }

    private function lookup(Address $address, string $userAgent): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $userAgent])
                ->timeout(15)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'street'     => trim($address->house_number . ' ' . $address->street_name),
                    'postalcode' => $address->postcode,
                    'country'    => 'United Kingdom',
                    'format'     => 'json',
                    'limit'      => 1,
                ]);

            if (!$response->successful()) return null;
            $results = $response->json();
            if (empty($results) || !isset($results[0]['lat'], $results[0]['lon'])) return null;

            return ['lat' => (float) $results[0]['lat'], 'lng' => (float) $results[0]['lon']];
        } catch (\Exception) {
            return null;
        }
    }
}
