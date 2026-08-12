<?php

namespace Tests\Feature\Api\V1;

use App\Models\Participant;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    // ─── POST /api/v1/sessions ────────────────────────────────────────────────

    public function test_can_create_a_session(): void
    {
        $response = $this->postJson('/api/v1/sessions', [
            'transport_mode' => 'car',
            'venue_type'     => 'restaurant',
            'halal_filter'   => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id', 'short_code', 'transport_mode', 'venue_type',
                    'halal_filter', 'status', 'expires_at', 'participants',
                ],
            ]);

        $this->assertDatabaseHas('sessions', [
            'transport_mode' => 'car',
            'venue_type'     => 'restaurant',
            'halal_filter'   => true,
            'status'         => 'pending',
        ]);
    }

    public function test_create_session_generates_unique_short_code(): void
    {
        $r1 = $this->postJson('/api/v1/sessions', ['transport_mode' => 'car', 'venue_type' => 'cafe']);
        $r2 = $this->postJson('/api/v1/sessions', ['transport_mode' => 'car', 'venue_type' => 'cafe']);

        $this->assertNotEquals(
            $r1->json('data.short_code'),
            $r2->json('data.short_code')
        );
    }

    public function test_create_session_fails_with_invalid_transport_mode(): void
    {
        $response = $this->postJson('/api/v1/sessions', [
            'transport_mode' => 'bicycle',
            'venue_type'     => 'mall',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_create_session_fails_with_invalid_venue_type(): void
    {
        $response = $this->postJson('/api/v1/sessions', [
            'transport_mode' => 'car',
            'venue_type'     => 'stadium',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── GET /api/v1/sessions/{short_code} ───────────────────────────────────

    public function test_can_retrieve_a_session(): void
    {
        $session = Session::factory()->create();

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.short_code', $session->short_code);
    }

    public function test_get_session_returns_404_for_unknown_short_code(): void
    {
        $response = $this->getJson('/api/v1/sessions/XXXXXXXX');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_get_session_returns_404_for_expired_session(): void
    {
        $session = Session::factory()->expired()->create();

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ─── POST /api/v1/sessions/{short_code}/participants ─────────────────────

    public function test_can_add_participant_to_session(): void
    {
        $session = Session::factory()->create();

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/participants", [
            'name'      => 'Alice',
            'address'   => 'KLCC, Kuala Lumpur',
            'latitude'  => 3.1569,
            'longitude' => 101.7123,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.participant.name', 'Alice')
            ->assertJsonPath('data.session.participant_count', 1);

        $this->assertDatabaseHas('participants', [
            'session_id' => $session->id,
            'name'       => 'Alice',
        ]);
    }

    public function test_add_participant_rejects_location_outside_kl_selangor(): void
    {
        $session = Session::factory()->create();

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/participants", [
            'name'      => 'Bob',
            'address'   => 'Kota Bharu, Kelantan',
            'latitude'  => 6.1248,
            'longitude' => 102.2380,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'location' => ['This location is outside our supported area (Klang Valley only).'],
            ]);
    }

    public function test_add_participant_rejects_when_session_is_full(): void
    {
        $session = Session::factory()->full()->create();

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/participants", [
            'name'      => 'Extra Person',
            'address'   => 'Petaling Jaya, Selangor',
            'latitude'  => 3.1073,
            'longitude' => 101.6067,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_add_participant_rejects_expired_session(): void
    {
        $session = Session::factory()->expired()->create();

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/participants", [
            'name'      => 'Charlie',
            'address'   => 'Bangsar, Kuala Lumpur',
            'latitude'  => 3.1298,
            'longitude' => 101.6719,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_add_participant_name_is_optional(): void
    {
        $session = Session::factory()->create();

        $response = $this->postJson("/api/v1/sessions/{$session->short_code}/participants", [
            'address'   => 'Subang Jaya, Selangor',
            'latitude'  => 3.0521,
            'longitude' => 101.5872,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.participant.name', null);
    }

    // ─── DELETE /api/v1/sessions/{short_code}/participants/{id} ──────────────

    public function test_can_remove_participant_from_session(): void
    {
        $session     = Session::factory()->create();
        $participant = Participant::factory()->create([
            'session_id'   => $session->id,
            'access_token' => 'valid-token',
        ]);

        $response = $this->deleteJson(
            "/api/v1/sessions/{$session->short_code}/participants/{$participant->id}",
            ['access_token' => 'valid-token']
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.participant_count', 0);

        $this->assertDatabaseMissing('participants', ['id' => $participant->id]);
    }

    public function test_remove_participant_rejects_wrong_access_token(): void
    {
        $session     = Session::factory()->create();
        $participant = Participant::factory()->create([
            'session_id'   => $session->id,
            'access_token' => 'valid-token',
        ]);

        $response = $this->deleteJson(
            "/api/v1/sessions/{$session->short_code}/participants/{$participant->id}",
            ['access_token' => 'wrong-token']
        );

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('participants', ['id' => $participant->id]);
    }

    public function test_remove_participant_requires_access_token(): void
    {
        $session     = Session::factory()->create();
        $participant = Participant::factory()->create(['session_id' => $session->id]);

        $response = $this->deleteJson(
            "/api/v1/sessions/{$session->short_code}/participants/{$participant->id}"
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_remove_participant_returns_404_for_wrong_session(): void
    {
        $session1    = Session::factory()->create();
        $session2    = Session::factory()->create();
        $participant = Participant::factory()->create([
            'session_id'   => $session2->id,
            'access_token' => 'valid-token',
        ]);

        $response = $this->deleteJson(
            "/api/v1/sessions/{$session1->short_code}/participants/{$participant->id}",
            ['access_token' => 'valid-token']
        );

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
