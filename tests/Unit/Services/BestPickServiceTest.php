<?php

namespace Tests\Unit\Services;

use App\Services\Venue\BestPickService;
use Tests\TestCase;

class BestPickServiceTest extends TestCase
{
    private BestPickService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BestPickService();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeVenueArray(float $score, array $secondsPerParticipant): array
    {
        $travelTimes = [];
        foreach ($secondsPerParticipant as $i => $seconds) {
            $travelTimes["participant-{$i}"] = ['seconds' => $seconds, 'text' => "{$seconds}s"];
        }

        return [
            'place_id'             => 'ChIJ' . md5((string) $score . implode(',', $secondsPerParticipant)),
            'name'                 => "Venue score={$score}",
            'address'              => '1 Jalan Test, KL',
            'lat'                  => 3.1579,
            'lng'                  => 101.7119,
            'type'                 => 'restaurant',
            'rating'               => 4.0,
            'is_halal'             => null,
            'is_halal_verified'    => false,
            'distance_meters'      => 1000,
            'photo_url'            => null,
            'google_maps_url'      => 'https://maps.google.com',
            'source'               => 'seeded',
            'travel_times'         => $travelTimes,
            'best_for_participant' => null,
            'is_best_pick'         => false,
            'score'                => $score,
        ];
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    /**
     * Venue A: score=0.8, times=[900,950]  → fairness≈0.973, final≈0.887  ← winner
     * Venue B: score=0.9, times=[300,1800] → fairness≈0.286, final≈0.593
     * Venue C: score=0.7, times=[600,700]  → fairness≈0.923, final≈0.812
     */
    public function test_best_pick_selected_from_highest_combined_score(): void
    {
        $venueA = $this->makeVenueArray(0.8, [900, 950]);
        $venueB = $this->makeVenueArray(0.9, [300, 1800]);
        $venueC = $this->makeVenueArray(0.7, [600, 700]);

        $result = $this->service->selectBestPick([$venueA, $venueB, $venueC]);

        // Exactly one venue must be the best pick
        $bestPicks = array_filter($result, static fn($v) => $v['is_best_pick'] === true);
        $this->assertCount(1, $bestPicks);

        // Venue A has the highest combined score
        $winner = reset($bestPicks);
        $this->assertSame($venueA['place_id'], $winner['place_id']);
    }

    public function test_fairness_score_is_higher_for_balanced_travel_times(): void
    {
        // Balanced: times close together → low coefficient of variation → high fairness
        $balanced   = ['p1' => ['seconds' => 900], 'p2' => ['seconds' => 950]];

        // Imbalanced: one participant has 6× the travel time of the other
        $imbalanced = ['p1' => ['seconds' => 300], 'p2' => ['seconds' => 1800]];

        $fairnessBalanced   = $this->service->calculateFairness($balanced);
        $fairnessImbalanced = $this->service->calculateFairness($imbalanced);

        $this->assertGreaterThan(
            $fairnessImbalanced,
            $fairnessBalanced,
            "Balanced travel times should produce a higher fairness score than imbalanced ones."
        );

        // Balanced fairness should be close to 1.0
        $this->assertGreaterThan(0.9, $fairnessBalanced);

        // Imbalanced fairness should be well below balanced
        $this->assertLessThan(0.5, $fairnessImbalanced);
    }
}
