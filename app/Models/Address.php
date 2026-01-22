<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    protected $fillable = [
        'house_number',
        'street_name',
        'town',
        'postcode',
        'constituency',
        'sort_order',
    ];

    public function knockResults(): HasMany
    {
        return $this->hasMany(KnockResult::class);
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
}
