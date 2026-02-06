<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWardExportSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'ward_id',
        'frequency',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }
}
