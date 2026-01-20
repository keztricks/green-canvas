<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Services\AddressNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Search for addresses.
     */
    public function search(Request $request, AddressNormalizer $normalizer): JsonResponse
    {
        $query = $request->input('q', '');

        if (empty($query)) {
            return response()->json([]);
        }

        // Normalize the query
        $normalized = $normalizer->normText($query);
        $postcode = $normalizer->findPostcode($query);
        $parts = $normalizer->extractHouseAndStreet($query);

        $candidates = [];

        // Try exact lookup by postcode + house number if both present
        if ($postcode && $parts['house_number']) {
            $candidates = Address::where('postcode', $postcode)
                ->where('house_number', $parts['house_number'])
                ->limit(10)
                ->get();

            if ($candidates->isNotEmpty()) {
                return response()->json($candidates);
            }
        }

        // Try fulltext search
        $fulltextResults = DB::select(
            "SELECT id, raw_address, postcode, house_number, street_name, status, lat, lon,
                    MATCH(norm) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
             FROM addresses
             WHERE MATCH(norm) AGAINST(? IN NATURAL LANGUAGE MODE)
             ORDER BY relevance DESC
             LIMIT 50",
            [$normalized, $normalized]
        );

        if (!empty($fulltextResults)) {
            $candidates = collect($fulltextResults);
        } else {
            // Fallback to LIKE search with tokens
            $tokens = explode(' ', $normalized);
            $query = Address::query();

            foreach ($tokens as $token) {
                if (strlen($token) >= 2) {
                    $query->where('norm', 'LIKE', '%' . $token . '%');
                }
            }

            $candidates = $query->limit(50)->get();
        }

        // Compute Levenshtein distance for ranking
        $rankedCandidates = $candidates->map(function ($address) use ($normalized) {
            // Convert to array if it's an object
            $addressArray = is_object($address) ? (array) $address : $address;
            
            $addressNorm = $addressArray['norm'] ?? 
                          ($address instanceof Address ? $address->norm : '');
            
            $distance = levenshtein(
                substr($normalized, 0, 255),
                substr($addressNorm, 0, 255)
            );

            return array_merge($addressArray, ['distance' => $distance]);
        })
        ->sortBy('distance')
        ->take(10)
        ->values();

        return response()->json($rankedCandidates);
    }
}
