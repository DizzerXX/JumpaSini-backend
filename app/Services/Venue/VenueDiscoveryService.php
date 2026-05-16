<?php

namespace App\Services\Venue;

use App\Helpers\GeoHelper;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Services\GoogleMaps\CachedGoogleMapsClient;

class VenueDiscoveryService
{
    private const NEARBY_RADIUS  = 10000;
    private const TEXT_RADIUS    = 25000;
    private const TOP_N          = 5;
    private const MIN_GOOGLE_RESULTS = 3;

    private const GOOGLE_TYPE_MAP = [
        'mall'       => ['type' => 'shopping_mall', 'useText' => false],
        'restaurant' => ['type' => 'restaurant',    'useText' => false],
        'cafe'       => ['type' => 'cafe',          'useText' => false],
        'park'       => ['type' => 'park',           'useText' => false],
        'beach'      => ['query' => 'beach',         'useText' => true],
        'waterfall'  => ['query' => 'waterfall Malaysia', 'useText' => true],
        'river'      => ['query' => 'river activities',   'useText' => true],
    ];

    public function __construct(
        private readonly CachedGoogleMapsClient $mapsClient,
        private readonly VenueRepositoryInterface $venueRepository,
    ) {}

    public function discover(
        float  $lat,
        float  $lng,
        string $venueType,
        bool   $halalFilter,
        string $transportMode,
    ): array {
        $config = self::GOOGLE_TYPE_MAP[$venueType] ?? ['query' => $venueType, 'useText' => true];

        // Step 1 — fetch from Google Places
        $googleResults = $this->fetchFromGoogle($lat, $lng, $venueType, $config, $halalFilter);

        // Step 2 — fetch seeded fallback (always queried; merged below)
        $seededResults = $this->fetchFromSeeded($lat, $lng, $venueType, $halalFilter);

        // Step 3 — build VenueResult objects, tag with venue type for fromGoogle()
        $all = [];
        $seenPlaceIds = [];

        foreach ($googleResults as $place) {
            $place['_venue_type'] = $venueType;
            $result = VenueResult::fromGoogle($place, $lat, $lng);
            $all[] = $result;
            if ($result->placeId) {
                $seenPlaceIds[$result->placeId] = true;
            }
        }

        // Step 4 — merge seeded, deduplicate by google_place_id
        foreach ($seededResults as $venue) {
            $key = $venue->google_place_id ?? $venue->id;
            if (isset($seenPlaceIds[$key])) {
                continue;
            }
            $all[] = VenueResult::fromSeeded($venue, $lat, $lng);
        }

        // Step 5 — rank and return top N
        $ranked = $this->rankVenues($all);

        return array_map(static fn(VenueResult $v) => $v->toArray(), $ranked);
    }

    private function fetchFromGoogle(float $lat, float $lng, string $venueType, array $config, bool $halalFilter): array
    {
        try {
            if ($config['useText']) {
                $query = $config['query'];
                if ($halalFilter && in_array($venueType, ['restaurant', 'cafe'], true)) {
                    $query .= ' halal';
                }
                return $this->mapsClient->textSearch($lat, $lng, $query, self::TEXT_RADIUS);
            }

            if ($halalFilter && in_array($venueType, ['restaurant', 'cafe'], true)) {
                return $this->mapsClient->textSearch($lat, $lng, $config['type'] . ' halal', self::NEARBY_RADIUS);
            }

            return $this->mapsClient->nearbySearch($lat, $lng, $config['type'], self::NEARBY_RADIUS);
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchFromSeeded(float $lat, float $lng, string $venueType, bool $halalFilter): \Illuminate\Support\Collection
    {
        $venues = $this->venueRepository->findNearMidpoint($lat, $lng, $venueType, self::TEXT_RADIUS);

        if ($halalFilter && in_array($venueType, ['restaurant', 'cafe'], true)) {
            $venues = $venues->filter(fn($v) => $v->is_halal === true);
        }

        return $venues;
    }

    private function rankVenues(array $venues): array
    {
        if (empty($venues)) {
            return [];
        }

        // Normalise distances for the distance weight component
        $distances = array_map(fn(VenueResult $v) => $v->distanceMeters, $venues);
        $maxDist   = max($distances) ?: 1;

        $scored = array_map(function (VenueResult $venue) use ($maxDist): array {
            $ratingScore   = (($venue->rating ?? 0) / 5.0) * 0.6;
            $normDist      = $venue->distanceMeters / $maxDist;
            $distanceScore = (1 - $normDist) * 0.4;

            return ['venue' => $venue, 'score' => $ratingScore + $distanceScore];
        }, $venues);

        usort($scored, static fn(array $a, array $b) => $b['score'] <=> $a['score']);

        return array_map(
            static fn(array $item) => $item['venue'],
            array_slice($scored, 0, self::TOP_N)
        );
    }
}
