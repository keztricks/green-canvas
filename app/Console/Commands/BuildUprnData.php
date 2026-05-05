<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BuildUprnData extends Command
{
    protected $signature = 'addresses:build-uprn-data
        {--council= : Path to council CSV (e.g. Calderdale FOI release with UPRN + address fields)}
        {--os-uprn= : Path to OS Open UPRN CSV (UPRN → lat/lng, the full ~2GB file)}
        {--output= : Path to write the merged CSV}';

    protected $description = 'Merge a council UPRN/address CSV with the OS Open UPRN coordinate file into a single combined dataset';

    public function handle(): int
    {
        $councilPath = $this->option('council');
        $osPath      = $this->option('os-uprn');
        $outputPath  = $this->option('output');

        if (!$councilPath || !$osPath || !$outputPath) {
            $this->error('--council, --os-uprn and --output are all required.');
            return self::FAILURE;
        }
        if (!file_exists($councilPath)) { $this->error("Council file not found: {$councilPath}"); return self::FAILURE; }
        if (!file_exists($osPath))      { $this->error("OS Open UPRN file not found: {$osPath}"); return self::FAILURE; }

        // Phase 1: load council CSV → in-memory map of UPRN → row data
        $this->info("Loading council CSV {$councilPath}...");
        $councilByUprn = [];
        $skipped = 0;

        $h = fopen($councilPath, 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        $cols = array_flip(array_map(fn($c) => strtolower(trim($c)), $header));
        $uprnCol     = $cols['llpg uprn']  ?? $cols['uprn']     ?? 0;
        $postcodeCol = $cols['postcode']   ?? null;
        $addr1Col    = $cols['add line 1'] ?? null;
        $addr2Col    = $cols['add line 2'] ?? null;
        $addr3Col    = $cols['add line 3'] ?? null;
        $addr4Col    = $cols['add line 4'] ?? null;

        while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $rawUprn = trim($row[$uprnCol] ?? '');
            // Skip Excel-mangled UPRNs (precision lost via scientific notation)
            if ($rawUprn === '' || str_contains($rawUprn, 'E+') || str_contains($rawUprn, '.')) {
                $skipped++;
                continue;
            }
            $uprn = ltrim($rawUprn, '0');
            if ($uprn === '') continue;

            $councilByUprn[$uprn] = [
                'add1'     => $addr1Col !== null ? trim($row[$addr1Col] ?? '') : '',
                'add2'     => $addr2Col !== null ? trim($row[$addr2Col] ?? '') : '',
                'add3'     => $addr3Col !== null ? trim($row[$addr3Col] ?? '') : '',
                'add4'     => $addr4Col !== null ? trim($row[$addr4Col] ?? '') : '',
                'postcode' => $postcodeCol !== null ? trim($row[$postcodeCol] ?? '') : '',
            ];
        }
        fclose($h);

        $message = '  ' . count($councilByUprn) . ' council UPRNs loaded.';
        if ($skipped > 0) $message .= " ({$skipped} skipped — malformed UPRN)";
        $this->info($message);

        // Phase 2: stream the OS Open UPRN file, write merged rows for matched UPRNs
        $this->info("Streaming OS Open UPRN file {$osPath}...");

        $in  = fopen($osPath, 'r');
        $headerLine = fgets($in);
        if (str_starts_with($headerLine, "\xEF\xBB\xBF")) $headerLine = substr($headerLine, 3);
        $osHeaderCols = array_map(fn($c) => strtolower(trim($c)), explode(',', trim($headerLine)));
        $idxUprn = array_search('uprn', $osHeaderCols);
        $idxLat  = array_search('latitude', $osHeaderCols);
        $idxLng  = array_search('longitude', $osHeaderCols);
        if ($idxUprn === false || $idxLat === false || $idxLng === false) {
            $this->error("OS file missing expected columns (UPRN/LATITUDE/LONGITUDE). Got: " . implode(',', $osHeaderCols));
            fclose($in);
            return self::FAILURE;
        }

        @mkdir(dirname($outputPath), 0775, true);
        $out = fopen($outputPath, 'w');
        fwrite($out, "UPRN,Add Line 1,Add Line 2,Add Line 3,Add Line 4,Postcode,Latitude,Longitude\n");

        $linesRead = 0;
        $matched   = 0;
        $remaining = count($councilByUprn);

        while (($line = fgets($in)) !== false) {
            $linesRead++;
            if ($linesRead % 5_000_000 === 0) {
                $this->line("  ...{$linesRead} lines scanned, {$matched} matched, {$remaining} UPRNs still to find");
            }

            $cells = explode(',', $line);
            $uprn  = trim($cells[$idxUprn]);
            if (!isset($councilByUprn[$uprn])) continue;

            $row = $councilByUprn[$uprn];
            fputcsv($out, [
                $uprn,
                $row['add1'], $row['add2'], $row['add3'], $row['add4'],
                $row['postcode'],
                trim($cells[$idxLat]),
                trim($cells[$idxLng]),
            ], ',', '"', '');

            unset($councilByUprn[$uprn]);
            $matched++;
            $remaining--;
            if ($remaining === 0) break;
        }

        fclose($in);
        fclose($out);

        $this->info("Done. {$matched} merged rows written to {$outputPath} (scanned {$linesRead} OS lines).");
        if ($remaining > 0) {
            $this->warn("  {$remaining} council UPRNs not found in the OS dataset (likely new builds or recently de-issued).");
        }
        return self::SUCCESS;
    }
}
