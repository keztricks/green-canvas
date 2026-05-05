<?php

namespace App\Console\Commands;

use App\Models\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeocodeViaUprn extends Command
{
    protected $signature = 'addresses:geocode-uprn
        {--mapping= : Path to council CSV mapping addresses → UPRNs (e.g. Calderdale FOI release)}
        {--coords= : Path to OS Open UPRN CSV (UPRN → lat/lng, ~2GB)}
        {--ward= : Only process this ward ID}
        {--force : Re-geocode addresses already marked precise}
        {--phase=both : "match" (just match UPRNs), "lookup" (just look up coords), or "both"}';

    protected $description = 'Geocode addresses to property level via council UPRN mapping + OS Open UPRN coordinates';

    public function handle(): int
    {
        $phase = $this->option('phase');

        if (in_array($phase, ['match', 'both'])) {
            if (!$this->option('mapping')) {
                $this->error('--mapping is required for match phase');
                return self::FAILURE;
            }
            $this->matchAddressesToUprns($this->option('mapping'));
        }

        if (in_array($phase, ['lookup', 'both'])) {
            if (!$this->option('coords')) {
                $this->error('--coords is required for lookup phase');
                return self::FAILURE;
            }
            $this->lookupCoordinatesFromOsFile($this->option('coords'));
        }

        return self::SUCCESS;
    }

    private function matchAddressesToUprns(string $mappingPath): void
    {
        if (!file_exists($mappingPath)) {
            $this->error("Mapping file not found: {$mappingPath}");
            return;
        }

        $this->info("Building UPRN lookup from {$mappingPath}...");

        // Read council CSV: postcode → list of [normalized_address, uprn]
        $lookup = [];
        $handle = fopen($mappingPath, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');
        $cols = array_flip(array_map(fn($h) => strtolower(trim($h)), $header));

        $uprnCol     = $cols['llpg uprn']   ?? $cols['uprn']     ?? 0;
        $postcodeCol = $cols['postcode']    ?? count($header) - 1;
        $addr1Col    = $cols['add line 1']  ?? null;
        $addr2Col    = $cols['add line 2']  ?? null;
        $addr3Col    = $cols['add line 3']  ?? null;
        $addr4Col    = $cols['add line 4']  ?? null;

        $rows = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows++;
            $pc = $this->normPostcode($row[$postcodeCol] ?? '');
            $rawUprn = trim($row[$uprnCol] ?? '');
            // Skip Excel-mangled UPRNs (lost precision in scientific notation)
            if ($rawUprn === '' || str_contains($rawUprn, 'E+') || str_contains($rawUprn, '.')) {
                $skipped++;
                continue;
            }
            $uprn = $this->normUprn($rawUprn);
            if (!$pc || !$uprn) continue;

            $combined = trim(
                ($addr1Col !== null ? ($row[$addr1Col] ?? '') . ' ' : '') .
                ($addr2Col !== null ? ($row[$addr2Col] ?? '') . ' ' : '') .
                ($addr3Col !== null ? ($row[$addr3Col] ?? '') . ' ' : '') .
                ($addr4Col !== null ? ($row[$addr4Col] ?? '') : '')
            );
            $tokens = $this->filterNoise($this->tokenSet($this->normAddr($combined)));

            $lookup[$pc][] = ['tokens' => $tokens, 'tokenCount' => count($tokens), 'uprn' => $uprn];
        }
        fclose($handle);
        $msg = "  {$rows} council records loaded across " . count($lookup) . " postcodes.";
        if ($skipped > 0) $msg .= " (skipped {$skipped} rows with malformed UPRNs)";
        $this->info($msg);

        // Match each Address against its postcode bucket
        $query = Address::query()->whereNotNull('postcode')->where('postcode', '!=', '');
        if ($wardId = $this->option('ward')) $query->where('ward_id', $wardId);
        if (!$this->option('force')) $query->whereNull('uprn');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No addresses to match.');
            return;
        }

        $this->info("Matching {$total} addresses to UPRNs...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $matched = 0;
        $unmatched = 0;
        $verbose = $this->output->isVerbose();

        $query->chunkById(500, function ($chunk) use (&$lookup, &$matched, &$unmatched, $bar, $verbose) {
            DB::transaction(function () use ($chunk, &$lookup, &$matched, &$unmatched, $bar, $verbose) {
                foreach ($chunk as $address) {
                    $bar->advance();
                    $pc = $this->normPostcode($address->postcode);
                    if (!isset($lookup[$pc])) { $unmatched++; continue; }

                    $needle = $this->filterNoise($this->tokenSet($this->normAddr($address->house_number . ' ' . $address->street_name)));
                    $bestUprn = $this->bestUprnMatch($needle, $lookup[$pc]);

                    if ($bestUprn) {
                        $address->update(['uprn' => $bestUprn]);
                        $matched++;
                        if ($verbose) $this->line("  <fg=green>✓</> {$address->house_number} {$address->street_name}, {$address->postcode}  →  UPRN {$bestUprn}");
                    } else {
                        $unmatched++;
                        if ($verbose) $this->line("  <fg=red>✗</> {$address->house_number} {$address->street_name}, {$address->postcode}  (no UPRN match in postcode)");
                    }
                }
            });
        });

        $bar->finish();
        $this->newLine();
        $this->info("Matched {$matched}, unmatched {$unmatched}.");
    }

    private function bestUprnMatch(array $needleTokens, array $candidates): ?string
    {
        if (empty($needleTokens)) return null;

        $bestUprn = null;
        $bestExtras = PHP_INT_MAX;

        foreach ($candidates as $cand) {
            // All needle tokens must appear as exact words in the candidate
            foreach ($needleTokens as $t => $_) {
                if (!isset($cand['tokens'][$t])) continue 2;
            }
            $extras = $cand['tokenCount'] - count($needleTokens);
            if ($extras < $bestExtras) {
                $bestExtras = $extras;
                $bestUprn = $cand['uprn'];
                if ($extras === 0) break;
            }
        }

        return $bestUprn;
    }

    private function tokenSet(string $s): array
    {
        $tokens = preg_split('/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        return array_flip($tokens);
    }

    private function filterNoise(array $tokens): array
    {
        // Drop tokens that are postcode fragments or unambiguous town names —
        // they're not address-distinguishing and create false extras when comparing.
        static $noiseSet = [
            'halifax' => true, 'bradford' => true, 'todmorden' => true,
            'hebden' => true, 'mytholmroyd' => true, 'elland' => true,
            'brighouse' => true, 'queensbury' => true, 'copley' => true,
            'wainstalls' => true, 'midgley' => true, 'charlestown' => true,
        ];

        $out = [];
        foreach ($tokens as $t => $_) {
            if (isset($noiseSet[$t])) {
                continue;
            }
            // Postcode outward (e.g. "hx1", "bd13") — letters then digits
            if (preg_match('/^[a-z]{1,2}\d+[a-z]?$/', $t)) {
                continue;
            }
            // Postcode inward (e.g. "1aa", "2qx") — digit then letters
            if (preg_match('/^\d[a-z]{2}$/', $t)) {
                continue;
            }
            $out[$t] = true;
        }
        return $out;
    }

    private function lookupCoordinatesFromOsFile(string $osPath): void
    {
        if (!file_exists($osPath)) {
            $this->error("OS UPRN file not found: {$osPath}");
            return;
        }

        // Build set of UPRNs we want to look up
        $query = Address::query()->whereNotNull('uprn');
        if ($wardId = $this->option('ward')) $query->where('ward_id', $wardId);
        if (!$this->option('force')) $query->where('precise_position', false);

        $wanted = $query->pluck('uprn')->unique()->values();

        if ($wanted->isEmpty()) {
            $this->info('No UPRNs awaiting coordinates.');
            return;
        }

        $wantedSet = array_flip($wanted->all());
        $this->info("Streaming OS UPRN file for " . count($wantedSet) . " UPRNs (~2GB, this can take a few minutes)...");

        $handle = fopen($osPath, 'r');
        $header = fgets($handle);
        // Strip BOM if present
        if (str_starts_with($header, "\xEF\xBB\xBF")) $header = substr($header, 3);
        $headerCols = array_map(fn($c) => strtolower(trim($c)), explode(',', trim($header)));
        $idxUprn = array_search('uprn', $headerCols);
        $idxLat  = array_search('latitude', $headerCols);
        $idxLng  = array_search('longitude', $headerCols);
        if ($idxUprn === false || $idxLat === false || $idxLng === false) {
            $this->error("OS file missing expected columns (UPRN/LATITUDE/LONGITUDE). Got: " . implode(',', $headerCols));
            fclose($handle);
            return;
        }

        $coords = [];
        $linesRead = 0;
        $remaining = count($wantedSet);

        while (($line = fgets($handle)) !== false) {
            $linesRead++;
            if ($linesRead % 5_000_000 === 0) {
                $this->line("  ...{$linesRead} lines scanned, {$remaining} UPRNs still to find");
            }

            $cols = explode(',', $line);
            $uprn = trim($cols[$idxUprn]);
            if (!isset($wantedSet[$uprn])) continue;

            $coords[$uprn] = [
                'lat' => (float) $cols[$idxLat],
                'lng' => (float) $cols[$idxLng],
            ];
            unset($wantedSet[$uprn]);
            $remaining--;
            if ($remaining === 0) break; // done early
        }
        fclose($handle);

        $this->info("  Found coordinates for " . count($coords) . " UPRNs after scanning {$linesRead} lines.");

        // Bulk update
        $this->info('Updating addresses...');
        $bar = $this->output->createProgressBar(count($coords));
        $bar->start();

        DB::transaction(function () use ($coords, $bar) {
            foreach ($coords as $uprn => $c) {
                Address::where('uprn', $uprn)->update([
                    'latitude'         => $c['lat'],
                    'longitude'        => $c['lng'],
                    'precise_position' => true,
                ]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');
    }

    private function normUprn(string $uprn): string
    {
        // OS Open UPRN stores UPRNs without leading zeros; council CSVs sometimes pad them.
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
