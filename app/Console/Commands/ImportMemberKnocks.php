<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\KnockResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ImportMemberKnocks extends Command
{
    protected $signature = 'canvassing:import-member-knocks
        {csv : Path to a CSV (e.g. NationBuilder/CAN2 export)}
        {--user= : User ID to attribute the knocks to (required)}
        {--note=They are a Green Party member. : Note recorded on each knock}
        {--response=green : KnockResult.response value}
        {--vote-likelihood=1 : KnockResult.vote_likelihood (1 = definitely green, 5 = never)}
        {--knocked-at= : Timestamp for the knock (default: now)}
        {--address-col=can2_user_address : CSV column name for the full address line}
        {--postcode-col=zip_code : CSV column name for the postcode}
        {--skip-if-knocked : Skip addresses that already have any knock result}
        {--unmatched-out= : Write unmatched/ambiguous rows to this CSV path}
        {--matched-out= : Write matched rows (with address_id, knock_result_id) to this CSV path}
        {--interactive : When no/ambiguous address match in a postcode, prompt to pick a candidate}
        {--dry-run : Match only — do not write any KnockResult rows}';

    protected $description = 'Match a member address list against the address book and record a knock for each match.';

    public function handle(): int
    {
        $config = $this->buildConfig();
        if ($config === null) {
            return self::FAILURE;
        }

        $csv = $this->openCsv($config['csv_path'], [$config['address_col'], $config['postcode_col']]);
        if ($csv === null) {
            return self::FAILURE;
        }
        [$fh, $ai, $pi] = $csv;

        $outputs = $this->openOutputs();
        $stats = $this->newStats();
        $byPostcode = [];

        $rowNum = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $rowNum++;
            $stats['rows']++;
            $this->processRow($rowNum, $row[$ai] ?? '', $row[$pi] ?? '', $config, $stats, $byPostcode, $outputs);
        }

        fclose($fh);
        $this->closeOutputs($outputs);

        $this->newLine();
        $this->info($config['dry_run'] ? 'Dry run complete.' : 'Import complete.');
        $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }

    private function buildConfig(): ?array
    {
        $userId = $this->option('user');
        if (! $userId) {
            $this->error('--user=ID is required.');

            return null;
        }
        $user = User::find($userId);
        if (! $user) {
            $this->error("User {$userId} not found.");

            return null;
        }

        $csvPath = $this->argument('csv');
        if (! is_file($csvPath) || ! is_readable($csvPath)) {
            $this->error("CSV not readable: {$csvPath}");

            return null;
        }

        $response = $this->option('response');
        if (! in_array($response, array_keys(KnockResult::responseOptions()), true)) {
            $this->error("Invalid response '{$response}'.");

            return null;
        }

        $voteLikelihood = $this->parseVoteLikelihood($this->option('vote-likelihood'));
        if ($voteLikelihood === false) {
            $this->error('--vote-likelihood must be an integer 1-5 (or empty).');

            return null;
        }

        return [
            'csv_path' => $csvPath,
            'user' => $user,
            'response' => $response,
            'vote_likelihood' => $voteLikelihood,
            'knocked_at' => $this->option('knocked-at') ? Carbon::parse($this->option('knocked-at')) : Carbon::now(),
            'note' => $this->option('note'),
            'dry_run' => (bool) $this->option('dry-run'),
            'skip_if_knocked' => (bool) $this->option('skip-if-knocked'),
            'interactive' => (bool) $this->option('interactive'),
            'address_col' => $this->option('address-col'),
            'postcode_col' => $this->option('postcode-col'),
        ];
    }

    private function parseVoteLikelihood($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! ctype_digit((string) $raw) || (int) $raw < 1 || (int) $raw > 5) {
            return false;
        }

        return (int) $raw;
    }

    private function openCsv(string $path, array $requiredCols): ?array
    {
        $fh = fopen($path, 'r');
        if (! $fh) {
            $this->error("Could not open {$path}");

            return null;
        }
        $header = fgetcsv($fh);
        if (! $header) {
            $this->error('CSV is empty or unreadable.');
            fclose($fh);

            return null;
        }
        $header = array_map(fn ($h) => trim((string) $h), $header);
        foreach ($requiredCols as $col) {
            if (! in_array($col, $header, true)) {
                $this->error("CSV missing required column '{$col}'. Found: ".implode(', ', $header));
                fclose($fh);

                return null;
            }
        }
        $idx = array_flip($header);

        return [$fh, $idx[$requiredCols[0]], $idx[$requiredCols[1]]];
    }

    private function openOutputs(): array
    {
        $matched = $this->option('matched-out') ? fopen($this->option('matched-out'), 'w') : null;
        if ($matched) {
            fputcsv($matched, ['row', 'csv_address', 'csv_postcode', 'address_id', 'matched_address', 'knock_result_id']);
        }
        $unmatched = $this->option('unmatched-out') ? fopen($this->option('unmatched-out'), 'w') : null;
        if ($unmatched) {
            fputcsv($unmatched, ['row', 'csv_address', 'csv_postcode', 'reason', 'candidate_address_ids']);
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    private function closeOutputs(array $outputs): void
    {
        foreach ($outputs as $fh) {
            if ($fh) {
                fclose($fh);
            }
        }
    }

    private function newStats(): array
    {
        return [
            'rows' => 0,
            'matched' => 0,
            'inserted' => 0,
            'skipped_already_knocked' => 0,
            'skipped_do_not_knock' => 0,
            'unmatched_no_postcode_match' => 0,
            'unmatched_no_address_match' => 0,
            'ambiguous' => 0,
            'invalid_row' => 0,
        ];
    }

    private function processRow(int $rowNum, string $rawAddress, string $rawPostcode, array $config, array &$stats, array &$byPostcode, array $outputs): void
    {
        $normPostcode = $this->normalisePostcode($rawPostcode);
        $normAddress = $this->normaliseAddress($rawAddress);

        if ($normPostcode === '' || $normAddress === '') {
            $stats['invalid_row']++;
            $this->writeUnmatched($outputs['unmatched'], $rowNum, $rawAddress, $rawPostcode, 'missing_field', []);

            return;
        }

        $candidates = $this->candidatesForPostcode($normPostcode, $byPostcode);
        if ($candidates->isEmpty()) {
            $stats['unmatched_no_postcode_match']++;
            $this->writeUnmatched($outputs['unmatched'], $rowNum, $rawAddress, $rawPostcode, 'postcode_not_in_db', []);

            return;
        }

        $matches = $this->matchAddress($candidates, $normAddress);

        if ($matches->isEmpty()) {
            $picked = $config['interactive']
                ? $this->promptCandidate($rowNum, $rawAddress, $rawPostcode, $candidates, 'No address match in this postcode.')
                : null;
            if ($picked === null) {
                $stats['unmatched_no_address_match']++;
                $this->writeUnmatched($outputs['unmatched'], $rowNum, $rawAddress, $rawPostcode, 'no_address_match_in_postcode', $candidates->pluck('id')->all());

                return;
            }
            $this->recordKnock($picked, $rowNum, $rawAddress, $rawPostcode, $config, $stats, $outputs);

            return;
        }
        if ($matches->count() > 1) {
            $picked = $config['interactive']
                ? $this->promptCandidate($rowNum, $rawAddress, $rawPostcode, $matches, 'Multiple address matches.')
                : null;
            if ($picked === null) {
                $stats['ambiguous']++;
                $this->writeUnmatched($outputs['unmatched'], $rowNum, $rawAddress, $rawPostcode, 'multiple_matches', $matches->pluck('id')->all());

                return;
            }
            $this->recordKnock($picked, $rowNum, $rawAddress, $rawPostcode, $config, $stats, $outputs);

            return;
        }

        $this->recordKnock($matches->first(), $rowNum, $rawAddress, $rawPostcode, $config, $stats, $outputs);
    }

    private function promptCandidate(int $rowNum, string $rawAddress, string $rawPostcode, Collection $candidates, string $reason): ?Address
    {
        $this->newLine();
        $this->warn("Row {$rowNum}: {$reason}");
        $this->line("  CSV: \"{$rawAddress}\" ({$rawPostcode})");
        $this->line('  Candidates:');
        $list = $candidates->values();
        foreach ($list as $i => $c) {
            $this->line(sprintf('    [%d] #%d  %s', $i + 1, $c->id, $c->full_address));
        }
        $this->line('    [0] skip (record as unmatched)');

        while (true) {
            $answer = $this->ask('Pick a number');
            if ($answer === null || $answer === '') {
                return null;
            }
            if (! ctype_digit((string) $answer)) {
                $this->line('  Enter a number.');

                continue;
            }
            $n = (int) $answer;
            if ($n === 0) {
                return null;
            }
            if ($n >= 1 && $n <= $list->count()) {
                return $list[$n - 1];
            }
            $this->line('  Out of range.');
        }
    }

    private function candidatesForPostcode(string $normPostcode, array &$byPostcode): Collection
    {
        if (! array_key_exists($normPostcode, $byPostcode)) {
            $byPostcode[$normPostcode] = Address::query()
                ->whereRaw("REPLACE(UPPER(postcode), ' ', '') = ?", [$normPostcode])
                ->get();
        }

        return $byPostcode[$normPostcode];
    }

    private function matchAddress(Collection $candidates, string $normAddress): Collection
    {
        // 1. Exact normalised equality on "house_number street_name".
        $exact = $candidates->filter(fn (Address $a) => $this->normaliseAddress(trim($a->house_number.' '.$a->street_name)) === $normAddress
        )->values();
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        // 2. Loose match: candidate is a prefix of the CSV line, or vice versa.
        //    Handles e.g. CSV "1 Fearnley Cottages, Turret Hall Road" vs DB "1" + "Fearnley Cottages",
        //    or CSV truncated to "1 Fearnley Cottages" vs DB "1 Fearnley Cottages" + "Turret Hall Road".
        return $candidates->filter(function (Address $a) use ($normAddress) {
            $candidate = $this->normaliseAddress(trim($a->house_number.' '.$a->street_name));
            if ($candidate === '') {
                return false;
            }

            return str_starts_with($normAddress, $candidate.' ')
                || str_starts_with($candidate, $normAddress.' ');
        })->values();
    }

    private function recordKnock(Address $address, int $rowNum, string $rawAddress, string $rawPostcode, array $config, array &$stats, array $outputs): void
    {
        $stats['matched']++;

        if ($address->do_not_knock) {
            $stats['skipped_do_not_knock']++;
            $this->writeUnmatched($outputs['unmatched'], $rowNum, $rawAddress, $rawPostcode, 'do_not_knock', [$address->id]);

            return;
        }
        if ($config['skip_if_knocked'] && $address->knockResults()->exists()) {
            $stats['skipped_already_knocked']++;
            $this->writeUnmatched($outputs['unmatched'], $rowNum, $rawAddress, $rawPostcode, 'already_knocked', [$address->id]);

            return;
        }

        if ($config['dry_run']) {
            $this->writeMatched($outputs['matched'], $rowNum, $rawAddress, $rawPostcode, $address, '(dry-run)');

            return;
        }

        $knock = KnockResult::create([
            'address_id' => $address->id,
            'user_id' => $config['user']->id,
            'response' => $config['response'],
            'vote_likelihood' => $config['vote_likelihood'],
            'turnout_likelihood' => null,
            'notes' => $config['note'],
            'knocked_at' => $config['knocked_at'],
        ]);
        $stats['inserted']++;
        $this->writeMatched($outputs['matched'], $rowNum, $rawAddress, $rawPostcode, $address, (string) $knock->id);
    }

    private function normalisePostcode(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($raw)) ?? '');
    }

    /**
     * Lowercase, strip punctuation, collapse whitespace, expand a few common UK street-type
     * abbreviations so "12 Smith St" matches "12 Smith Street".
     */
    private function normaliseAddress(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[.,]/', ' ', $s) ?? '';
        $s = preg_replace('/\s+/', ' ', $s) ?? '';
        $s = trim($s);

        $expansions = [
            ' st ' => ' street ',
            ' rd ' => ' road ',
            ' ave ' => ' avenue ',
            ' av ' => ' avenue ',
            ' dr ' => ' drive ',
            ' ln ' => ' lane ',
            ' cl ' => ' close ',
            ' ct ' => ' court ',
            ' cres ' => ' crescent ',
            ' pl ' => ' place ',
            ' ter ' => ' terrace ',
        ];
        $padded = ' '.$s.' ';
        foreach ($expansions as $from => $to) {
            $padded = str_replace($from, $to, $padded);
        }

        return trim($padded);
    }

    private function writeMatched($fh, int $rowNum, string $address, string $postcode, Address $a, string $knockId): void
    {
        if (! $fh) {
            return;
        }
        fputcsv($fh, [$rowNum, $address, $postcode, $a->id, $a->full_address, $knockId]);
    }

    private function writeUnmatched($fh, int $rowNum, string $address, string $postcode, string $reason, array $candidateIds): void
    {
        if (! $fh) {
            return;
        }
        fputcsv($fh, [$rowNum, $address, $postcode, $reason, implode('|', $candidateIds)]);
    }
}
