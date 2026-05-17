<?php

namespace Tests\Feature\Api\V1;

use App\Models\Participant;
use App\Models\Session;
use App\Services\GoogleMaps\CachedGoogleMapsClient;
use App\Services\Venue\BestPickService;
use App\Services\Venue\VenueDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Full venue array matching VenueResult::toArray() shape including Module 4 fields. */
    private function fakeVenueArray(string $name = 'Test Venue', bool $isBestPick = false): array
    {
        return [
            'place_id'             => 'ChIJ' . md5($name),
            'name'                 => $name,
            'address'              => '1 Jalan Test, Kuala Lumpur',
            'lat'                  => 3.1579,
            'lng'                  => 101.7119,
            'type'                 => 'restaurant',
            'rating'               => 4.2,
            'is_halal'             => true,
            'is_halal_verified'    => false,
            'distance_meters'      => 500,
            'photo_url'            => null,
            'google_maps_url'      => 'https://www.google.com/maps/place/?q=place_id:ChIJtest',
            'source'               => 'google',
            'travel_times'         => [
                'participant-uuid-1' => ['seconds' => 1200, 'text' => '20 mins'],
                'participant-uuid-2' => ['seconds' => 900,  'text' => '15 mins'],
            ],
            'best_for_participant' => 'participant-uuid-2',
            'is_best_pick'         => $isBestPick,
            'score'                => 0.87,
        ];
    }

    /** Session with a midpoint already calculated. */
    private function sessionWithMidpoint(array $overrides = []): Session
    {
        return Session::factory()->create(array_merge([
            'midpoint_lat'   => 3.1579,
            'midpoint_lng'   => 101.7119,
            'venue_type'     => 'restaurant',
            'halal_filter'   => false,
            'transport_mode' => 'car',
        ], $overrides));
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_recommendation_endpoint_requires_midpoint(): void
    {
        $session = Session::factory()->create(['midpoint_lat' => null]);
        Participant::factory()->count(2)->create(['session_id' => $session->id]);

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/recommendation");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_recommendation_returns_correct_structure(): void
    {
        $session = $this->sessionWithMidpoint();
        Participant::factory()->count(2)->create(['session_id' => $session->id]);

        $bestVenue   = $this->fakeVenueArray('Best Place', true);
        $otherVenue  = $this->fakeVenueArray('Other Place', false);
        $fakeVenues  = [$bestVenue, $otherVenue];

        $this->mock(VenueDiscoveryService::class, function (MockInterface $mock) use ($fakeVenues) {
            $mock->shouldReceive('discover')->once()->andReturn($fakeVenues);
        });

        $this->mock(BestPickService::class, function (MockInterface $mock) use ($fakeVenues) {
            $mock->shouldReceive('selectBestPick')->once()->andReturn($fakeVenues);
            $mock->shouldReceive('calculateFairness')->once()->andReturn(0.91);
        });

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/recommendation");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'session_short_code',
                'midpoint' => ['lat', 'lng'],
                'best_venue',
                'participant_summary',
                'fairness_score',
                'transport_mode',
                'venue_type',
                'calculated_at',
            ]]);

        $this->assertCount(2, $response->json('data.participant_summary'));
        $this->assertTrue($response->json('data.best_venue.is_best_pick'));
        $this->assertSame(0.91, $response->json('data.fairness_score'));
    }

    public function test_venues_response_includes_travel_times(): void
    {
        $session = $this->sessionWithMidpoint();
        $p1      = Participant::factory()->create(['session_id' => $session->id]);
        $p2      = Participant::factory()->create(['session_id' => $session->id]);

        $fakePlace = [
            'place_id'    => 'ChIJtestVenue001',
            'name'        => 'Midpoint Restaurant',
            'vicinity'    => '1 Jalan Midpoint, Kuala Lumpur',
            'geometry'    => ['location' => ['lat' => 3.1579, 'lng' => 101.7119]],
            'rating'      => 4.5,
        ];

        // One row per participant, one element per destination venue
        $matrixRows = [
            ['elements' => [['status' => 'OK', 'duration' => ['value' => 1200, 'text' => '20 mins']]]],
            ['elements' => [['status' => 'OK', 'duration' => ['value' => 900,  'text' => '15 mins']]]],
        ];

        $this->mock(CachedGoogleMapsClient::class, function (MockInterface $mock) use ($fakePlace, $matrixRows) {
            $mock->shouldReceive('nearbySearch')->andReturn([$fakePlace]);
            $mock->shouldReceive('distanceMatrix')->andReturn($matrixRows);
        });

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/venues");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $venue) {
            $this->assertArrayHasKey('travel_times', $venue);
            $this->assertArrayHasKey('is_best_pick', $venue);
        }

        // Exactly one venue must be marked best pick
        $bestCount = count(array_filter($data, static fn($v) => $v['is_best_pick'] === true));
        $this->assertSame(1, $bestCount);

        // Travel times keyed by participant ID must be present on each venue
        foreach ($data as $venue) {
            $this->assertArrayHasKey($p1->id, $venue['travel_times']);
            $this->assertArrayHasKey($p2->id, $venue['travel_times']);
            $this->assertSame(1200, $venue['travel_times'][$p1->id]['seconds']);
            $this->assertSame(900,  $venue['travel_times'][$p2->id]['seconds']);
        }
    }
}
