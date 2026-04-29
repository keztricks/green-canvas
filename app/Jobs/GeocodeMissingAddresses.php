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

    public int $timeout = 600;

    public function handle(): void
    {
        $apiKey = config('services.os_places.key');

        $postcodes = Address::whereNull('latitude')
            ->distinct()
            ->pluck('postcode')
            ->filter()
            ->values()
            ->all();

        $fallbackNeeded = [];

        if ($apiKey) {
            foreach ($postcodes as $postcode) {
                $osResults = GeocodeAddresses::fetchOsPlaces($postcode, $apiKey);

                $addresses = Address::where('postcode', $postcode)
                    ->whereNull('latitude')
                    ->get();

                foreach ($addresses as $address) {
                    $coords = GeocodeAddresses::matchAddress($address, $osResults);
                    if ($coords) {
                        $address->update(['latitude' => $coords['lat'], 'longitude' => $coords['lng']]);
                    } else {
                        $fallbackNeeded[] = $postcode;
                        break;
                    }
                }
            }
        } else {
            $fallbackNeeded = $postcodes;
        }

        foreach (array_chunk(array_unique($fallbackNeeded), 100) as $batch) {
            $results = GeocodeAddresses::lookupPostcodes(array_values($batch));
            foreach ($results as $postcode => $coords) {
                if ($coords) {
                    Address::where('postcode', $postcode)
                        ->whereNull('latitude')
                        ->update(['latitude' => $coords['lat'], 'longitude' => $coords['lng']]);
                }
            }
        }
    }
}
