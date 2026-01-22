<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Canvasser;
use App\Models\KnockResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CanvassingController extends Controller
{
    public function index()
    {
        // Get all unique streets with address counts
        $streets = Address::select('street_name', 'town')
            ->selectRaw('COUNT(*) as address_count')
            ->selectRaw('COUNT(DISTINCT knock_results.id) as knocked_count')
            ->leftJoin('knock_results', 'addresses.id', '=', 'knock_results.address_id')
            ->groupBy('street_name', 'town')
            ->orderBy('street_name')
            ->get();

        return view('canvassing.index', compact('streets'));
    }

    public function street($streetName)
    {
        $addresses = Address::byStreet($streetName)
            ->with(['knockResults' => function($query) {
                $query->latest('knocked_at');
            }])
            ->get();

        if ($addresses->isEmpty()) {
            return redirect()->route('canvassing.index')
                ->with('error', 'Street not found');
        }

        $town = $addresses->first()->town;
        $responseOptions = KnockResult::responseOptions();
        $canvassers = Canvasser::active()->orderBy('name')->get();

        return view('canvassing.street', compact('addresses', 'streetName', 'town', 'responseOptions', 'canvassers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'response' => 'required|in:not_home,conservative,labour,lib_dem,green,reform,undecided,refused,moved,other',
            'notes' => 'nullable|string|max:1000',
            'canvasser_id' => 'nullable|exists:canvassers,id',
        ]);

        $validated['knocked_at'] = now();
        
        // Get canvasser name if canvasser_id provided
        if (isset($validated['canvasser_id'])) {
            $canvasser = Canvasser::find($validated['canvasser_id']);
            $validated['canvasser_name'] = $canvasser->name;
            unset($validated['canvasser_id']);
        }

        KnockResult::create($validated);

        return back()->with('success', 'Result recorded successfully');
    }
}
