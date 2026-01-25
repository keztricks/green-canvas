<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\Address;
use App\Models\Ward;
use Illuminate\Http\Request;

class ElectionController extends Controller
{
    public function index()
    {
        $elections = Election::with('wards')->orderBy('election_date', 'desc')->get();
        return view('elections.index', compact('elections'));
    }

    public function create()
    {
        $wards = Ward::orderBy('name')->get();
        return view('elections.create', compact('wards'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'election_date' => 'required|date',
            'type' => 'required|in:general,local,by-election,other',
            'ward_ids' => 'nullable|array',
            'ward_ids.*' => 'exists:wards,id',
        ]);

        $validated['active'] = true;

        $election = Election::create($validated);

        if ($request->has('ward_ids')) {
            $election->wards()->attach($request->ward_ids);
        }

        return redirect()->route('elections.index')
            ->with('success', 'Election created successfully');
    }

    public function destroy(Election $election)
    {
        $election->delete();

        return redirect()->route('elections.index')
            ->with('success', 'Election deleted successfully');
    }

    public function toggleVoted(Request $request, $addressId, $electionId)
    {
        $address = Address::findOrFail($addressId);
        
        if ($address->elections()->where('election_id', $electionId)->exists()) {
            $address->elections()->detach($electionId);
            $voted = false;
        } else {
            $address->elections()->attach($electionId, ['voted' => true]);
            $voted = true;
        }

        return response()->json(['success' => true, 'voted' => $voted]);
    }
}
