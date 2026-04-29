<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_id',
        'house_number',
        'street_name',
        'town',
        'postcode',
        'constituency',
        'sort_order',
        'elector_count',
        'do_not_knock',
        'do_not_knock_at',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'do_not_knock' => 'boolean',
        'do_not_knock_at' => 'datetime',
    ];

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function knockResults(): HasMany
    {
        return $this->hasMany(KnockResult::class);
    }

    public function elections()
    {
        return $this->belongsToMany(Election::class)
            ->withPivot('status', 'notes')
            ->withTimestamps();
    }

    public function latestResult()
    {
        return $this->knockResults()->latest('knocked_at')->first();
    }

    public function getFullAddressAttribute(): string
    {
        return "{$this->house_number} {$this->street_name}, {$this->town}, {$this->postcode}";
    }

    public function scopeByStreet($query, string $streetName)
    {
        return $query->where('street_name', $streetName)->orderBy('sort_order');
    }

    public function scopeByWard($query, int $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    public function scopeByKnockResponse($query, array $responses, array $likelihoods): void
    {
        if (empty($responses) && empty($likelihoods)) {
            return;
        }

        $query->whereHas('knockResults', function ($q) use ($responses, $likelihoods) {
            $q->whereRaw('knocked_at = (SELECT MAX(kr2.knocked_at) FROM knock_results kr2 WHERE kr2.address_id = knock_results.address_id)');
            if (!empty($responses)) {
                $q->whereIn('response', $responses);
            }
            if (!empty($likelihoods)) {
                $q->whereIn('vote_likelihood', $likelihoods);
            }
        });
    }

    public function scopeByElectionStatus($query, array $electionFilters)
    {
        // If no election filters selected, return all addresses
        if (empty($electionFilters)) {
            return $query;
        }

        // ANY logic: address must match AT LEAST ONE selected election/status combination
        $query->where(function($q) use ($electionFilters) {
            foreach ($electionFilters as $electionId => $statuses) {
                if (empty($statuses)) {
                    continue;
                }
                
                // Check if 'unknown' is in the selected statuses
                $hasUnknown = in_array('unknown', $statuses);
                $otherStatuses = array_diff($statuses, ['unknown']);
                
                // Build OR condition for this election
                $q->orWhere(function($subQ) use ($electionId, $hasUnknown, $otherStatuses) {
                    // If there are non-unknown statuses, check for addresses with those statuses
                    if (!empty($otherStatuses)) {
                        $subQ->whereHas('elections', function ($electionQ) use ($electionId, $otherStatuses) {
                            $electionQ->where('election_id', $electionId)
                                     ->whereIn('address_election.status', $otherStatuses);
                        });
                    }
                    
                    // If 'unknown' is selected, also include addresses with status='unknown' 
                    // OR addresses that don't have any record for this election
                    if ($hasUnknown) {
                        if (!empty($otherStatuses)) {
                            // If we already added other statuses, use OR
                            $subQ->orWhereHas('elections', function ($electionQ) use ($electionId) {
                                $electionQ->where('election_id', $electionId)
                                         ->where('address_election.status', 'unknown');
                            });
                            $subQ->orWhereDoesntHave('elections', function ($electionQ) use ($electionId) {
                                $electionQ->where('election_id', $electionId);
                            });
                        } else {
                            // Only unknown is selected, so we need whereHas OR doesntHave
                            $subQ->where(function($unknownQ) use ($electionId) {
                                $unknownQ->whereHas('elections', function ($electionQ) use ($electionId) {
                                    $electionQ->where('election_id', $electionId)
                                             ->where('address_election.status', 'unknown');
                                })
                                ->orWhereDoesntHave('elections', function ($electionQ) use ($electionId) {
                                    $electionQ->where('election_id', $electionId);
                                });
                            });
                        }
                    }
                });
            }
        });

        return $query;
    }

}
