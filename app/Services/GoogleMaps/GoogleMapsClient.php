<?php

namespace App\Services\GoogleMaps;

use App\Exceptions\GoogleMapsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GoogleMapsClient
{
    private const BASE_URL = 'https://maps.googleapis.com/maps/api';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.key', '');
    }

    /**
     * Geocode a human-readable address to lat/lng coordinates.
     *
     * @throws GoogleMapsException
     */
    public function geocode(string $address): array
    {
        $response = $this->client()->get(self::BASE_URL . '/geocode/json', [
            'address' => $address,
            'key'     => $this->apiKey,
        ])->json();

        if (($response['status'] ?? '') !== 'OK' || empty($response['results'])) {
            throw GoogleMapsException::fromStatus($response['status'] ?? 'UNKNOWN');
        }

        $location = $response['results'][0]['geometry']['location'];

        return [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
        ];
    }

    /**
     * Fetch travel times between multiple origins and destinations.
     *
     * @param  array  $origins       Array of ['lat' => float, 'lng' => float]
     * @param  array  $destinations  Array of ['lat' => float, 'lng' => float]
     * @param  string $mode          'car' or 'public_transport'
     * @return array  Raw rows array from Distance Matrix response
     *
     * @throws GoogleMapsException
     */
    public function distanceMatrix(array $origins, array $destinations, string $mode = 'driving'): array
    {
        $modeMap = [
            'car'              => 'driving',
            'public_transport' => 'transit',
        ];

        $resolvedMode = $modeMap[$mode] ?? $mode;

        $formatPoints = static fn(array $points): string => implode('|', array_map(
            static fn(array $p): string => "{$p['lat']},{$p['lng']}",
            $points
        ));

        $response = $this->client()->get(self::BASE_URL . '/distancematrix/json', [
            'origins'      => $formatPoints($origins),
            'destinations' => $formatPoints($destinations),
            'mode'         => $resolvedMode,
            'key'          => $this->apiKey,
        ])->json();

        if (($response['status'] ?? '') !== 'OK') {
            throw GoogleMapsException::fromStatus($response['status'] ?? 'UNKNOWN');
        }

        return $response['rows'];
    }

    /**
     * Find places near a coordinate by type.
     *
     * @throws GoogleMapsException
     */
    public function nearbySearch(float $lat, float $lng, string $type, int $radius = 5000): array
    {
        $response = $this->client()->get(self::BASE_URL . '/place/nearbysearch/json', [
            'location' => "{$lat},{$lng}",
            'type'     => $type,
            'radius'   => $radius,
            'key'      => $this->apiKey,
        ])->json();

        $status = $response['status'] ?? '';

        if (!in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw GoogleMapsException::fromStatus($status);
        }

        return $response['results'] ?? [];
    }

    /**
     * Search for places near a coordinate by text query.
     */
    public function textSearch(float $lat, float $lng, string $query, int $radius = 5000): array
    {
        $response = $this->client()->get(self::BASE_URL . '/place/textsearch/json', [
            'query'    => $query,
            'location' => "{$lat},{$lng}",
            'radius'   => $radius,
            'key'      => $this->apiKey,
        ])->json();

        return $response['results'] ?? [];
    }

    /**
     * Address autocomplete predictions, biased to a region.
     *
     * @return array Raw predictions array
     *
     * @throws GoogleMapsException
     */
    public function placeAutocomplete(string $input, ?string $sessionToken = null): array
    {
        $bounds = (array) config('jumpa.boundary');

        $response = $this->client()->get(self::BASE_URL . '/place/autocomplete/json', array_filter([
            'input'        => $input,
            'components'   => 'country:my',
            'location'     => sprintf(
                '%s,%s',
                ($bounds['lat_min'] + $bounds['lat_max']) / 2,
                ($bounds['lng_min'] + $bounds['lng_max']) / 2,
            ),
            'radius'       => 50000,
            'sessiontoken' => $sessionToken,
            'key'          => $this->apiKey,
        ]))->json();

        $status = $response['status'] ?? '';

        if (!in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw GoogleMapsException::fromStatus($status);
        }

        return $response['predictions'] ?? [];
    }

    /**
     * Resolve a place_id to its coordinates and formatted address.
     *
     * @throws GoogleMapsException
     */
    public function placeDetails(string $placeId, ?string $sessionToken = null): array
    {
        $response = $this->client()->get(self::BASE_URL . '/place/details/json', array_filter([
            'place_id'     => $placeId,
            'fields'       => 'geometry/location,formatted_address,name',
            'sessiontoken' => $sessionToken,
            'key'          => $this->apiKey,
        ]))->json();

        if (($response['status'] ?? '') !== 'OK' || empty($response['result'])) {
            throw GoogleMapsException::fromStatus($response['status'] ?? 'UNKNOWN');
        }

        $result   = $response['result'];
        $location = $result['geometry']['location'] ?? null;

        if ($location === null) {
            throw GoogleMapsException::fromStatus('ZERO_RESULTS');
        }

        return [
            'place_id' => $placeId,
            'name'     => $result['name'] ?? null,
            'address'  => $result['formatted_address'] ?? '',
            'lat'      => (float) $location['lat'],
            'lng'      => (float) $location['lng'],
        ];
    }

    /**
     * Reverse-geocode coordinates to a human-readable address.
     *
     * @throws GoogleMapsException
     */
    public function reverseGeocode(float $lat, float $lng): string
    {
        $response = $this->client()->get(self::BASE_URL . '/geocode/json', [
            'latlng' => "{$lat},{$lng}",
            'key'    => $this->apiKey,
        ])->json();

        $status = $response['status'] ?? '';

        if (!in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw GoogleMapsException::fromStatus($status);
        }

        return $response['results'][0]['formatted_address']
            ?? sprintf('%.6f, %.6f', $lat, $lng);
    }

    /**
     * A slow/hanging Google API call must not be able to block a request indefinitely
     * (e.g. the synchronous midpoint recalculation on every participant join) — cap
     * the wait and retry transient failures with a short backoff before giving up.
     */
    private function client(): PendingRequest
    {
        return Http::timeout(5)->retry(2, 200);
    }
}
