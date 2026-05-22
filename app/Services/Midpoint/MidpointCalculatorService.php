<?php

namespace App\Services\Midpoint;

use App\Services\GoogleMaps\CachedGoogleMapsClient;
use Illuminate\Support\Facades\Log;

class MidpointCalculatorService
{
    public function __construct(
        private readonly CachedGoogleMapsClient $mapsClient,
    ) {}

    /**
     * Calculate the geographic centroid of a set of coordinates.
     *
     * @param  array  $participants  Array of ['lat' => float, 'lng' => float]
     * @return array                 ['lat' => float, 'lng' => float]
     */
    public function calculateCentroid(array $participants): array
    {
        $count = count($participants);

        return [
            'lat' => array_sum(array_column($participants, 'lat')) / $count,
            'lng' => array_sum(array_column($participants, 'lng')) / $count,
        ];
    }

    /**
     * Return true when the spread of travel times is within the acceptable threshold.
     *
     * @param  array  $travelTimes  Flat array of travel times in seconds
     */
    public function validateFairness(array $travelTimes): bool
    {
        if (count($travelTimes) < 2) {
            return true;
        }

        $maxImbalanceSeconds = (int) config('jumpa.midpoint.max_imbalance_minutes') * 60;

        return (max($travelTimes) - min($travelTimes)) <= $maxImbalanceSeconds;
    }

    /**
     * Shift the centroid one step toward the participant with the longest travel time.
     *
     * @param  array  $centroid      ['lat' => float, 'lng' => float]
     * @param  array  $participants  Array of ['lat' => float, 'lng' => float], same order as $travelTimes
     * @param  array  $travelTimes   Flat array of travel times in seconds, same index as $participants
     * @return array                 Adjusted ['lat' => float, 'lng' => float]
     */
    public function adjustCentroid(array $centroid, array $participants, array $travelTimes): array
    {
        // Find the index of the participant with the longest travel time
        $sorted = $travelTimes;
        arsort($sorted);
        $outlierIndex = array_key_first($sorted);

        $outlier = $participants[$outlierIndex];
        $step    = (float) config('jumpa.midpoint.adjustment_step');

        return [
            'lat' => $centroid['lat'] + ($outlier['lat'] - $centroid['lat']) * $step,
            'lng' => $centroid['lng'] + ($outlier['lng'] - $centroid['lng']) * $step,
        ];
    }

    /**
     * Calculate the fairest midpoint for the given participants.
     *
     * @param  array   $participants  Array of Participant models (must have id, latitude, longitude)
     * @param  string  $transportMode 'car' or 'public_transport'
     */
    public function calculate(array $participants, string $transportMode): MidpointResult
    {
        // Reindex to guarantee 0-based integer keys throughout
        $participants = array_values($participants);

        if (count($participants) === 1) {
            $p = $participants[0];

            return MidpointResult::make([
                'lat'            => (float) $p->latitude,
                'lng'            => (float) $p->longitude,
                'travel_times'   => [$p->id => 0],
                'is_fair'        => true,
                'iterations'     => 0,
                'transport_mode' => $transportMode,
            ]);
        }

        $coords = array_map(
            static fn($p): array => ['lat' => (float) $p->latitude, 'lng' => (float) $p->longitude],
            $participants
        );

        $centroid      = $this->calculateCentroid($coords);
        $maxIterations = (int) config('jumpa.midpoint.max_adjustment_iterations');
        $iterations    = 0;
        $isFair        = false;
        $times         = [];

        do {
            $rows = $this->mapsClient->distanceMatrix($coords, [$centroid], $transportMode);

            $times = [];
            foreach ($participants as $i => $participant) {
                $times[$i] = (int) ($rows[$i]['elements'][0]['duration']['value'] ?? 0);
            }

            $isFair = $this->validateFairness($times);
            $iterations++;

            if (!$isFair && $iterations < $maxIterations) {
                $centroid = $this->adjustCentroid($centroid, $coords, $times);
            }
        } while (!$isFair && $iterations < $maxIterations);

        if (!$isFair) {
            Log::warning('MidpointCalculator: max iterations reached without achieving fairness.', [
                'centroid'   => $centroid,
                'iterations' => $iterations,
            ]);
        }

        // Build the participant_id => seconds map for the result
        $travelTimesById = [];
        foreach ($participants as $i => $participant) {
            $travelTimesById[$participant->id] = $times[$i];
        }

        return MidpointResult::make([
            'lat'            => $centroid['lat'],
            'lng'            => $centroid['lng'],
            'travel_times'   => $travelTimesById,
            'is_fair'        => $isFair,
            'iterations'     => $iterations,
            'transport_mode' => $transportMode,
        ]);
    }
}
