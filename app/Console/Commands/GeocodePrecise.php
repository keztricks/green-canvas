<?php

namespace App\Console\Commands;

use App\Models\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodePrecise extends Command
{
    protected $signature = 'addresses:geocode-precise {--ward= : Only process this ward ID} {--limit=0 : Maximum addresses to process per run (0 = all)} {--force : Re-geocode addresses already marked precise} {--throttle=1100 : Milliseconds between requests} {--min-rank=28 : Reject Nominatim results with place_rank below this (28 = house-level, 26 = street)}';
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

        $verbose = $this->output->isVerbose();
        $minRank = (int) $this->option('min-rank');
        $eta = gmdate('H:i:s', (int) ceil($ids->count() * $throttleMs / 1000));
        $this->info("Geocoding {$ids->count()} addresses via Nominatim (~1 req/sec, min place_rank {$minRank}, ETA {$eta}). Ctrl-C to stop.");

        $bar = $verbose ? null : $this->output->createProgressBar($ids->count());
        if ($bar) $bar->start();

        $found = 0;
        $rejected = 0;
        $missing = 0;

        foreach ($ids as $id) {
            $address = Address::find($id);
            if ($bar) $bar->advance();
            if (!$address) continue;

            $label = trim($address->house_number . ' ' . $address->street_name) . ', ' . $address->postcode;
            $result = $this->lookup($address, $userAgent, $minRank);

            if ($result['coords']) {
                $address->update([
                    'latitude'         => $result['coords']['lat'],
                    'longitude'        => $result['coords']['lng'],
                    'precise_position' => true,
                ]);
                $found++;
                if ($verbose) $this->line("  <fg=green>✓</> {$label}  →  rank {$result['rank']}, {$result['coords']['lat']}, {$result['coords']['lng']}");
            } elseif ($result['rank'] !== null) {
                $rejected++;
                if ($verbose) $this->line("  <fg=yellow>~</> {$label}  (best match rank {$result['rank']} below min {$minRank} — skipped)");
            } else {
                $missing++;
                if ($verbose) $this->line("  <fg=red>✗</> {$label}  (no match)");
            }

            usleep($throttleMs * 1000);
        }

        if ($bar) { $bar->finish(); $this->newLine(); }
        $this->info("Done. {$found} matched, {$rejected} rejected (too coarse), {$missing} not found.");

        return self::SUCCESS;
    }

    private function lookup(Address $address, string $userAgent, int $minRank): array
    {
        $miss = ['coords' => null, 'rank' => null];
        try {
            $response = Http::withHeaders(['User-Agent' => $userAgent])
                ->timeout(15)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'street'         => trim($address->house_number . ' ' . $address->street_name),
                    'postalcode'     => $address->postcode,
                    'country'        => 'United Kingdom',
                    'format'         => 'json',
                    'addressdetails' => 1,
                    'limit'          => 5,
                ]);

            if (!$response->successful()) return $miss;
            $results = $response->json();
            if (empty($results)) return $miss;

            // Pick the highest-precision result (place_rank: 30 = building, 26 = street, 21 = postcode)
            $best = null;
            foreach ($results as $r) {
                $rank = (int) ($r['place_rank'] ?? 0);
                if (!isset($r['lat'], $r['lon'])) continue;
                if ($best === null || $rank > $best['rank']) {
                    $best = ['rank' => $rank, 'lat' => $r['lat'], 'lon' => $r['lon']];
                }
            }
            if (!$best) return $miss;
            if ($best['rank'] < $minRank) return ['coords' => null, 'rank' => $best['rank']];

            return [
                'coords' => ['lat' => (float) $best['lat'], 'lng' => (float) $best['lon']],
                'rank'   => $best['rank'],
            ];
        } catch (\Exception) {
            return $miss;
        }
    }
}
