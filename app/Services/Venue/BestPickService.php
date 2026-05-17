<?php

namespace App\Services\Venue;

class BestPickService
{
    /**
     * Mark the best overall venue with isBestPick = true.
     *
     * For each venue the final score is:
     *   final_score = (ranking_score * 0.5) + (fairness_score * 0.5)
     *
     * Fairness measures how balanced the travel times are across all participants:
     *   fairness = 1 - (std_deviation / mean)  clamped to [0, 1]
     *
     * @param  array $venueResults  Array of associative arrays produced by VenueResult::toArray()
     * @return array                Same array with is_best_pick set on the winner
     */
    public function selectBestPick(array $venueResults): array
    {
        if (empty($venueResults)) {
            return $venueResults;
        }

        $bestIndex      = 0;
        $bestFinalScore = -INF;

        foreach ($venueResults as $index => $venue) {
            $fairness     = $this->calculateFairness($venue['travel_times'] ?? []);
            $rankingScore = (float) ($venue['score'] ?? 0.0);
            $finalScore   = ($rankingScore * 0.5) + ($fairness * 0.5);

            if ($finalScore > $bestFinalScore) {
                $bestFinalScore = $finalScore;
                $bestIndex      = $index;
            }
        }

        $venueResults[$bestIndex]['is_best_pick'] = true;

        return $venueResults;
    }

    /**
     * fairness = 1 - (std_deviation(seconds) / mean(seconds)), clamped to [0, 1].
     *
     * Returns 1.0 for a single participant (perfect fairness by definition)
     * and 0.0 when there are no travel times recorded.
     */
    public function calculateFairness(array $travelTimes): float
    {
        if (empty($travelTimes)) {
            return 0.0;
        }

        $seconds = array_column($travelTimes, 'seconds');
        $count   = count($seconds);

        if ($count === 1) {
            return 1.0;
        }

        $mean = array_sum($seconds) / $count;

        if ($mean == 0.0) {
            return 1.0;
        }

        $variance = array_sum(array_map(fn(int $s) => ($s - $mean) ** 2, $seconds)) / $count;
        $stdDev   = sqrt($variance);

        return max(0.0, 1.0 - ($stdDev / $mean));
    }
}
