<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Election extends Model
{
    use HasFactory;

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
            ->withPivot('status', 'notes')
            ->withTimestamps();
    }

    public function wards()
    {
        return $this->belongsToMany(Ward::class);
    }
}
