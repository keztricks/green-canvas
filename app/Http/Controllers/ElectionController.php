<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\Address;
use Illuminate\Http\Request;

class ElectionController extends Controller
{
    public function index()
    {
        $elections = Election::orderBy('election_date', 'desc')->get();
        return view('elections.index', compact('elections'));
    }

    public function create()
    {
        return view('elections.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'election_date' => 'required|date',
            'type' => 'required|in:general,local,by-election,other',
        ]);

        $validated['active'] = true;

        Election::create($validated);

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
