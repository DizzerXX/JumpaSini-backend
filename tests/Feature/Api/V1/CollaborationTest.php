<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\AutoConfirmVenueJob;
use App\Models\Participant;
use App\Models\Session;
use App\Models\VenueVote;
use App\Services\Venue\BestPickService;
use App\Services\Venue\VenueDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

    /** Create a participant in the session with a known access token. */
    private function participantFor(Session $session, ?string $name = 'Danish'): Participant
    {
        return Participant::factory()->create([
            'session_id'   => $session->id,
            'name'         => $name,
            'access_token' => 'token-' . Str::random(8),
        ]);
    }

    private function castVote(
        Session $session,
        string $vote = 'up',
        ?Participant $participant = null,
        string $placeId = 'ChIJtest'
    ): TestResponse {
        $participant ??= $this->participantFor($session);

        return $this->postJson("/api/v1/sessions/{$session->short_code}/votes", [
            'google_place_id' => $placeId,
            'venue_name'      => 'Test Mall',
            'participant_id'  => $participant->id,
            'access_token'    => $participant->access_token,
            'vote'            => $vote,
        ]);
    }

    // ─── Test 1: Cast vote stores correctly ───────────────────────────────────

    public function test_cast_vote_stores_correctly(): void
    {
        $session     = $this->sessionWithMidpoint();
        $participant = $this->participantFor($session, 'Danish');

        $response = $this->castVote($session, 'up', $participant);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['vote_summary', 'your_vote']]);

        $this->assertDatabaseHas('venue_votes', [
            'session_id'      => $session->id,
            'participant_id'  => $participant->id,
            'google_place_id' => 'ChIJtest',
            'vote'            => 'up',
        ]);
    }

    public function test_cast_vote_requires_a_valid_access_token(): void
    {
        $session     = Session::factory()->create();
        $participant = $this->participantFor($session);

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/votes", [
            'google_place_id' => 'ChIJtest',
            'venue_name'      => 'Test Mall',
            'participant_id'  => $participant->id,
            'access_token'    => 'wrong-token',
            'vote'            => 'up',
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
        $this->assertDatabaseCount('venue_votes', 0);
    }

    public function test_cast_vote_rejects_participant_from_another_session(): void
    {
        $session = Session::factory()->create();
        $outsider = $this->participantFor(Session::factory()->create());

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/votes", [
            'google_place_id' => 'ChIJtest',
            'venue_name'      => 'Test Mall',
            'participant_id'  => $outsider->id,
            'access_token'    => $outsider->access_token,
            'vote'            => 'up',
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
        $this->assertDatabaseCount('venue_votes', 0);
    }

    // ─── Test 2: Upsert replaces an existing vote ────────────────────────────

    public function test_upsert_vote_updates_existing_vote(): void
    {
        $session     = Session::factory()->create();
        $participant = $this->participantFor($session);

        $this->castVote($session, 'up', $participant)->assertStatus(200);
        $this->castVote($session, 'down', $participant)->assertStatus(200);

        $this->assertDatabaseCount('venue_votes', 1);
        $this->assertDatabaseHas('venue_votes', [
            'session_id'     => $session->id,
            'participant_id' => $participant->id,
            'vote'           => 'down',
        ]);
    }

    /**
     * Regression: votes used to be keyed on participant_name, so two participants
     * who both left their name blank collapsed into a single row.
     */
    public function test_two_unnamed_participants_cast_separate_votes(): void
    {
        $session = Session::factory()->create();
        $first   = $this->participantFor($session, null);
        $second  = $this->participantFor($session, null);

        $this->castVote($session, 'up', $first)->assertStatus(200);
        $this->castVote($session, 'down', $second)->assertStatus(200);

        $this->assertDatabaseCount('venue_votes', 2);

        $summary = $this->getJson("/api/v1/sessions/{$session->short_code}/votes")
            ->json('data.vote_summary.ChIJtest');

        $this->assertSame(1, $summary['up']);
        $this->assertSame(1, $summary['down']);
        $this->assertSame(2, $summary['total']);
    }

    // ─── Test 3: Vote summary counts correctly ────────────────────────────────

    public function test_vote_summary_counts_correctly(): void
    {
        $session = Session::factory()->create();

        $this->castVote($session, 'up', $this->participantFor($session, 'Danish'));
        $this->castVote($session, 'up', $this->participantFor($session, 'Ahmad'));
        $this->castVote($session, 'down', $this->participantFor($session, 'Siti'));

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

    // ─── Test 9: Voting start sets correct timestamps ─────────────────────────

    public function test_voting_start_sets_correct_timestamps(): void
    {
        Queue::fake();

        $session = Session::factory()->create();

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/voting/start");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.voting_state.active', true);

        $session->refresh();

        $this->assertNotNull($session->voting_starts_at);
        $this->assertNotNull($session->voting_ends_at);

        $durationMinutes = (int) config('jumpa.voting.duration_minutes', 5);
        $this->assertEqualsWithDelta(
            $session->voting_starts_at->addMinutes($durationMinutes)->timestamp,
            $session->voting_ends_at->timestamp,
            2
        );

        Queue::assertPushed(AutoConfirmVenueJob::class, fn ($job) => true);
    }

    // ─── Test 10: Summary includes voting state ───────────────────────────────

    public function test_summary_includes_voting_state(): void
    {
        Queue::fake();

        $session = Session::factory()->create();
        Participant::factory()->count(2)->create(['session_id' => $session->id]);

        // Start voting
        $this->postJson("/api/v1/sessions/{$session->short_code}/voting/start")
            ->assertStatus(200);

        $this->castVote($session, 'up', $this->participantFor($session, 'Danish'), 'ChIJwinner');
        $this->castVote($session, 'down', $this->participantFor($session, 'Ahmad'), 'ChIJloser');

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $votingState = $response->json('data.voting_state');
        $this->assertNotNull($votingState);
        $this->assertTrue($votingState['active']);
        $this->assertNotNull($votingState['ends_at']);
        $this->assertGreaterThan(0, $votingState['seconds_remaining']);
        $this->assertSame('ChIJwinner', $votingState['winning_venue']);
    }

    // ─── Test 11: Auto-confirm selects most voted venue ───────────────────────

    public function test_auto_confirm_selects_most_voted_venue(): void
    {
        $session = Session::factory()->create([
            'voting_starts_at' => now()->subMinutes(6),
            'voting_ends_at'   => now()->subMinute(), // already ended
        ]);

        // Venue A: 3 up votes (winner)
        $this->castVote($session, 'up', $this->participantFor($session, 'Danish'), 'ChIJvenueA');
        $this->castVote($session, 'up', $this->participantFor($session, 'Ahmad'), 'ChIJvenueA');
        $this->castVote($session, 'up', $this->participantFor($session, 'Siti'), 'ChIJvenueA');

        // Venue B: 1 up vote
        $this->castVote($session, 'up', $this->participantFor($session, 'Zara'), 'ChIJvenueB');

        AutoConfirmVenueJob::dispatchSync($session->id);

        $this->assertDatabaseHas('sessions', [
            'id'                      => $session->id,
            'voting_confirmed_venue'  => 'ChIJvenueA',
            'session_confirmed_venue' => 'ChIJvenueA',
            'status'                  => 'completed',
        ]);
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
