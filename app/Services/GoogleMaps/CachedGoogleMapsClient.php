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

    /**
     * Autocomplete predictions, cached on the normalised input.
     *
     * Short TTL relative to places_ttl: these are typed keystrokes, so the win is
     * collapsing repeats across users rather than long-term reuse.
     *
     * A session token is deliberately excluded from the cache key — it exists to group
     * a user's keystrokes for Google's billing and must not fragment the shared cache.
     *
     * @throws \App\Exceptions\GoogleMapsException
     */
    public function placeAutocomplete(string $input, ?string $sessionToken = null): array
    {
        $normalised = mb_strtolower(trim($input));
        $cacheKey   = 'gmaps:autocomplete:' . md5($normalised);
        $ttl        = (int) config('jumpa.cache.autocomplete_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($input, $sessionToken) {
            return $this->client->placeAutocomplete($input, $sessionToken);
        });
    }

    /**
     * Place details cached for places_ttl seconds — a place's coordinates are stable.
     *
     * @throws \App\Exceptions\GoogleMapsException
     */
    public function placeDetails(string $placeId, ?string $sessionToken = null): array
    {
        $cacheKey = 'gmaps:placeDetails:' . md5($placeId);
        $ttl      = (int) config('jumpa.cache.places_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($placeId, $sessionToken) {
            return $this->client->placeDetails($placeId, $sessionToken);
        });
    }

    /**
     * Reverse geocode cached for places_ttl seconds, keyed on ~11m-precision coords.
     *
     * @throws \App\Exceptions\GoogleMapsException
     */
    public function reverseGeocode(float $lat, float $lng): string
    {
        $cacheKey = sprintf('gmaps:reverseGeocode:%.4f,%.4f', $lat, $lng);
        $ttl      = (int) config('jumpa.cache.places_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lng) {
            return $this->client->reverseGeocode($lat, $lng);
        });
    }
}
