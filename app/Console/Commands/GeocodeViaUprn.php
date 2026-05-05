<?php

namespace App\Console\Commands;

use App\Models\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeocodeViaUprn extends Command
{
    protected $signature = 'addresses:geocode-uprn
        {--data= : Path to a merged UPRN data CSV (built by addresses:build-uprn-data)}
        {--ward= : Only process this ward ID}
        {--force : Re-geocode addresses already marked precise}';

    protected $description = 'Geocode addresses to property level using a merged UPRN/coordinate CSV';

    public function handle(): int
    {
        $dataPath = $this->option('data');
        if (!$dataPath) {
            $this->error('--data is required (use addresses:build-uprn-data to produce it)');
            return self::FAILURE;
        }
        if (!file_exists($dataPath)) {
            $this->error("Data file not found: {$dataPath}");
            return self::FAILURE;
        }

        // Load merged CSV: postcode → list of [tokens, uprn, lat, lng]
        $this->info("Loading merged UPRN data from {$dataPath}...");
        $lookup = [];
        $rows = 0;

        $h = fopen($dataPath, 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        $cols = array_flip(array_map(fn($c) => strtolower(trim($c)), $header));
        $uprnCol     = $cols['uprn']         ?? 0;
        $addr1Col    = $cols['add line 1']   ?? 1;
        $addr2Col    = $cols['add line 2']   ?? 2;
        $addr3Col    = $cols['add line 3']   ?? 3;
        $addr4Col    = $cols['add line 4']   ?? 4;
        $postcodeCol = $cols['postcode']     ?? 5;
        $latCol      = $cols['latitude']     ?? 6;
        $lngCol      = $cols['longitude']    ?? 7;

        while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $pc   = $this->normPostcode($row[$postcodeCol] ?? '');
            $uprn = $this->normUprn($row[$uprnCol] ?? '');
            if (!$pc || !$uprn) continue;

            $combined = trim(
                ($row[$addr1Col] ?? '') . ' ' .
                ($row[$addr2Col] ?? '') . ' ' .
                ($row[$addr3Col] ?? '') . ' ' .
                ($row[$addr4Col] ?? '')
            );
            $tokens = $this->filterNoise($this->tokenSet($this->normAddr($combined)));

            $lookup[$pc][] = [
                'tokens'     => $tokens,
                'tokenCount' => count($tokens),
                'uprn'       => $uprn,
                'lat'        => (float) ($row[$latCol] ?? 0),
                'lng'        => (float) ($row[$lngCol] ?? 0),
            ];
            $rows++;
        }
        fclose($h);

        $this->info("  {$rows} merged records loaded across " . count($lookup) . " postcodes.");

        // Match addresses against postcode buckets, write coords directly.
        $query = Address::query()->whereNotNull('postcode')->where('postcode', '!=', '');
        if ($wardId = $this->option('ward')) $query->where('ward_id', $wardId);
        if (!$this->option('force')) $query->where('precise_position', false);

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No addresses to geocode.');
            return self::SUCCESS;
        }

        $this->info("Geocoding {$total} addresses...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $matched   = 0;
        $unmatched = 0;
        $verbose   = $this->output->isVerbose();

        $query->chunkById(500, function ($chunk) use (&$lookup, &$matched, &$unmatched, $bar, $verbose) {
            DB::transaction(function () use ($chunk, &$lookup, &$matched, &$unmatched, $bar, $verbose) {
                foreach ($chunk as $address) {
                    $bar->advance();
                    $pc = $this->normPostcode($address->postcode);
                    if (!isset($lookup[$pc])) { $unmatched++; continue; }

                    $needle = $this->filterNoise($this->tokenSet($this->normAddr(
                        $address->house_number . ' ' . $address->street_name
                    )));
                    $best = $this->bestMatch($needle, $lookup[$pc]);

                    if ($best) {
                        $address->update([
                            'uprn'             => $best['uprn'],
                            'latitude'         => $best['lat'],
                            'longitude'        => $best['lng'],
                            'precise_position' => true,
                        ]);
                        $matched++;
                        if ($verbose) {
                            $this->line("  <fg=green>✓</> {$address->house_number} {$address->street_name}, {$address->postcode}  →  UPRN {$best['uprn']}");
                        }
                    } else {
                        $unmatched++;
                        if ($verbose) {
                            $this->line("  <fg=red>✗</> {$address->house_number} {$address->street_name}, {$address->postcode}  (no match)");
                        }
                    }
                }
            });
        });

        $bar->finish();
        $this->newLine();
        $this->info("Matched {$matched}, unmatched {$unmatched}.");

        return self::SUCCESS;
    }

    private function bestMatch(array $needleTokens, array $candidates): ?array
    {
        if (empty($needleTokens)) return null;

        $best = null;
        $bestExtras = PHP_INT_MAX;

        foreach ($candidates as $cand) {
            foreach ($needleTokens as $t => $_) {
                if (!isset($cand['tokens'][$t])) continue 2;
            }
            $extras = $cand['tokenCount'] - count($needleTokens);
            if ($extras < $bestExtras) {
                $bestExtras = $extras;
                $best = $cand;
                if ($extras === 0) break;
            }
        }

        return $best;
    }

    private function tokenSet(string $s): array
    {
        $tokens = preg_split('/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        return array_flip($tokens);
    }

    private function filterNoise(array $tokens): array
    {
        // Drop unambiguous town names and postcode-shaped tokens — they don't
        // distinguish between candidates and create false "extra token" weight.
        static $noiseSet = [
            'halifax' => true, 'bradford' => true, 'todmorden' => true,
            'hebden' => true, 'mytholmroyd' => true, 'elland' => true,
            'brighouse' => true, 'queensbury' => true, 'copley' => true,
            'wainstalls' => true, 'midgley' => true, 'charlestown' => true,
        ];

        $out = [];
        foreach ($tokens as $t => $_) {
            if (isset($noiseSet[$t])) continue;
            if (preg_match('/^[a-z]{1,2}\d+[a-z]?$/', $t)) continue; // postcode outward
            if (preg_match('/^\d[a-z]{2}$/', $t)) continue;          // postcode inward
            $out[$t] = true;
        }
        return $out;
    }

    private function normUprn(string $uprn): string
    {
        $uprn = ltrim(trim($uprn), '0');
        return $uprn === '' ? '0' : $uprn;
    }

    private function normPostcode(string $pc): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($pc)));
    }

    private function normAddr(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[,\.]+/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}
