<?php

namespace App\Http\Controllers;

use App\Models\Street;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreetController extends Controller
{
    /**
     * Get all streets with unvisited address counts.
     */
    public function index(): JsonResponse
    {
        $streets = Street::withCount([
            'addresses as unvisited_count' => function ($query) {
                $query->where('status', 'unvisited');
            }
        ])
        ->orderBy('display_name')
        ->get();

        return response()->json($streets);
    }

    /**
     * Assign a street to a volunteer.
     */
    public function assign(Request $request, $id): JsonResponse
    {
        $request->validate([
            'volunteer_id' => 'required|string',
            'lock_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $volunteerId = $request->input('volunteer_id');
        $lockMinutes = $request->input('lock_minutes', 60);

        $street = Street::lockForUpdate()->findOrFail($id);

        // Check if already assigned to another volunteer and lock is still valid
        if ($street->assigned_to && 
            $street->assigned_to !== $volunteerId && 
            $street->lock_until && 
            now()->lt($street->lock_until)) {
            return response()->json([
                'message' => 'Street is locked by another volunteer',
                'locked_until' => $street->lock_until,
            ], 423);
        }

        // Assign street
        $street->assigned_to = $volunteerId;
        $street->lock_until = now()->addMinutes($lockMinutes);
        $street->save();

        return response()->json([
            'message' => 'Street assigned successfully',
            'street' => $street,
        ]);
    }
}
