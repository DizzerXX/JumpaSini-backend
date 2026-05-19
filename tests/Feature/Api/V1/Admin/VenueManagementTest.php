<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VenueManagementTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_admin_can_list_venues_with_pagination(): void
    {
        $this->actingAsAdmin();
        Venue::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/venues');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'venues',
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                ],
            ]);

        $this->assertCount(5, $response->json('data.venues'));
        $this->assertSame(5, $response->json('data.pagination.total'));
    }

    public function test_admin_can_create_venue(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'name'      => 'KLCC Mall',
            'address'   => 'Kuala Lumpur City Centre, 50088 Kuala Lumpur',
            'latitude'  => 3.1579,
            'longitude' => 101.7119,
            'type'      => 'mall',
            'is_halal'  => null,
        ];

        $response = $this->postJson('/api/v1/admin/venues', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'KLCC Mall');

        $this->assertDatabaseHas('venues', ['name' => 'KLCC Mall', 'is_active' => 1]);
    }

    public function test_admin_can_update_venue_halal_status(): void
    {
        $this->actingAsAdmin();
        $venue = Venue::factory()->create(['is_halal' => null, 'is_halal_verified' => false]);

        $response = $this->patchJson("/api/v1/admin/venues/{$venue->id}/halal", [
            'is_halal'          => true,
            'is_halal_verified' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_halal', true)
            ->assertJsonPath('data.is_halal_verified', true);

        $this->assertDatabaseHas('venues', [
            'id'                => $venue->id,
            'is_halal'          => 1,
            'is_halal_verified' => 1,
        ]);
    }

    public function test_admin_can_soft_delete_venue(): void
    {
        $this->actingAsAdmin();
        $venue = Venue::factory()->create(['is_active' => true]);

        $response = $this->deleteJson("/api/v1/admin/venues/{$venue->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('venues', ['id' => $venue->id, 'is_active' => 0]);
    }

    public function test_deleted_venue_not_returned_in_public_venue_search(): void
    {
        $this->actingAsAdmin();
        $venue = Venue::factory()->create(['is_active' => true]);

        $this->deleteJson("/api/v1/admin/venues/{$venue->id}");

        // The active() scope excludes is_active=false venues
        $this->assertNull(Venue::active()->find($venue->id));
    }

    public function test_venue_list_filterable_by_type(): void
    {
        $this->actingAsAdmin();
        Venue::factory()->count(3)->create(['type' => 'mall']);
        Venue::factory()->count(2)->create(['type' => 'park']);

        $response = $this->getJson('/api/v1/admin/venues?type=mall');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.venues'));

        foreach ($response->json('data.venues') as $venue) {
            $this->assertSame('mall', $venue['type']);
        }
    }

    public function test_venue_list_filterable_by_halal_status(): void
    {
        $this->actingAsAdmin();
        Venue::factory()->count(2)->halal()->create();
        Venue::factory()->count(3)->notHalal()->create();
        Venue::factory()->count(1)->create(['is_halal' => null]);

        $response = $this->getJson('/api/v1/admin/venues?halal=true');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.venues'));

        foreach ($response->json('data.venues') as $venue) {
            $this->assertTrue($venue['is_halal']);
        }
    }
}
