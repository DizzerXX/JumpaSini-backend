<?php

namespace Tests\Feature\Api\V1;

use App\Models\Participant;
use App\Models\Session;
use App\Models\VenueVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    // ─── Test 7: Adding participant resets midpoint ───────────────────────────

    public function test_adding_participant_to_session_with_midpoint_resets_midpoint(): void
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

        $this->assertDatabaseHas('sessions', [
            'id'           => $session->id,
            'midpoint_lat' => null,
            'midpoint_lng' => null,
        ]);
    }
}
