<?php

namespace Tests\Feature\Api\V1;

use App\Models\Participant;
use App\Models\Session;
use App\Services\GoogleMaps\CachedGoogleMapsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MidpointTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Replace CachedGoogleMapsClient with a mock that returns fixed travel times
     * for all distanceMatrix calls during the test.
     *
     * @param  int[]  $durationsInSeconds  One value per participant, same order as participants
     */
    private function mockDistanceMatrix(array $durationsInSeconds): void
    {
        $rows = array_map(
            static fn(int $secs): array => ['elements' => [['duration' => ['value' => $secs]]]],
            $durationsInSeconds
        );

        $this->mock(CachedGoogleMapsClient::class, function (MockInterface $mock) use ($rows) {
            $mock->shouldReceive('distanceMatrix')->andReturn($rows);
        });
    }

    /**
     * Create a session with two real KL/Selangor participants.
     * KLCC  → lat: 3.1579, lng: 101.7119
     * Subang → lat: 3.0456, lng: 101.5797
     * Expected centroid: lat ≈ 3.1018, lng ≈ 101.6458
     */
    private function sessionWithTwoParticipants(): Session
    {
        $session = Session::factory()->create(['transport_mode' => 'car']);

        Participant::factory()->create([
            'session_id' => $session->id,
            'latitude'   => 3.1579,
            'longitude'  => 101.7119,
        ]);

        Participant::factory()->create([
            'session_id' => $session->id,
            'latitude'   => 3.0456,
            'longitude'  => 101.5797,
        ]);

        return $session;
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_cannot_calculate_midpoint_with_less_than_two_participants(): void
    {
        $session = Session::factory()->create();
        Participant::factory()->create(['session_id' => $session->id]);

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/midpoint");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_midpoint_calculation_returns_correct_structure(): void
    {
        // Travel times: 1200s (20 min) and 1500s (25 min) — diff = 300s = 5 min < 15 min → fair
        $this->mockDistanceMatrix([1200, 1500]);

        $session  = $this->sessionWithTwoParticipants();
        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/midpoint");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'lat',
                    'lng',
                    'is_fair',
                    'travel_times',
                    'iterations',
                    'transport_mode',
                ],
            ]);

        $lat = $response->json('data.lat');
        $lng = $response->json('data.lng');

        $this->assertGreaterThan(3.0, $lat);
        $this->assertLessThan(3.2, $lat);
        $this->assertGreaterThan(101.5, $lng);
        $this->assertLessThan(101.8, $lng);
    }

    public function test_midpoint_updates_session_status_to_active(): void
    {
        $this->mockDistanceMatrix([1200, 1500]);

        $session = $this->sessionWithTwoParticipants();

        $this->postJson("/api/v1/sessions/{$session->short_code}/midpoint")
            ->assertStatus(200);

        $this->assertDatabaseHas('sessions', [
            'id'     => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_expired_session_cannot_calculate_midpoint(): void
    {
        $session = Session::factory()->expired()->create();

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/midpoint");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
