<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnockResult extends Model
{
    protected $fillable = [
        'address_id',
        'response',
        'notes',
        'canvasser_name',
        'knocked_at',
    ];

    protected $casts = [
        'knocked_at' => 'datetime',
    ];

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public static function responseOptions(): array
    {
        return [
            'not_home' => 'Not Home',
            'conservative' => 'Conservative',
            'labour' => 'Labour',
            'lib_dem' => 'Liberal Democrat',
            'green' => 'Green Party',
            'reform' => 'Reform UK',
            'undecided' => 'Undecided',
            'refused' => 'Refused to Say',
            'moved' => 'Moved Away',
            'other' => 'Other Party',
        ];
    }
}
