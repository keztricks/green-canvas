<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExtractUprnSubset extends Command
{
    protected $signature = 'addresses:extract-uprn-subset
        {--mapping= : Path to council CSV that lists the UPRNs you care about}
        {--coords= : Path to full OS Open UPRN CSV (~2GB)}
        {--output= : Path to write the trimmed CSV}';

    protected $description = 'Build a small UPRN→coords CSV containing only the UPRNs from a council mapping file (e.g. one council area)';

    public function handle(): int
    {
        $mapping = $this->option('mapping');
        $coords  = $this->option('coords');
        $output  = $this->option('output');
        if (!$mapping || !$coords || !$output) {
            $this->error('--mapping, --coords and --output are all required.');
            return self::FAILURE;
        }
        if (!file_exists($mapping)) { $this->error("Mapping file not found: {$mapping}"); return self::FAILURE; }
        if (!file_exists($coords))  { $this->error("Coords file not found: {$coords}"); return self::FAILURE; }

        // Phase 1: collect UPRNs we want from the council CSV
        $this->info("Collecting UPRNs from {$mapping}...");
        $wanted = [];
        $h = fopen($mapping, 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        $cols = array_flip(array_map(fn($c) => strtolower(trim($c)), $header));
        $uprnCol = $cols['llpg uprn'] ?? $cols['uprn'] ?? 0;

        while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $raw = trim($row[$uprnCol] ?? '');
            if ($raw === '' || str_contains($raw, 'E+') || str_contains($raw, '.')) continue;
            $uprn = ltrim($raw, '0');
            if ($uprn !== '') $wanted[$uprn] = true;
        }
        fclose($h);
        $this->info('  ' . count($wanted) . ' unique UPRNs collected.');

        // Phase 2: stream the OS coords file, copy matching rows
        $this->info("Streaming {$coords}...");
        $in  = fopen($coords, 'r');
        $out = fopen($output, 'w');

        $headerLine = fgets($in);
        // Strip BOM if present
        if (str_starts_with($headerLine, "\xEF\xBB\xBF")) $headerLine = substr($headerLine, 3);
        fwrite($out, $headerLine);
        $headerCols = array_map(fn($c) => strtolower(trim($c)), explode(',', trim($headerLine)));
        $idxUprn = array_search('uprn', $headerCols);
        if ($idxUprn === false) {
            $this->error('OS file header missing UPRN column.');
            fclose($in); fclose($out);
            return self::FAILURE;
        }

        $linesRead = 0;
        $matched = 0;
        $remaining = count($wanted);
        while (($line = fgets($in)) !== false) {
            $linesRead++;
            if ($linesRead % 5_000_000 === 0) {
                $this->line("  ...{$linesRead} lines scanned, {$matched} matched, {$remaining} UPRNs still to find");
            }
            $cols = explode(',', $line);
            $uprn = trim($cols[$idxUprn]);
            if (!isset($wanted[$uprn])) continue;
            fwrite($out, $line);
            $matched++;
            $remaining--;
            unset($wanted[$uprn]);
            if ($remaining === 0) break;
        }
        fclose($in);
        fclose($out);

        $this->info("Done. Wrote {$matched} rows to {$output}. Scanned {$linesRead} OS lines.");
        if ($remaining > 0) {
            $this->warn("  {$remaining} UPRNs from the council file weren't in the OS dataset.");
        }
        return self::SUCCESS;
    }
}
