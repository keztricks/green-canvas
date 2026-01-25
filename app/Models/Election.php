<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Election extends Model
{
    protected $fillable = [
        'name',
        'election_date',
        'type',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'election_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public function addresses()
    {
        return $this->belongsToMany(Address::class)
            ->withPivot('voted', 'notes')
            ->withTimestamps();
    }

    public function wards()
    {
        return $this->belongsToMany(Ward::class);
    }
}
