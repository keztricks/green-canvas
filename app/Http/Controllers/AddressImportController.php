<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressImportController extends Controller
{
    public function index()
    {
        $addressCount = Address::count();
        $wards = Ward::active()->orderBy('name')->get();
        return view('import.index', compact('addressCount', 'wards'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getPathname(), 'r');
        
        // Skip header row
        $header = fgetcsv($handle);
        
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;

        DB::beginTransaction();
        
        try {
            $seen = [];
            
            while (($row = fgetcsv($handle)) !== false) {
                // Electoral register format: 
                // Columns: 0=Prefix, 1=Number, 2=Suffix, 3=Markers, 4=DOB, 5=Name, 6=Postcode, 
                //          7=Address1, 8=Address2, 9=Address3, 10=Address4, 11=Address5, 12=Address6
                
                if (count($row) < 10) {
                    continue;
                }

                $address1 = trim($row[7]); // e.g., "1 Arden Mews" or "51 Wakefield Road" or "Flat A"
                $address2 = trim($row[8]); // e.g., "Arden Road" or "Sowerby Bridge" or "100 Wakefield Road"
                $address3 = trim($row[9]); // e.g., "Halifax" or "HX6 2AZ" or "Sowerby Bridge"
                $postcode = trim($row[6]);
                
                // Skip if missing essential data
                if (empty($address1) || empty($postcode)) {
                    $skipped++;
                    continue;
                }

                // Determine street name and house number intelligently
                $houseNumber = '';
                $streetName = '';
                $town = '';
                
                // Check if Address1 contains a building complex/block name
                // These should NOT be treated as streets
                $buildingComplexPattern = '/\b(Mews|Almshouses|Apartments|Flats|Court|House|Cottages|Villas|Mansions|Buildings|Block|Tower)\b/i';
                
                // Check if Address1 contains a number at the start
                if (preg_match('/^(\d+[A-Za-z]?(?:-\d+)?)\s+(.+)/', $address1, $matches)) {
                    $houseNumber = $matches[1];
                    $remainder = $matches[2];
                    
                    // Check if remainder contains a building complex identifier
                    if (preg_match($buildingComplexPattern, $remainder)) {
                        // It's a building complex like "1 Arden Mews" or "1 Crossley Almshouses"
                        // Use full Address1 as house number, Address2 as street
                        $houseNumber = $address1;
                        $streetName = $address2;
                        $town = $address3;
                    }
                    // Check if remainder looks like a street name
                    elseif (preg_match('/\b(Road|Street|Lane|Avenue|Drive|Way|Close|Place|Terrace|Walk|Gardens|Park|Grove|Crescent|Rise|View|Hill)\b/i', $remainder)) {
                        // It's a proper street address like "51 Wakefield Road"
                        $streetName = $remainder;
                        $town = $address2;
                    }
                    else {
                        // Fallback: treat as building name
                        $houseNumber = $address1;
                        $streetName = $address2;
                        $town = $address3;
                    }
                } 
                // Check if Address1 is a flat/apartment designation
                elseif (preg_match('/^(Flat|Apartment|Unit|Room)\s+([A-Z0-9]+)$/i', $address1)) {
                    // Address2 should contain the street number and name
                    if (preg_match('/^(\d+[A-Za-z]?)\s+(.+)/', $address2, $matches)) {
                        $houseNumber = $matches[1] . ' ' . $address1;
                        $streetName = $matches[2];
                        $town = $address3;
                    } else {
                        $skipped++;
                        continue;
                    }
                }
                // Check if Address1 ends with a known street type (like "Carlton House Terrace")
                elseif (preg_match('/\b(Road|Street|Lane|Avenue|Drive|Way|Close|Place|Terrace|Walk|Gardens|Park|Grove|Crescent|Rise|View|Hill)$/i', $address1)) {
                    // Treat as street name if it's just the street without number
                    $houseNumber = $address1;
                    $streetName = $address1;
                    $town = $address2;
                }
                // Address1 is a building/complex name without a number
                else {
                    $houseNumber = $address1;
                    $streetName = $address2;
                    $town = $address3;
                }
                
                // Final validation
                if (empty($houseNumber) || empty($streetName)) {
                    $skipped++;
                    continue;
                }

                // Create unique key to avoid duplicate addresses
                $uniqueKey = strtolower($houseNumber . '|' . $streetName . '|' . $postcode);
                
                if (isset($seen[$uniqueKey])) {
                    $duplicates++;
                    continue;
                }
                
                $seen[$uniqueKey] = true;

                Address::create([
                    'ward_id' => $request->ward_id,
                    'house_number' => $houseNumber,
                    'street_name' => $streetName,
                    'town' => $town,
                    'postcode' => $postcode,
                    'constituency' => 'Halifax',
                    'sort_order' => $this->extractNumericSort($houseNumber),
                ]);

                $imported++;
            }

            DB::commit();
            fclose($handle);

            $message = "Successfully imported {$imported} unique addresses";
            if ($duplicates > 0) {
                $message .= " ({$duplicates} duplicates skipped)";
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

    public function clear()
    {
        Address::truncate();
        
        return redirect()->route('import.index')
            ->with('success', 'All addresses have been cleared');
    }
}
