<?php

namespace Tests\Unit\Services;

use App\Services\GoogleMaps\CachedGoogleMapsClient;
use App\Services\Midpoint\MidpointCalculatorService;
use Mockery;
use Tests\TestCase;

class MidpointCalculatorServiceTest extends TestCase
{
    private MidpointCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Pure calculation methods don't call the maps client — a bare mock is enough.
        $this->service = new MidpointCalculatorService(
            Mockery::mock(CachedGoogleMapsClient::class)
        );
    }

    public function test_centroid_calculation_averages_coordinates_correctly(): void
    {
        $participants = [
            ['lat' => 3.1579, 'lng' => 101.7119],  // KLCC
            ['lat' => 3.0456, 'lng' => 101.5797],  // Subang Jaya
        ];

        $centroid = $this->service->calculateCentroid($participants);

        // Expected: lat = (3.1579 + 3.0456) / 2 = 3.10175
        //           lng = (101.7119 + 101.5797) / 2 = 101.64580
        $this->assertEqualsWithDelta(3.10175, $centroid['lat'], 0.001);
        $this->assertEqualsWithDelta(101.6458, $centroid['lng'], 0.001);
    }

    public function test_fairness_validation_passes_when_within_threshold(): void
    {
        // Diff = 1500 - 1200 = 300 s = 5 min, threshold = 15 min → fair
        $result = $this->service->validateFairness([1200, 1500]);

        $this->assertTrue($result);
    }

    public function test_fairness_validation_fails_when_exceeds_threshold(): void
    {
        // Diff = 2100 - 600 = 1500 s = 25 min, threshold = 15 min → not fair
        $result = $this->service->validateFairness([600, 2100]);

        $this->assertFalse($result);
    }

    public function test_adjustment_shifts_centroid_toward_outlier(): void
    {
        $centroid = ['lat' => 3.1000, 'lng' => 101.6500];

        // Participant 0: Subang Jaya (low travel time)
        // Participant 1: KLCC (high travel time — the outlier)
        $participants = [
            ['lat' => 3.0456, 'lng' => 101.5797],
            ['lat' => 3.1579, 'lng' => 101.7119],
        ];

        // Participant 1 has the longest travel time
        $travelTimes = [0 => 600, 1 => 2100];

        $adjusted = $this->service->adjustCentroid($centroid, $participants, $travelTimes);

        $outlier = $participants[1];

        // The adjusted centroid must be closer to the outlier than the original centroid
        $originalDist = abs($centroid['lat'] - $outlier['lat']);
        $adjustedDist = abs($adjusted['lat'] - $outlier['lat']);

        $this->assertLessThan($originalDist, $adjustedDist);

        // Sanity: the centroid moved in the right direction (toward KLCC, which is further north)
        $this->assertGreaterThan($centroid['lat'], $adjusted['lat']);
        $this->assertGreaterThan($centroid['lng'], $adjusted['lng']);
    }
}
