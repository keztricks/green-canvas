<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddressImportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Expected electoral-register CSV format
    |--------------------------------------------------------------------------
    |
    | Green Canvas was written against Calderdale's full (unmarked) electoral
    | register format, which is a 13-column CSV (one row per elector). The
    | first row is a header and is skipped. Address fields are concatenated
    | across the six "Address1..Address6" columns; the importer parses a
    | street name and house number out of them, with optional help from a
    | per-ward street-reference CSV (see config('canvassing.ward_reference_dir')).
    |
    | Different councils' EROs send different layouts — if your CSV has
    | a different column order, edit the constants below or send a PR
    | adding a per-deployment column-mapping config.
    |
    | See docs/data-formats.md for a worked example.
    */
    private const COL_POSTCODE  = 6;
    private const COL_ADDRESS_1 = 7;
    private const COL_ADDRESS_2 = 8;
    private const COL_ADDRESS_3 = 9;
    private const COL_ADDRESS_4 = 10;
    private const COL_ADDRESS_5 = 11;
    private const COL_ADDRESS_6 = 12;
    private const MIN_COLUMNS   = 10;

    public function index()
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $addressCount = Address::count();
        $wards = Ward::active()->orderBy('name')->get();
        return view('import.index', compact('addressCount', 'wards'));
    }

    private function loadStreetReference(string $wardName): array
    {
        $postcodeToStreet = [];

        $dir = trim(config('canvassing.ward_reference_dir'), '/');
        $filePath = storage_path('app/' . $dir . '/' . Str::slug($wardName) . '.csv');

        if (!file_exists($filePath)) {
            return $postcodeToStreet;
        }
        
        $handle = fopen($filePath, 'r');
        
        // Skip header rows (first 14 lines based on the structure)
        for ($i = 0; $i < 14; $i++) {
            fgetcsv($handle);
        }
        
        // Read data: columns are New Ward, Current Ward, Street, Full Address, Address 1-4, Post Code
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 9) continue;
            
            $street = trim($row[2]);
            $postcode = trim($row[8]);
            
            if (!empty($street) && !empty($postcode)) {
                $postcodeToStreet[$postcode] = $street;
            }
        }
        
        fclose($handle);
        
        return $postcodeToStreet;
    }

    public function store(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getPathname(), 'r');
        
        // Skip header row
        $header = fgetcsv($handle);
        
        $imported = 0;
        $updated = 0;
        $skipReasons = [
            'too_few_columns'   => 0,
            'missing_postcode'  => 0,
            'unparseable_street'=> 0,
            'no_house_number'   => 0,
        ];

        DB::beginTransaction();

        try {
            // Track elector count per unique address
            $addressCounts = [];

            // Load street reference for this ward
            $ward = Ward::find($request->ward_id);
            $streetReference = $this->loadStreetReference($ward->name);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < self::MIN_COLUMNS) {
                    $skipReasons['too_few_columns']++;
                    continue;
                }

                $addressFields = [
                    trim($row[self::COL_ADDRESS_1] ?? ''),
                    trim($row[self::COL_ADDRESS_2] ?? ''),
                    trim($row[self::COL_ADDRESS_3] ?? ''),
                    trim($row[self::COL_ADDRESS_4] ?? ''),
                    trim($row[self::COL_ADDRESS_5] ?? ''),
                    trim($row[self::COL_ADDRESS_6] ?? ''),
                ];

                $postcode = trim($row[self::COL_POSTCODE]);

                if (empty($postcode)) {
                    $skipReasons['missing_postcode']++;
                    continue;
                }

                // Try to get definitive street name from reference
                $streetName = $streetReference[$postcode] ?? null;
                $townAliases = config('canvassing.town_aliases', []);
                $defaultTown = $townAliases[0] ?? '';

                // If not in reference, parse from address fields
                if (!$streetName) {
                    $result = $this->parseAddressFields($addressFields);

                    if (!$result || !$result['street']) {
                        $skipReasons['unparseable_street']++;
                        continue;
                    }

                    $streetName = $result['street'];
                    $houseNumber = $result['house_number'];
                    $town = $result['town'] ?: $defaultTown;
                } else {
                    // Use reference street, extract house number
                    $houseNumber = $this->extractHouseNumber($addressFields, $streetName);

                    if (!$houseNumber) {
                        $skipReasons['no_house_number']++;
                        continue;
                    }

                    // Find town from address fields against the configured alias list.
                    $town = $defaultTown;
                    if (!empty($townAliases)) {
                        $aliasPattern = '/^(' . implode('|', array_map(
                            fn ($a) => preg_quote($a, '/'),
                            $townAliases
                        )) . ')/i';
                        foreach ($addressFields as $field) {
                            if (preg_match($aliasPattern, $field)) {
                                $town = $field;
                                break;
                            }
                        }
                    }
                }

                // Create unique key to count electors per address
                $uniqueKey = strtolower($houseNumber . '|' . $streetName . '|' . $postcode);
                
                if (!isset($addressCounts[$uniqueKey])) {
                    $addressCounts[$uniqueKey] = [
                        'ward_id' => $request->ward_id,
                        'house_number' => $houseNumber,
                        'street_name' => $streetName,
                        'town' => $town,
                        'postcode' => $postcode,
                        'constituency' => config('canvassing.default_constituency'),
                        'sort_order' => $this->extractNumericSort($houseNumber),
                        'elector_count' => 0,
                    ];
                }
                
                // Increment elector count for this address
                $addressCounts[$uniqueKey]['elector_count']++;
            }

            // Now create or update addresses with elector counts
            foreach ($addressCounts as $addressData) {
                $address = Address::updateOrCreate(
                    [
                        'ward_id' => $addressData['ward_id'],
                        'house_number' => $addressData['house_number'],
                        'street_name' => $addressData['street_name'],
                        'postcode' => $addressData['postcode'],
                    ],
                    [
                        'town' => $addressData['town'],
                        'constituency' => $addressData['constituency'],
                        'sort_order' => $addressData['sort_order'],
                        'elector_count' => $addressData['elector_count'],
                    ]
                );
                
                if ($address->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $updated++;
                }
            }

            DB::commit();
            fclose($handle);

            $message = "Successfully processed " . count($addressCounts) . " unique addresses";
            if ($imported > 0) {
                $message .= " ({$imported} new)";
            }
            if ($updated > 0) {
                $message .= " ({$updated} updated)";
            }

            $totalSkipped = array_sum($skipReasons);
            if ($totalSkipped > 0) {
                $reasonLabels = [
                    'too_few_columns'    => 'too few columns',
                    'missing_postcode'   => 'missing postcode',
                    'unparseable_street' => 'street unparseable',
                    'no_house_number'    => 'no house number',
                ];
                $parts = [];
                foreach ($skipReasons as $reason => $count) {
                    if ($count > 0) {
                        $parts[] = "{$count} {$reasonLabels[$reason]}";
                    }
                }
                $message .= " ({$totalSkipped} skipped: " . implode(', ', $parts) . ")";
            }

            return redirect()->route('import.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);

            return redirect()->route('import.index')
                ->with('error', 'Error importing addresses: ' . $e->getMessage());
        }
    }

    private function extractNumericSort(string $houseNumber): int
    {
        // Extract numeric part from house number for sorting (e.g., "12A" -> 12)
        preg_match('/\d+/', $houseNumber, $matches);
        return $matches[0] ?? 0;
    }

    /**
     * Regex matching configured town aliases as a prefix (e.g. "Halifax HX1 1AA" matches).
     * Returns null when no aliases are configured.
     */
    private function townSkipPattern(): ?string
    {
        $aliases = config('canvassing.town_aliases', []);
        if (empty($aliases)) {
            return null;
        }
        return '/^(' . implode('|', array_map(fn ($a) => preg_quote($a, '/'), $aliases)) . ')/i';
    }

    /**
     * Regex matching a field that is exactly a configured town alias.
     * Returns null when no aliases are configured.
     */
    private function townExactPattern(): ?string
    {
        $aliases = config('canvassing.town_aliases', []);
        if (empty($aliases)) {
            return null;
        }
        return '/^(' . implode('|', array_map(fn ($a) => preg_quote($a, '/'), $aliases)) . ')$/i';
    }

    private function extractHouseNumber(array $addressFields, ?string $streetName = null): ?string
    {
        $townSkip = $this->townSkipPattern();

        // Find the first field that contains useful house identifier
        foreach ($addressFields as $field) {
            $trimmed = trim($field);
            if (empty($trimmed)) continue;

            // Skip if it's just a town name or postcode outward
            if ($townSkip && preg_match($townSkip, $trimmed)) continue;
            if (preg_match('/^[A-Z]{1,2}\d/i', $trimmed)) continue;
            
            // If this field contains the street name, extract the part before it
            if ($streetName && stripos($trimmed, $streetName) !== false) {
                // Try to extract the part before the street name
                $parts = preg_split('/' . preg_quote($streetName, '/') . '/i', $trimmed, 2);
                if (!empty($parts[0])) {
                    $houseNumber = trim($parts[0], ', ');
                    if (!empty($houseNumber)) {
                        return $houseNumber;
                    }
                }
                // If nothing before street name, continue to next field
                continue;
            }
            
            return $trimmed;
        }
        return null;
    }

    private function parseAddressFields(array $addressFields): ?array
    {
        // Street type keywords - these definitively indicate a street name
        $streetTypes = 'Road|Street|Lane|Avenue|Drive|Way|Close|Place|Terrace|Walk|Gardens|Park|Grove|Crescent|Rise|View|Hill|Square|Green|Mews|Row|Parade|Broadway|Circle|Path|Croft|Bank|Mount|Fold|Vale|Heights|Side|Yard|Promenade|Approach|Court';
        
        // Filter out empty fields and likely town/postcode fields
        $townExact = $this->townExactPattern();
        $cleanFields = [];
        foreach ($addressFields as $field) {
            $trimmed = trim($field);
            if (empty($trimmed)) continue;
            // Skip if it's a configured town alias on its own, or a full postcode
            if ($townExact && preg_match($townExact, $trimmed)) continue;
            if (preg_match('/^[A-Z]{1,2}\d+\s*\d[A-Z]{2}$/i', $trimmed)) continue;
            $cleanFields[] = $trimmed;
        }
        
        if (empty($cleanFields)) {
            return null;
        }
        
        // Find the field that contains a street type keyword
        $streetIndex = null;
        $streetName = null;
        
        foreach ($cleanFields as $index => $field) {
            if (preg_match('/\b(' . $streetTypes . ')\b/i', $field)) {
                $streetIndex = $index;
                $streetName = $field;
                break;
            }
        }
        
        // If no street type found, use the second field as street (common pattern)
        if (!$streetName && count($cleanFields) >= 2) {
            $streetIndex = 1;
            $streetName = $cleanFields[1];
        }
        
        if (!$streetName) {
            return null;
        }
        
        // House number is everything before the street
        $houseNumberParts = [];
        for ($i = 0; $i < $streetIndex; $i++) {
            $houseNumberParts[] = $cleanFields[$i];
        }
        
        // If no parts before street, try to extract number from street field itself
        if (empty($houseNumberParts)) {
            if (preg_match('/^(\d+[A-Za-z]?(?:-\d+)?)\s+(.+)/', $streetName, $matches)) {
                $houseNumberParts[] = $matches[1];
                $streetName = $matches[2];
            } else {
                // Use full street as house number (e.g., building names)
                $houseNumberParts[] = $streetName;
            }
        }
        
        // Town is anything after the street
        $town = '';
        for ($i = $streetIndex + 1; $i < count($cleanFields); $i++) {
            $town = $cleanFields[$i];
            break; // Take first one after street
        }
        
        return [
            'house_number' => implode(', ', $houseNumberParts),
            'street' => $streetName,
            'town' => $town,
        ];
    }

    public function clear()
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        Address::truncate();
        
        return redirect()->route('import.index')
            ->with('success', 'All addresses have been cleared');
    }
}
