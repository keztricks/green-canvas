<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Get addresses for a specific street.
     */
    public function byStreet($streetId): JsonResponse
    {
        $addresses = Address::where('street_id', $streetId)
            ->select('id', 'raw_address', 'postcode', 'house_number', 'status', 'lat', 'lon')
            ->get()
            ->sortBy(function ($address) {
                // Sort by house number numerically first, then lexicographically
                $houseNumber = $address->house_number;
                
                // Handle null or empty house numbers
                if (empty($houseNumber)) {
                    return [PHP_INT_MAX, ''];
                }
                
                if (is_numeric($houseNumber)) {
                    return [(int) $houseNumber, ''];
                }
                // Extract numeric part if exists
                if (preg_match('/^(\d+)(.*)$/', $houseNumber, $matches)) {
                    return [(int) $matches[1], $matches[2]];
                }
                return [PHP_INT_MAX, $houseNumber];
            })
            ->values();

        return response()->json($addresses);
    }

    /**
     * Record a visit to an address.
     */
    public function visit(Request $request, $id): JsonResponse
    {
        $request->validate([
            'volunteer_id' => 'required|string',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $address = Address::findOrFail($id);

            // Update address
            $address->status = $request->input('status');
            $address->last_contacted_at = now();
            $address->current_volunteer = $request->input('volunteer_id');
            $address->save();

            // Create visit record
            Visit::create([
                'address_id' => $id,
                'volunteer_id' => $request->input('volunteer_id'),
                'status' => $request->input('status'),
                'notes' => $request->input('notes'),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Visit recorded successfully',
                'address' => $address,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
