<?php

namespace Tests\Feature\Api\V1;

use App\Models\Participant;
use App\Models\Session;
use App\Models\VenueVote;
use App\Services\Venue\BestPickService;
use App\Services\Venue\VenueDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function sessionWithMidpoint(): Session
    {
        return Session::factory()->create([
            'midpoint_lat'           => 3.1579,
            'midpoint_lng'           => 101.7119,
            'midpoint_calculated_at' => now(),
        ]);
    }

    private function castVote(Session $session, string $vote = 'up', string $participantName = 'Danish', string $placeId = 'ChIJtest'): void
    {
        $this->postJson("/api/v1/sessions/{$session->short_code}/votes", [
            'google_place_id'  => $placeId,
            'venue_name'       => 'Test Mall',
            'participant_name' => $participantName,
            'vote'             => $vote,
        ]);
    }

    // ─── Test 1: Cast vote stores correctly ───────────────────────────────────

    public function test_cast_vote_stores_correctly(): void
    {
        $session = $this->sessionWithMidpoint();
        Participant::factory()->count(2)->create(['session_id' => $session->id]);

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/votes", [
            'google_place_id'  => 'ChIJtest',
            'venue_name'       => 'Test Mall',
            'participant_name' => 'Danish',
            'vote'             => 'up',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['vote_summary', 'your_vote']]);

        $this->assertDatabaseHas('venue_votes', [
            'session_id'       => $session->id,
            'google_place_id'  => 'ChIJtest',
            'participant_name' => 'Danish',
            'vote'             => 'up',
        ]);
    }

    // ─── Test 2: Upsert replaces an existing vote ────────────────────────────

    public function test_upsert_vote_updates_existing_vote(): void
    {
        $session = Session::factory()->create();

        $this->postJson("/api/v1/sessions/{$session->short_code}/votes", [
            'google_place_id'  => 'ChIJtest',
            'venue_name'       => 'Test Mall',
            'participant_name' => 'Danish',
            'vote'             => 'up',
        ])->assertStatus(200);

        $this->postJson("/api/v1/sessions/{$session->short_code}/votes", [
            'google_place_id'  => 'ChIJtest',
            'venue_name'       => 'Test Mall',
            'participant_name' => 'Danish',
            'vote'             => 'down',
        ])->assertStatus(200);

        $this->assertDatabaseCount('venue_votes', 1);
        $this->assertDatabaseHas('venue_votes', [
            'session_id'       => $session->id,
            'google_place_id'  => 'ChIJtest',
            'participant_name' => 'Danish',
            'vote'             => 'down',
        ]);
    }

    // ─── Test 3: Vote summary counts correctly ────────────────────────────────

    public function test_vote_summary_counts_correctly(): void
    {
        $session = Session::factory()->create();

        $this->castVote($session, 'up', 'Danish');
        $this->castVote($session, 'up', 'Ahmad');
        $this->castVote($session, 'down', 'Siti');

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/votes");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $summary = $response->json('data.vote_summary.ChIJtest');
        $this->assertSame(2, $summary['up']);
        $this->assertSame(1, $summary['down']);
        $this->assertSame(3, $summary['total']);
    }

    // ─── Test 4: Confirm venue marks session completed ────────────────────────

    public function test_confirm_venue_marks_session_completed(): void
    {
        $session = Session::factory()->create();

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/confirm", [
            'google_place_id' => 'ChIJtest',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('sessions', [
            'id'                      => $session->id,
            'status'                  => 'completed',
            'session_confirmed_venue' => 'ChIJtest',
        ]);

        $this->assertNotNull(
            Session::find($session->id)->session_confirmed_at
        );
    }

    // ─── Test 5: Session summary returns complete structure ───────────────────

    public function test_session_summary_returns_complete_structure(): void
    {
        // No midpoint set — avoids VenueDiscovery call; all structural keys still present
        $session = Session::factory()->create();
        Participant::factory()->count(2)->create(['session_id' => $session->id]);

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'session',
                    'participants',
                    'midpoint',
                    'confirmed_venue',
                    'vote_summary',
                    'recommendation',
                    'is_complete',
                    'expires_at',
                ],
            ]);
    }

    // ─── Test 6: Shareable URL is included in session response ───────────────

    public function test_shareable_url_included_in_session_response(): void
    {
        $session = Session::factory()->create();

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $shareableUrl = $response->json('data.shareable_url');
        $this->assertNotNull($shareableUrl);
        $this->assertStringContainsString($session->short_code, $shareableUrl);
    }

    // ─── Test 7: Summary includes recommendation when midpoint is set ────────

    public function test_summary_includes_recommendation_when_midpoint_exists(): void
    {
        $session = $this->sessionWithMidpoint();
        Participant::factory()->count(2)->create(['session_id' => $session->id]);

        $fakeVenue = [
            'place_id'             => 'ChIJsummarytest001',
            'name'                 => 'Midpoint Cafe',
            'address'              => '1 Jalan Test, Kuala Lumpur',
            'lat'                  => 3.1579,
            'lng'                  => 101.7119,
            'type'                 => 'restaurant',
            'rating'               => 4.5,
            'is_halal'             => true,
            'is_halal_verified'    => false,
            'distance_meters'      => 300,
            'photo_url'            => null,
            'google_maps_url'      => 'https://www.google.com/maps/search/?api=1&query=3.1579,101.7119',
            'source'               => 'seeded',
            'travel_times'         => [],
            'best_for_participant' => null,
            'is_best_pick'         => true,
            'score'                => 0.9,
        ];

        $this->mock(VenueDiscoveryService::class, function (MockInterface $mock) use ($fakeVenue) {
            $mock->shouldReceive('discover')->once()->andReturn(['venues' => [$fakeVenue], 'weather' => null]);
        });

        $this->mock(BestPickService::class, function (MockInterface $mock) use ($fakeVenue) {
            $mock->shouldReceive('selectBestPick')->once()->andReturn([$fakeVenue]);
            $mock->shouldReceive('calculateFairness')->once()->andReturn(1.0);
        });

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $recommendation = $response->json('data.recommendation');
        $this->assertNotNull($recommendation);
        $this->assertArrayHasKey('best_venue', $recommendation);
        $this->assertSame('Midpoint Cafe', $recommendation['best_venue']['name']);
    }

    // ─── Test 8: Adding participant triggers auto-recalculation ──────────────

    public function test_adding_participant_to_session_triggers_recalculation(): void
    {
        $session = $this->sessionWithMidpoint();

        $this->assertDatabaseHas('sessions', [
            'id'           => $session->id,
            'midpoint_lat' => 3.1579,
        ]);

        $this->postJson("/api/v1/sessions/{$session->short_code}/participants", [
            'name'      => 'New Person',
            'address'   => 'Bangsar, Kuala Lumpur',
            'latitude'  => 3.1298,
            'longitude' => 101.6719,
        ])->assertStatus(201);

        // Recalculation runs synchronously (QUEUE_CONNECTION=sync).
        // With 1 participant the midpoint becomes that participant's own location.
        $this->assertDatabaseHas('sessions', [
            'id'     => $session->id,
            'status' => 'active',
        ]);

        $session->refresh();
        $this->assertNotNull($session->midpoint_lat);
        $this->assertEqualsWithDelta(3.1298, (float) $session->midpoint_lat, 0.0001);
        $this->assertEqualsWithDelta(101.6719, (float) $session->midpoint_lng, 0.0001);
    }
}
