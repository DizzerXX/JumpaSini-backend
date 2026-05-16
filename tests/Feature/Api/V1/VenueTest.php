<?php

namespace Tests\Feature\Api\V1;

use App\Models\Participant;
use App\Models\Session;
use App\Services\Venue\VenueDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class VenueTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Fake venue array that satisfies the response structure assertions. */
    private function fakeVenueArray(string $name = 'Test Venue'): array
    {
        return [
            'place_id'          => 'ChIJ' . md5($name),
            'name'              => $name,
            'address'           => '1 Jalan Test, Kuala Lumpur',
            'lat'               => 3.1579,
            'lng'               => 101.7119,
            'type'              => 'restaurant',
            'rating'            => 4.2,
            'is_halal'          => true,
            'is_halal_verified' => false,
            'distance_meters'   => 500,
            'photo_url'         => null,
            'google_maps_url'   => 'https://www.google.com/maps/place/?q=place_id:ChIJtest',
            'source'            => 'google',
        ];
    }

    /** Session with a midpoint already calculated. */
    private function sessionWithMidpoint(array $overrides = []): Session
    {
        return Session::factory()->create(array_merge([
            'midpoint_lat'    => 3.1579,
            'midpoint_lng'    => 101.7119,
            'venue_type'      => 'restaurant',
            'halal_filter'    => true,
            'transport_mode'  => 'car',
        ], $overrides));
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_venues_endpoint_requires_midpoint_to_be_calculated(): void
    {
        $session = Session::factory()->create(['midpoint_lat' => null]);
        Participant::factory()->count(2)->create(['session_id' => $session->id]);

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/venues");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_venues_endpoint_returns_top_5_results(): void
    {
        $venues = array_map(fn(int $i) => $this->fakeVenueArray("Venue {$i}"), range(1, 5));

        $this->mock(VenueDiscoveryService::class, function (MockInterface $mock) use ($venues) {
            $mock->shouldReceive('discover')->once()->andReturn($venues);
        });

        $session  = $this->sessionWithMidpoint();
        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/venues");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(5, $data);

        foreach ($data as $venue) {
            $this->assertArrayHasKey('name', $venue);
            $this->assertArrayHasKey('lat', $venue);
            $this->assertArrayHasKey('lng', $venue);
            $this->assertArrayHasKey('rating', $venue);
            $this->assertArrayHasKey('is_halal', $venue);
            $this->assertArrayHasKey('distance_meters', $venue);
        }
    }

    public function test_halal_filter_is_applied_for_restaurant_type(): void
    {
        $capturedHalalFilter = null;

        $this->mock(VenueDiscoveryService::class, function (MockInterface $mock) use (&$capturedHalalFilter) {
            $mock->shouldReceive('discover')
                ->once()
                ->andReturnUsing(function ($lat, $lng, $venueType, $halalFilter, $transportMode) use (&$capturedHalalFilter) {
                    $capturedHalalFilter = $halalFilter;
                    return [];
                });
        });

        $session  = $this->sessionWithMidpoint(['venue_type' => 'restaurant', 'halal_filter' => true]);
        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/venues");

        $response->assertStatus(200);
        $this->assertTrue($capturedHalalFilter, 'VenueDiscoveryService::discover() was not called with halalFilter=true');
    }

    public function test_halal_report_stores_correctly(): void
    {
        $response = $this->postJson('/api/v1/venues/ChIJtest123/halal-report', [
            'reported_as_halal' => true,
            'session_id'        => null,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('venue_halal_reports', [
            'google_place_id'   => 'ChIJtest123',
            'reported_as_halal' => 1,
        ]);
    }
}
