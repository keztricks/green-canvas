<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    protected $fillable = [
        'filename',
        'record_count',
        'version',
        'notes',
        'ward_id',
        'date_from',
        'date_to',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }
}
