<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnockResult extends Model
{
    protected $fillable = [
        'address_id',
        'user_id',
        'response',
        'vote_likelihood',
        'notes',
        'knocked_at',
    ];

    protected $casts = [
        'knocked_at' => 'datetime',
    ];

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'your_party' => 'Your Party',
            'undecided' => 'Undecided',
            'refused' => 'Refused to Say',
            'other' => 'Other Party',
        ];
    }
}
