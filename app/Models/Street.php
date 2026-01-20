<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Street extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'street_norm',
        'display_name',
        'assigned_to',
        'lock_until',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'lock_until' => 'datetime',
    ];

    /**
     * Get the addresses for the street.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the volunteer assigned to the street.
     */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class, 'assigned_to');
    }
}
