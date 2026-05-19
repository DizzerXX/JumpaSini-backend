<?php

namespace App\Services\Venue;

use App\Helpers\GeoHelper;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Services\GoogleMaps\CachedGoogleMapsClient;
use App\Services\Weather\WeatherAssessmentService;

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
        private readonly CachedGoogleMapsClient    $mapsClient,
        private readonly VenueRepositoryInterface  $venueRepository,
        private readonly WeatherAssessmentService  $weatherAssessment,
    ) {}

    public function discover(
        float  $lat,
        float  $lng,
        string $venueType,
        bool   $halalFilter,
        string $transportMode,
        array  $participants = [],
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

        // Step 5 — rank top N (also sets score on each VenueResult)
        $ranked = $this->rankVenues($all);

        // Step 6 — enrich with per-participant travel times
        $this->enrichWithTravelTimes($ranked, $participants, $transportMode);

        // Step 7 — weather assessment (outdoor venue types only)
        $outdoorTypes = (array) config('jumpa.weather.outdoor_types', []);
        $weather      = null;

        if (in_array($venueType, $outdoorTypes, true) && config('jumpa.weather.enabled', true)) {
            try {
                $weather = $this->weatherAssessment->assess($lat, $lng, $venueType);
            } catch (\Throwable) {
                // Weather failure must not block venue results
            }
        }

        return [
            'venues'  => array_map(static fn(VenueResult $v) => $v->toArray(), $ranked),
            'weather' => $weather,
        ];
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

    public function rankVenues(array $venues): array
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
            $score         = $ratingScore + $distanceScore;
            $venue->score  = $score;

            return ['venue' => $venue, 'score' => $score];
        }, $venues);

        usort($scored, static fn(array $a, array $b) => $b['score'] <=> $a['score']);

        return array_map(
            static fn(array $item) => $item['venue'],
            array_slice($scored, 0, self::TOP_N)
        );
    }

    /**
     * Enrich each VenueResult with per-participant travel times via Distance Matrix API.
     * On API failure the venue's travelTimes stays as an empty array.
     *
     * @param  VenueResult[] $venues
     * @param  array         $participants  Participant model instances
     */
    private function enrichWithTravelTimes(array $venues, array $participants, string $transportMode): void
    {
        if (empty($participants) || empty($venues)) {
            return;
        }

        $origins = array_map(
            static fn($p) => ['lat' => (float) $p->latitude, 'lng' => (float) $p->longitude],
            $participants
        );

        foreach ($venues as $venue) {
            try {
                $rows = $this->mapsClient->distanceMatrix(
                    origins:      $origins,
                    destinations: [['lat' => $venue->lat, 'lng' => $venue->lng]],
                    mode:         $transportMode,
                );

                $travelTimes       = [];
                $minSeconds        = PHP_INT_MAX;
                $bestParticipantId = null;

                foreach ($participants as $i => $participant) {
                    $element = $rows[$i]['elements'][0] ?? null;

                    if ($element && ($element['status'] ?? '') === 'OK') {
                        $seconds = (int) ($element['duration']['value'] ?? 0);
                        $text    = (string) ($element['duration']['text'] ?? '');

                        $travelTimes[$participant->id] = ['seconds' => $seconds, 'text' => $text];

                        if ($seconds < $minSeconds) {
                            $minSeconds        = $seconds;
                            $bestParticipantId = $participant->id;
                        }
                    }
                }

                $venue->travelTimes        = $travelTimes;
                $venue->bestForParticipant = $bestParticipantId;
            } catch (\Throwable) {
                // Leave travelTimes empty if API call fails
            }
        }
    }
}
