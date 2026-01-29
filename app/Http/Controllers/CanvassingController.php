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
            ->selectRaw('COUNT(DISTINCT addresses.id) as address_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN knock_results.id IS NOT NULL THEN addresses.id END) as knocked_count')
            ->leftJoin('knock_results', 'addresses.id', '=', 'knock_results.address_id')
            ->groupBy('street_name', 'town')
            ->orderBy('street_name')
            ->get();

        return view('canvassing.ward', compact('ward', 'streets'));
    }

    public function allStreets($wardId)
    {
        $ward = Ward::findOrFail($wardId);
        
        $addresses = Address::byWard($wardId)
            ->with(['knockResults' => function($query) {
                $query->with('user')->latest('knocked_at');
            }, 'elections'])
            ->orderBy('street_name')
            ->orderBy('sort_order')
            ->get();

        if ($addresses->isEmpty()) {
            return redirect()->route('canvassing.ward', $wardId)
                ->with('error', 'No addresses found in this ward');
        }

        $responseOptions = KnockResult::responseOptions();
        $elections = \App\Models\Election::where('active', true)->orderBy('election_date', 'desc')->get();

        return view('canvassing.all-streets', compact('ward', 'addresses', 'responseOptions', 'elections'));
    }

    public function street($wardId, $streetName)
    {
        $ward = Ward::findOrFail($wardId);
        
        $addresses = Address::byWard($wardId)
            ->byStreet($streetName)
            ->with(['knockResults' => function($query) {
                $query->with('user')->latest('knocked_at');
            }, 'elections'])
            ->get();

        if ($addresses->isEmpty()) {
            return redirect()->route('canvassing.ward', $wardId)
                ->with('error', 'Street not found');
        }

        $town = $addresses->first()->town;
        $responseOptions = KnockResult::responseOptions();
        $elections = \App\Models\Election::where('active', true)->orderBy('election_date', 'desc')->get();

        return view('canvassing.street', compact('ward', 'addresses', 'streetName', 'town', 'responseOptions', 'elections'));
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

        return back()->with('success', 'Result recorded successfully')->withFragment('address-' . $validated['address_id']);
    }

    public function update(Request $request, KnockResult $knockResult)
    {
        $validated = $request->validate([
            'response' => 'required|in:not_home,conservative,labour,lib_dem,green,reform,your_party,undecided,refused,other',
            'vote_likelihood' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string|max:1000',
        ]);

        $knockResult->update($validated);

        return back()->with('success', 'Result updated successfully');
    }

    public function destroy(KnockResult $knockResult)
    {
        $knockResult->delete();

        return back()->with('success', 'Result deleted successfully');
    }

    public function markDoNotKnock(Address $address)
    {
        $address->update([
            'do_not_knock' => true,
            'do_not_knock_at' => now(),
        ]);

        return back()->with('success', 'Address marked as Do Not Knock');
    }

    public function clearDoNotKnock(Address $address)
    {
        $address->update([
            'do_not_knock' => false,
            'do_not_knock_at' => null,
        ]);

        return back()->with('success', 'Do Not Knock status cleared');
    }
    public function storeAddress(Request $request)
    {
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'house_number' => 'required|string|max:255',
            'street_name' => 'required|string|max:255',
            'town' => 'required|string|max:255',
            'postcode' => 'required|string|max:10',
        ]);

        // Check for duplicate
        $exists = Address::where('ward_id', $validated['ward_id'])
            ->where('house_number', $validated['house_number'])
            ->where('street_name', $validated['street_name'])
            ->where('postcode', $validated['postcode'])
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'This address already exists');
        }

        // Extract numeric sort order
        $sortOrder = 0;
        if (preg_match('/^(\d+)/', $validated['house_number'], $matches)) {
            $sortOrder = (int)$matches[1];
        }

        Address::create([
            'ward_id' => $validated['ward_id'],
            'house_number' => $validated['house_number'],
            'street_name' => $validated['street_name'],
            'town' => $validated['town'],
            'postcode' => $validated['postcode'],
            'constituency' => 'Halifax',
            'sort_order' => $sortOrder,
        ]);

        return redirect()->back()
            ->with('success', 'Address added successfully');
    }}
