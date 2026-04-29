<?php

namespace App\Jobs;

use App\Console\Commands\GeocodeAddresses;
use App\Models\Address;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeocodeMissingAddresses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(): void
    {
        $postcodes = Address::whereNull('latitude')
            ->distinct()
            ->pluck('postcode')
            ->filter()
            ->values()
            ->all();

        foreach (array_chunk($postcodes, 100, false) as $batch) {
            $results = GeocodeAddresses::lookupPostcodes($batch);
            foreach ($results as $postcode => $coords) {
                if ($coords) {
                    Address::where('postcode', $postcode)
                        ->update(['latitude' => $coords['lat'], 'longitude' => $coords['lng']]);
                }
            }
        }
    }
}
