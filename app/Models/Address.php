<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
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

    public function scopeByElectionStatus($query, array $electionFilters)
    {
        // If no election filters selected, return all addresses
        if (empty($electionFilters)) {
            return $query;
        }

        // ANY logic: address must match AT LEAST ONE selected election
        // For each election, ANY of its selected statuses can match
        $query->where(function($q) use ($electionFilters) {
            foreach ($electionFilters as $electionId => $statuses) {
                if (empty($statuses)) {
                    continue;
                }
                
                $q->orWhereHas('elections', function ($subQ) use ($electionId, $statuses) {
                    $subQ->where('election_id', $electionId)
                         ->whereIn('address_election.status', $statuses);
                });
            }
        });

        return $query;
    }
}
