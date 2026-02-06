<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeatureFlag extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Check if a feature is enabled by key.
     */
    public static function isEnabled(string $key): bool
    {
        return Cache::remember("feature_flag:{$key}", 3600, function () use ($key) {
            $flag = self::where('key', $key)->first();
            return $flag ? $flag->is_enabled : false;
        });
    }

    /**
     * Clear the cache for a specific feature flag.
     */
    public function clearCache(): void
    {
        Cache::forget("feature_flag:{$this->key}");
    }

    /**
     * Boot method to clear cache when updated.
     */
    protected static function booted(): void
    {
        static::updated(function (FeatureFlag $flag) {
            $flag->clearCache();
        });
    }
}
