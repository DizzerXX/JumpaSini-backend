<?php

namespace Tests\Unit\Services;

use App\Helpers\GeoHelper;
use App\Models\Venue;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Services\GoogleMaps\CachedGoogleMapsClient;
use App\Services\Venue\VenueDiscoveryService;
use App\Services\Venue\VenueResult;
use App\Services\Weather\WeatherAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VenueDiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(?CachedGoogleMapsClient $maps = null): VenueDiscoveryService
    {
        return new VenueDiscoveryService(
            $maps ?? Mockery::mock(CachedGoogleMapsClient::class),
            app(VenueRepositoryInterface::class),
            Mockery::mock(WeatherAssessmentService::class),
        );
    }

    private function makeVenueResult(float $rating, int $distanceMeters, string $name = 'Test'): VenueResult
    {
        $result                = new VenueResult();
        $result->placeId       = md5($name);
        $result->name          = $name;
        $result->address       = 'Test Address';
        $result->lat           = 3.1579;
        $result->lng           = 101.7119;
        $result->type          = 'restaurant';
        $result->rating        = $rating;
        $result->isHalal       = null;
        $result->isHalalVerified = false;
        $result->distanceMeters = $distanceMeters;
        $result->photoUrl      = null;
        $result->googleMapsUrl = 'https://www.google.com/maps';
        $result->source        = 'seeded';

        return $result;
    }

    public function test_ranking_sorts_by_score_correctly(): void
    {
        // maxDist = 5000; scores:
        // A: rating=5.0, dist=1000 → (1.0*0.6) + (1-0.2)*0.4 = 0.60 + 0.32 = 0.92
        // B: rating=3.0, dist=3000 → (0.6*0.6) + (1-0.6)*0.4 = 0.36 + 0.16 = 0.52
        // C: rating=4.0, dist=5000 → (0.8*0.6) + (1-1.0)*0.4 = 0.48 + 0.00 = 0.48
        $venueA = $this->makeVenueResult(rating: 5.0, distanceMeters: 1000, name: 'A');
        $venueB = $this->makeVenueResult(rating: 3.0, distanceMeters: 3000, name: 'B');
        $venueC = $this->makeVenueResult(rating: 4.0, distanceMeters: 5000, name: 'C');

        $service = $this->makeService();
        $ranked  = $service->rankVenues([$venueC, $venueB, $venueA]); // deliberately scrambled input

        $this->assertCount(3, $ranked);
        $this->assertSame('A', $ranked[0]->name);
        $this->assertSame('B', $ranked[1]->name);
        $this->assertSame('C', $ranked[2]->name);
    }

    public function test_haversine_distance_calculation(): void
    {
        // KLCC → Subang Jaya: approximately 19 km
        $distance = GeoHelper::haversineDistance(3.1579, 101.7119, 3.0456, 101.5797);

        $this->assertGreaterThan(17000, $distance);
        $this->assertLessThan(22000, $distance);
    }

    public function test_halal_filter_excludes_non_halal_seeded_venues(): void
    {
        // Venues placed at KLCC coords — well within 25 000 m fallback radius
        Venue::factory()->create([
            'type'      => 'restaurant',
            'latitude'  => 3.1579,
            'longitude' => 101.7119,
            'is_halal'  => true,
            'name'      => 'Halal Place',
        ]);

        Venue::factory()->create([
            'type'      => 'restaurant',
            'latitude'  => 3.1580,
            'longitude' => 101.7120,
            'is_halal'  => false,
            'name'      => 'Non-Halal Place',
        ]);

        $mockMaps = Mockery::mock(CachedGoogleMapsClient::class);
        // halalFilter + restaurant → textSearch path, return empty so only seeded used
        $mockMaps->shouldReceive('textSearch')->andReturn([]);
        $mockMaps->shouldReceive('nearbySearch')->andReturn([]);

        $mockWeather = Mockery::mock(WeatherAssessmentService::class);

        $service = new VenueDiscoveryService($mockMaps, app(VenueRepositoryInterface::class), $mockWeather);

        $result = $service->discover(
            lat:           3.1579,
            lng:           101.7119,
            venueType:     'restaurant',
            halalFilter:   true,
            transportMode: 'car',
        );

        $names = array_column($result['venues'], 'name');

        $this->assertContains('Halal Place', $names);
        $this->assertNotContains('Non-Halal Place', $names);
    }
}
