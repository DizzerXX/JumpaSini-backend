<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Participant;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_overview_returns_correct_structure(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_sessions',
                    'active_sessions',
                    'completed_sessions',
                    'total_participants',
                    'avg_participants_per_session',
                    'total_venue_searches',
                    'total_halal_reports',
                    'popular_venue_types',
                    'popular_areas',
                    'sessions_today',
                    'sessions_this_week',
                ],
            ]);
    }

    public function test_overview_counts_sessions_correctly(): void
    {
        $this->actingAsAdmin();

        // 2 completed sessions
        Session::factory()->count(2)->create(['status' => 'completed']);
        // 1 active (non-expired, not completed)
        $activeSession = Session::factory()->create([
            'status'     => 'active',
            'expires_at' => now()->addHours(2),
        ]);
        // 1 session with midpoint calculated (counts as venue search)
        Session::factory()->create(['midpoint_lat' => 3.1579, 'midpoint_lng' => 101.7119]);
        // 2 participants on the active session
        Participant::factory()->count(2)->create(['session_id' => $activeSession->id]);

        $response = $this->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertSame(4, $data['total_sessions']);
        $this->assertSame(2, $data['completed_sessions']);
        $this->assertSame(1, $data['total_venue_searches']);
        $this->assertSame(2, $data['total_participants']);
    }

    public function test_analytics_requires_admin_auth(): void
    {
        $this->getJson('/api/v1/admin/analytics/overview')->assertStatus(401);
        $this->getJson('/api/v1/admin/analytics/sessions')->assertStatus(401);
        $this->getJson('/api/v1/admin/analytics/venues')->assertStatus(401);
        $this->getJson('/api/v1/admin/analytics/halal-reports')->assertStatus(401);
    }
}
