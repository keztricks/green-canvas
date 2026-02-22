<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AddressImportController extends Controller
{
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
        // Map of ward reference files
        $referenceFiles = [
            'Wainhouse' => storage_path('app/ward-references/wainhouse.csv'),
            'Hebden Bridge & Todmorden East' => storage_path('app/ward-references/hebden-bridge-todmorden-east.csv'),
            'Brighouse' => storage_path('app/ward-references/brighouse.csv'),
            'Elland' => storage_path('app/ward-references/elland.csv'),
            'Greetland' => storage_path('app/ward-references/greetland.csv'),
            'Halifax Town' => storage_path('app/ward-references/halifax-town.csv'),
            'Hipperholme & Lightcliffe' => storage_path('app/ward-references/hipperholme-lightcliffe.csv'),
            'Illingworth & Mixenden' => storage_path('app/ward-references/illingworth-mixenden.csv'),
            'Luddendenfoot' => storage_path('app/ward-references/luddendenfoot.csv'),
            'Northowram & Shelf' => storage_path('app/ward-references/northowram-shelf.csv'),
            'Ovenden' => storage_path('app/ward-references/ovenden.csv'),
            'Park' => storage_path('app/ward-references/park.csv'),
            'Rastrick' => storage_path('app/ward-references/rastrick.csv'),
            'Ryburn' => storage_path('app/ward-references/ryburn.csv'),
            'Salterhebble Southowram and Skircoat Green' => storage_path('app/ward-references/salterhebble-southowram-skircoat-green.csv'),
            'Sowerby Bridge' => storage_path('app/ward-references/sowerby-bridge.csv'),
            'Todmorden West' => storage_path('app/ward-references/todmorden-west.csv'),
            'Warley' => storage_path('app/ward-references/warley.csv'),
        ];
        
        $postcodeToStreet = [];
        
        if (!isset($referenceFiles[$wardName])) {
            return $postcodeToStreet;
        }
        
        $filePath = $referenceFiles[$wardName];
        
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
        $skipped = 0;

        DB::beginTransaction();
        
        try {
            // Track elector count per unique address
            $addressCounts = [];
            
            // Load street reference for this ward
            $ward = Ward::find($request->ward_id);
            $streetReference = $this->loadStreetReference($ward->name);
            
            while (($row = fgetcsv($handle)) !== false) {
                // Electoral register format: 
                // Columns: 0=Prefix, 1=Number, 2=Suffix, 3=Markers, 4=DOB, 5=Name, 6=Postcode, 
                //          7=Address1, 8=Address2, 9=Address3, 10=Address4, 11=Address5, 12=Address6
                
                if (count($row) < 10) {
                    continue;
                }

                $addressFields = [
                    trim($row[7] ?? ''),
                    trim($row[8] ?? ''),
                    trim($row[9] ?? ''),
                    trim($row[10] ?? ''),
                    trim($row[11] ?? ''),
                    trim($row[12] ?? ''),
                ];
                
                $postcode = trim($row[6]);
                
                // Skip if missing postcode
                if (empty($postcode)) {
                    $skipped++;
                    continue;
                }

                // Try to get definitive street name from reference
                $streetName = $streetReference[$postcode] ?? null;
                
                // If not in reference, parse from address fields
                if (!$streetName) {
                    $result = $this->parseAddressFields($addressFields);
                    
                    if (!$result || !$result['street']) {
                        $skipped++;
                        continue;
                    }
                    
                    $streetName = $result['street'];
                    $houseNumber = $result['house_number'];
                    $town = $result['town'] ?: 'Halifax';
                } else {
                    // Use reference street, extract house number
                    $houseNumber = $this->extractHouseNumber($addressFields, $streetName);
                    
                    if (!$houseNumber) {
                        $skipped++;
                        continue;
                    }
                    
                    // Find town from address fields
                    $town = 'Halifax';
                    foreach ($addressFields as $field) {
                        if (preg_match('/^(Halifax|Sowerby Bridge|Copley|Hebden Bridge|Todmorden|Charlestown|Mytholmroyd|Wainstalls|Pecket Well|Midgley)/i', $field)) {
                            $town = $field;
                            break;
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
                        'constituency' => 'Halifax',
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
            if ($skipped > 0) {
                $message .= " ({$skipped} incomplete records skipped)";
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

    private function extractHouseNumber(array $addressFields, ?string $streetName = null): ?string
    {
        // Find the first field that contains useful house identifier
        foreach ($addressFields as $field) {
            $trimmed = trim($field);
            if (empty($trimmed)) continue;
            
            // Skip if it's just a town name or postcode
            if (preg_match('/^(Halifax|Sowerby Bridge|Copley|Hebden Bridge|Todmorden|Charlestown|Mytholmroyd|Wainstalls|Pecket Well|Midgley|HX\d)/i', $trimmed)) continue;
            
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
        $cleanFields = [];
        foreach ($addressFields as $field) {
            $trimmed = trim($field);
            if (empty($trimmed)) continue;
            // Skip if it's just "Halifax" or looks like a postcode
            if (preg_match('/^(Halifax|HX\d+\s*\d[A-Z]{2})$/i', $trimmed)) continue;
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
