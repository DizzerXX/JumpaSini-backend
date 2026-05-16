<?php

namespace App\Services\GoogleMaps;

use App\Exceptions\GoogleMapsException;
use Illuminate\Support\Facades\Cache;

class CachedGoogleMapsClient
{
    public function __construct(
        private readonly GoogleMapsClient $client,
    ) {}

    /**
     * Geocode is NOT cached — addresses may resolve differently over time.
     *
     * @throws GoogleMapsException
     */
    public function geocode(string $address): array
    {
        return $this->client->geocode($address);
    }

    /**
     * Distance matrix results cached for distance_ttl seconds.
     *
     * @throws GoogleMapsException
     */
    public function distanceMatrix(array $origins, array $destinations, string $mode = 'driving'): array
    {
        $cacheKey = 'gmaps:distanceMatrix:' . md5(serialize([$origins, $destinations, $mode]));
        $ttl      = (int) config('jumpa.cache.distance_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($origins, $destinations, $mode) {
            return $this->client->distanceMatrix($origins, $destinations, $mode);
        });
    }

    /**
     * Nearby search cached for places_ttl seconds.
     *
     * @throws GoogleMapsException
     */
    public function nearbySearch(float $lat, float $lng, string $type, int $radius = 5000): array
    {
        $cacheKey = 'gmaps:nearbySearch:' . md5(serialize([$lat, $lng, $type, $radius]));
        $ttl      = (int) config('jumpa.cache.places_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lng, $type, $radius) {
            return $this->client->nearbySearch($lat, $lng, $type, $radius);
        });
    }

    /**
     * Text search cached for places_ttl seconds.
     */
    public function textSearch(float $lat, float $lng, string $query, int $radius = 5000): array
    {
        $cacheKey = 'gmaps:textSearch:' . md5(serialize([$lat, $lng, $query, $radius]));
        $ttl      = (int) config('jumpa.cache.places_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lng, $query, $radius) {
            return $this->client->textSearch($lat, $lng, $query, $radius);
        });
    }
}
