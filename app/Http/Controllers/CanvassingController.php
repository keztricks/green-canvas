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
        $user = auth()->user();
        
        // Get wards based on user role
        $query = Ward::active()->withCount('addresses')->orderBy('name');
        
        // If not admin, filter to only assigned wards
        if (!$user->isAdmin()) {
            $query->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        
        $wards = $query->get();

        return view('canvassing.index', compact('wards'));
    }

    public function ward($wardId)
    {
        $ward = Ward::findOrFail($wardId);
        
        // Check if user has access to this ward
        if (!auth()->user()->hasAccessToWard($wardId)) {
            abort(403, 'You do not have access to this ward.');
        }
        
        // Get filter parameters (election_filters is an array like [1 => ['voted', 'not_voted'], 2 => ['unknown']])
        $selectedElectionFilters = request('election_filters', []);
        
        // Get elections for this ward
        $elections = \App\Models\Election::where('active', true)
            ->where(function($query) use ($wardId) {
                $query->whereDoesntHave('wards')
                    ->orWhereHas('wards', function($q) use ($wardId) {
                        $q->where('wards.id', $wardId);
                    });
            })
            ->orderBy('election_date', 'desc')
            ->get();
        
        // Get all unique streets in this ward with address counts
        $addressQuery = Address::byWard($wardId);
        
        // Apply election filters
        if (!empty($selectedElectionFilters)) {
            $addressQuery->byElectionStatus($selectedElectionFilters);
        }
        
        // Get the filtered address IDs as a subquery
        $filteredAddressIds = $addressQuery->pluck('id');
        
        // Now get street summary with counts based on filtered addresses
        $streets = Address::select('addresses.street_name as street_name', 'addresses.town as town')
            ->selectRaw('COUNT(DISTINCT addresses.id) as address_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN knock_results.id IS NOT NULL THEN addresses.id END) as knocked_count')
            ->leftJoin('knock_results', 'addresses.id', '=', 'knock_results.address_id')
            ->whereIn('addresses.id', $filteredAddressIds)
            ->groupBy('addresses.street_name', 'addresses.town')
            ->orderBy('addresses.street_name')
            ->get();

        return view('canvassing.ward', compact('ward', 'streets', 'elections', 'selectedElectionFilters'));
    }

    public function allStreets($wardId)
    {
        $ward = Ward::findOrFail($wardId);
        
        // Check if user has access to this ward
        if (!auth()->user()->hasAccessToWard($wardId)) {
            abort(403, 'You do not have access to this ward.');
        }
        
        // Get filter parameters
        $selectedElectionFilters = request('election_filters', []);
        $selectedResponseFilters = array_filter((array) request('response_filters', []));
        $selectedLikelihoodFilters = array_filter((array) request('likelihood_filters', []));

        $query = Address::byWard($wardId)
            ->with(['knockResults' => function($query) {
                $query->with('user')->latest('knocked_at');
            }, 'elections'])
            ->orderBy('street_name')
            ->orderBy('sort_order');

        // Apply election filters
        if (!empty($selectedElectionFilters)) {
            $query->byElectionStatus($selectedElectionFilters);
        }

        // Apply knock result filters
        $query->byKnockResponse($selectedResponseFilters, $selectedLikelihoodFilters);

        // Handle search
        if (request()->has('search')) {
            $search = request('search');
            $searchTerms = preg_split('/\s+/', trim($search));
            
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->where(function($subQ) use ($term) {
                        $subQ->where('street_name', 'like', "%{$term}%")
                             ->orWhere('house_number', 'like', "%{$term}%");
                    });
                }
            });
        }

        $addresses = $query->paginate(50);

        $hasActiveFilters = !empty($selectedElectionFilters) || !empty($selectedResponseFilters) || !empty($selectedLikelihoodFilters);

        if ($addresses->isEmpty() && !request()->has('search') && !$hasActiveFilters) {
            return redirect()->route('canvassing.ward', $wardId)
                ->with('error', 'No addresses found in this ward');
        }

        $responseOptions = KnockResult::responseOptions();
        $turnoutLikelihoodOptions = KnockResult::turnoutLikelihoodOptions();
        $elections = \App\Models\Election::where('active', true)
            ->where(function($query) use ($wardId) {
                $query->whereDoesntHave('wards')
                    ->orWhereHas('wards', function($q) use ($wardId) {
                        $q->where('wards.id', $wardId);
                    });
            })
            ->orderBy('election_date', 'desc')
            ->get();

        // Return JSON for AJAX requests
        if (request()->wantsJson() || request()->ajax()) {
            $addressesHtml = $addresses->map(function($address) use ($responseOptions, $turnoutLikelihoodOptions, $elections) {
                return view('canvassing.partials.address-item', [
                    'address' => $address,
                    'responseOptions' => $responseOptions,
                    'turnoutLikelihoodOptions' => $turnoutLikelihoodOptions,
                    'elections' => $elections
                ])->render();
            });

            return response()->json([
                'addresses' => $addressesHtml,
                'hasMore' => $addresses->hasMorePages(),
                'nextPage' => $addresses->currentPage() + 1,
                'total' => $addresses->total(),
            ]);
        }

        return view('canvassing.all-streets', compact('ward', 'addresses', 'responseOptions', 'turnoutLikelihoodOptions', 'elections', 'selectedElectionFilters', 'selectedResponseFilters', 'selectedLikelihoodFilters'));
    }

    public function street($wardId, $streetName)
    {
        $ward = Ward::findOrFail($wardId);
        
        // Check if user has access to this ward
        if (!auth()->user()->hasAccessToWard($wardId)) {
            abort(403, 'You do not have access to this ward.');
        }
        
        // Get filter parameters
        $selectedElectionFilters = request('election_filters', []);
        $selectedResponseFilters = array_filter((array) request('response_filters', []));
        $selectedLikelihoodFilters = array_filter((array) request('likelihood_filters', []));

        $query = Address::byWard($wardId)
            ->byStreet($streetName)
            ->with(['knockResults' => function($query) {
                $query->with('user')->latest('knocked_at');
            }, 'elections']);

        // Apply election filters
        if (!empty($selectedElectionFilters)) {
            $query->byElectionStatus($selectedElectionFilters);
        }

        // Apply knock result filters
        $query->byKnockResponse($selectedResponseFilters, $selectedLikelihoodFilters);

        $addresses = $query->get();

        $hasActiveFilters = !empty($selectedElectionFilters) || !empty($selectedResponseFilters) || !empty($selectedLikelihoodFilters);

        if ($addresses->isEmpty() && !$hasActiveFilters) {
            return redirect()->route('canvassing.ward', $wardId)
                ->with('error', 'Street not found');
        }

        $town = $addresses->first()->town
            ?? Address::byWard($wardId)->byStreet($streetName)->value('town')
            ?? '';
        $responseOptions = KnockResult::responseOptions();
        $turnoutLikelihoodOptions = KnockResult::turnoutLikelihoodOptions();
        $elections = \App\Models\Election::where('active', true)
            ->where(function($query) use ($wardId) {
                $query->whereDoesntHave('wards')
                    ->orWhereHas('wards', function($q) use ($wardId) {
                        $q->where('wards.id', $wardId);
                    });
            })
            ->orderBy('election_date', 'desc')
            ->get();

        return view('canvassing.street', compact('ward', 'addresses', 'streetName', 'town', 'responseOptions', 'turnoutLikelihoodOptions', 'elections', 'selectedElectionFilters', 'selectedResponseFilters', 'selectedLikelihoodFilters'));
    }

    public function map($wardId)
    {
        $ward = Ward::findOrFail($wardId);

        if (!auth()->user()->hasAccessToWard($wardId)) {
            abort(403, 'You do not have access to this ward.');
        }

        $user = auth()->user();
        $wardsQuery = Ward::active()->orderBy('name');
        if (!$user->isAdmin()) {
            $wardsQuery->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        $wards = $wardsQuery->get();

        $addresses = Address::byWard($wardId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['latestKnockResult' => fn($q) => $q
                ->select('knock_results.id', 'knock_results.address_id', 'knock_results.user_id', 'knock_results.response', 'knock_results.vote_likelihood', 'knock_results.turnout_likelihood', 'knock_results.notes', 'knock_results.knocked_at')
                ->with(['user' => fn($uq) => $uq->select('id', 'name')])])
            ->get(['id', 'ward_id', 'house_number', 'street_name', 'town', 'postcode', 'latitude', 'longitude', 'precise_position', 'do_not_knock']);

        $addressData = $addresses->map(fn($a) => $this->serialiseAddressForMap($a));

        $stats = DB::table('addresses')
            ->where('ward_id', $wardId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN latitude IS NOT NULL THEN 1 ELSE 0 END) as geocoded')
            ->selectRaw('SUM(CASE WHEN EXISTS(SELECT 1 FROM knock_results WHERE address_id = addresses.id) THEN 1 ELSE 0 END) as knocked')
            ->first();

        $totalCount    = (int) $stats->total;
        $geocodedCount = (int) $stats->geocoded;
        $knockedCount  = (int) $stats->knocked;

        $responseOptions = KnockResult::responseOptions();
        $turnoutOptions  = KnockResult::turnoutLikelihoodOptions();

        $canEditPositions = $user->isAdmin() || $user->isWardAdmin();

        $missingAddresses = collect();
        if ($canEditPositions) {
            $missingAddresses = Address::byWard($wardId)
                ->whereNull('latitude')
                ->orderBy('street_name')->orderBy('sort_order')
                ->get(['id', 'ward_id', 'house_number', 'street_name', 'town', 'postcode'])
                ->map(fn($a) => [
                    'id'       => $a->id,
                    'ward_id'  => $a->ward_id,
                    'label'    => trim($a->house_number . ' ' . $a->street_name),
                    'address'  => $a->full_address,
                    'street'   => $a->street_name,
                    'postcode' => $a->postcode,
                ]);
        }

        return view('canvassing.map', compact('ward', 'wards', 'addressData', 'totalCount', 'geocodedCount', 'knockedCount', 'responseOptions', 'turnoutOptions', 'canEditPositions', 'missingAddresses'));
    }

    public function mapAll()
    {
        // ~80k addresses across the council exceeds the default 128M limit.
        ini_set('memory_limit', '512M');

        $user = auth()->user();
        $wardsQuery = Ward::active()->orderBy('name');
        if (!$user->isAdmin()) {
            $wardsQuery->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        $wards = $wardsQuery->get();
        $wardIds = $wards->pluck('id')->all();

        if (empty($wardIds)) {
            abort(404, 'No accessible wards.');
        }

        // All-wards mode covers tens of thousands of addresses across the whole
        // council, so use raw DB queries (no Eloquent hydration overhead) and
        // a single JOIN to the latest knock result per address.
        $latestKnockSubquery = DB::table('knock_results as kr1')
            ->select('kr1.address_id', 'kr1.user_id', 'kr1.response', 'kr1.vote_likelihood', 'kr1.turnout_likelihood', 'kr1.notes', 'kr1.knocked_at')
            ->whereRaw('kr1.knocked_at = (SELECT MAX(kr2.knocked_at) FROM knock_results kr2 WHERE kr2.address_id = kr1.address_id)');

        $addresses = DB::table('addresses')
            ->leftJoinSub($latestKnockSubquery, 'lk', 'lk.address_id', '=', 'addresses.id')
            ->leftJoin('users', 'users.id', '=', 'lk.user_id')
            ->whereIn('addresses.ward_id', $wardIds)
            ->whereNotNull('addresses.latitude')
            ->whereNotNull('addresses.longitude')
            ->select(
                'addresses.id', 'addresses.ward_id', 'addresses.house_number', 'addresses.street_name',
                'addresses.town', 'addresses.postcode', 'addresses.latitude', 'addresses.longitude',
                'addresses.precise_position', 'addresses.do_not_knock',
                'lk.response', 'lk.vote_likelihood', 'lk.turnout_likelihood', 'lk.notes', 'lk.knocked_at',
                'users.name as canvasser_name',
            )
            ->get();

        $addressData = $addresses->map(fn($a) => [
            'id'         => $a->id,
            'ward_id'    => $a->ward_id,
            'lat'        => (float) $a->latitude,
            'lng'        => (float) $a->longitude,
            'label'      => $a->house_number . ' ' . $a->street_name,
            'address'    => "{$a->house_number} {$a->street_name}, {$a->town}, {$a->postcode}",
            'street'     => $a->street_name,
            'postcode'   => $a->postcode,
            'precise'    => (bool) $a->precise_position,
            'dnk'        => (bool) $a->do_not_knock,
            'response'   => $a->response,
            'turnout'    => $a->turnout_likelihood,
            'likelihood' => $a->vote_likelihood,
            'notes'      => $a->notes,
            'canvasser'  => $a->canvasser_name,
            'knocked_at' => $a->knocked_at ? substr($a->knocked_at, 0, 16) : null,
        ]);
        unset($addresses); // free memory before serialising the response

        $stats = DB::table('addresses')
            ->whereIn('ward_id', $wardIds)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN latitude IS NOT NULL THEN 1 ELSE 0 END) as geocoded')
            ->selectRaw('SUM(CASE WHEN EXISTS(SELECT 1 FROM knock_results WHERE address_id = addresses.id) THEN 1 ELSE 0 END) as knocked')
            ->first();
        $totalCount    = (int) $stats->total;
        $geocodedCount = (int) $stats->geocoded;
        $knockedCount  = (int) $stats->knocked;

        $responseOptions  = KnockResult::responseOptions();
        $turnoutOptions   = KnockResult::turnoutLikelihoodOptions();
        $canEditPositions = $user->isAdmin() || $user->isWardAdmin();

        $missingAddresses = collect();
        if ($canEditPositions) {
            $missingAddresses = Address::whereIn('ward_id', $wardIds)
                ->whereNull('latitude')
                ->orderBy('street_name')->orderBy('sort_order')
                ->get(['id', 'ward_id', 'house_number', 'street_name', 'town', 'postcode'])
                ->map(fn($a) => [
                    'id'       => $a->id,
                    'ward_id'  => $a->ward_id,
                    'label'    => trim($a->house_number . ' ' . $a->street_name),
                    'address'  => $a->full_address,
                    'street'   => $a->street_name,
                    'postcode' => $a->postcode,
                ]);
        }

        $ward = null; // signals "all wards" mode to the view

        return view('canvassing.map', compact('ward', 'wards', 'addressData', 'totalCount', 'geocodedCount', 'knockedCount', 'responseOptions', 'turnoutOptions', 'canEditPositions', 'missingAddresses'));
    }

    private function serialiseAddressForMap(Address $address): array
    {
        $latest = $address->latestKnockResult;
        return [
            'id'         => $address->id,
            'ward_id'    => $address->ward_id,
            'lat'        => (float) $address->latitude,
            'lng'        => (float) $address->longitude,
            'label'      => $address->house_number . ' ' . $address->street_name,
            'address'    => $address->full_address,
            'street'     => $address->street_name,
            'postcode'   => $address->postcode,
            'precise'    => (bool) $address->precise_position,
            'dnk'        => $address->do_not_knock,
            'response'   => $latest?->response,
            'turnout'    => $latest?->turnout_likelihood,
            'likelihood' => $latest?->vote_likelihood,
            'notes'      => $latest?->notes,
            'canvasser'  => $latest?->user?->name,
            'knocked_at' => $latest?->knocked_at?->format('d M Y H:i'),
        ];
    }

    public function wardBoundaries()
    {
        $path = storage_path('app/' . ltrim(config('canvassing.ward_boundary_file'), '/'));
        if (!file_exists($path)) {
            abort(404, 'Ward boundary data not available.');
        }
        return response()->file($path, [
            'Content-Type'  => 'application/geo+json',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function updatePosition(Request $request, Address $address)
    {
        $user = auth()->user();
        abort_if(!$user->isAdmin() && !$user->isWardAdmin(), 403, 'Only admins and ward admins can move dots.');
        abort_if(!$user->hasAccessToWard($address->ward_id), 403, 'You do not have access to this ward.');

        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $address->update([
            'latitude'         => $validated['lat'],
            'longitude'        => $validated['lng'],
            'precise_position' => true,
        ]);

        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'response' => 'required|in:not_home,conservative,labour,lib_dem,green,reform,your_party,undecided,refused,wont_vote,other',
            'vote_likelihood' => 'nullable|integer|min:1|max:5',
            'turnout_likelihood' => 'nullable|in:wont,might,will',
            'notes' => 'nullable|string|max:1000',
        ]);

        $address = Address::findOrFail($validated['address_id']);
        abort_if(!auth()->user()->hasAccessToWard($address->ward_id), 403, 'You do not have access to this ward.');

        $validated['knocked_at'] = now();
        $validated['user_id'] = auth()->id();

        $knockResult = KnockResult::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'       => true,
                'response'      => $knockResult->response,
                'response_label'=> KnockResult::responseOptions()[$knockResult->response] ?? $knockResult->response,
                'likelihood'    => $knockResult->vote_likelihood,
                'turnout'       => $knockResult->turnout_likelihood,
                'notes'         => $knockResult->notes,
                'canvasser'     => auth()->user()->name,
                'knocked_at'    => $knockResult->knocked_at->format('d M Y H:i'),
            ]);
        }

        $redirect = back()->with('success', 'Result recorded successfully')->withFragment('address-' . $validated['address_id']);

        if ($request->has('search')) {
            $redirect->withInput(['search' => $request->input('search')]);
        }

        return $redirect;
    }

    public function update(Request $request, KnockResult $knockResult)
    {
        abort_if(!auth()->user()->hasAccessToWard($knockResult->address->ward_id), 403, 'You do not have access to this ward.');

        $validated = $request->validate([
            'response' => 'required|in:not_home,conservative,labour,lib_dem,green,reform,your_party,undecided,refused,wont_vote,other',
            'vote_likelihood' => 'nullable|integer|min:1|max:5',
            'turnout_likelihood' => 'nullable|in:wont,might,will',
            'notes' => 'nullable|string|max:1000',
        ]);

        $knockResult->update($validated);

        $redirect = back()->with('success', 'Result updated successfully');
        
        if ($request->has('search')) {
            $redirect->withInput(['search' => $request->input('search')]);
        }
        
        return $redirect;
    }

    public function destroy(Request $request, KnockResult $knockResult)
    {
        abort_if(!auth()->user()->hasAccessToWard($knockResult->address->ward_id), 403, 'You do not have access to this ward.');

        $knockResult->delete();

        $redirect = back()->with('success', 'Result deleted successfully');
        
        if ($request->has('search')) {
            $redirect->withInput(['search' => $request->input('search')]);
        }
        
        return $redirect;
    }

    public function markDoNotKnock(Request $request, Address $address)
    {
        abort_if(!auth()->user()->hasAccessToWard($address->ward_id), 403, 'You do not have access to this ward.');

        $address->update([
            'do_not_knock' => true,
            'do_not_knock_at' => now(),
        ]);

        $redirect = back()->with('success', 'Address marked as Do Not Knock');
        
        if ($request->has('search')) {
            $redirect->withInput(['search' => $request->input('search')]);
        }
        
        return $redirect;
    }

    public function clearDoNotKnock(Request $request, Address $address)
    {
        abort_if(!auth()->user()->hasAccessToWard($address->ward_id), 403, 'You do not have access to this ward.');

        $address->update([
            'do_not_knock' => false,
            'do_not_knock_at' => null,
        ]);

        $redirect = back()->with('success', 'Do Not Knock status cleared');
        
        if ($request->has('search')) {
            $redirect->withInput(['search' => $request->input('search')]);
        }
        
        return $redirect;
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

        abort_if(!auth()->user()->hasAccessToWard($validated['ward_id']), 403, 'You do not have access to this ward.');

        // Check for duplicate
        $exists = Address::where('ward_id', $validated['ward_id'])
            ->where('house_number', $validated['house_number'])
            ->where('street_name', $validated['street_name'])
            ->where('postcode', $validated['postcode'])
            ->exists();

        if ($exists) {
            $redirect = redirect()->back()
                ->with('error', 'This address already exists');
            
            if ($request->has('search')) {
                $redirect->withInput(['search' => $request->input('search')]);
            }
            
            return $redirect;
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
            'constituency' => config('canvassing.default_constituency'),
            'sort_order' => $sortOrder,
        ]);

        $redirect = redirect()->back()
            ->with('success', 'Address added successfully');
        
        if ($request->has('search')) {
            $redirect->withInput(['search' => $request->input('search')]);
        }
        
        return $redirect;
    }}
