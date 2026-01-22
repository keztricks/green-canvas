<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Canvasser extends Model
{
    protected $fillable = [
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
