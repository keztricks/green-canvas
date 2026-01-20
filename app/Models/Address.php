<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'raw_address',
        'postcode',
        'house_number',
        'street_name',
        'street_norm',
        'norm',
        'street_id',
        'lat',
        'lon',
        'status',
        'last_contacted_at',
        'current_volunteer',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'last_contacted_at' => 'datetime',
        'lat' => 'decimal:8',
        'lon' => 'decimal:8',
    ];

    /**
     * Get the street that the address belongs to.
     */
    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }
}
