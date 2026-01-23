<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\KnockResult;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CanvassingController extends Controller
{
    public function index()
    {
        // Get all wards with address counts
        $wards = Ward::active()
            ->withCount('addresses')
            ->orderBy('name')
            ->get();

        return view('canvassing.index', compact('wards'));
    }

    public function ward($wardId)
    {
        $ward = Ward::findOrFail($wardId);
        
        // Get all unique streets in this ward with address counts
        $streets = Address::byWard($wardId)
            ->select('street_name', 'town')
            ->selectRaw('COUNT(*) as address_count')
            ->selectRaw('COUNT(DISTINCT knock_results.id) as knocked_count')
            ->leftJoin('knock_results', 'addresses.id', '=', 'knock_results.address_id')
            ->groupBy('street_name', 'town')
            ->orderBy('street_name')
            ->get();

        return view('canvassing.ward', compact('ward', 'streets'));
    }

    public function street($wardId, $streetName)
    {
        $ward = Ward::findOrFail($wardId);
        
        $addresses = Address::byWard($wardId)
            ->byStreet($streetName)
            ->with(['knockResults' => function($query) {
                $query->latest('knocked_at');
            }])
            ->get();

        if ($addresses->isEmpty()) {
            return redirect()->route('canvassing.ward', $wardId)
                ->with('error', 'Street not found');
        }

        $town = $addresses->first()->town;
        $responseOptions = KnockResult::responseOptions();

        return view('canvassing.street', compact('ward', 'addresses', 'streetName', 'town', 'responseOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'response' => 'required|in:not_home,conservative,labour,lib_dem,green,reform,your_party,undecided,refused,other',
            'vote_likelihood' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['knocked_at'] = now();
        $validated['user_id'] = auth()->id();
        
        KnockResult::create($validated);

        return back()->with('success', 'Result recorded successfully');
    }
}
