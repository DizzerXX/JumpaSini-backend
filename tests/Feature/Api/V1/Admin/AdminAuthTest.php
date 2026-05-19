<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_admin_can_login_with_correct_credentials(): void
    {
        Admin::factory()->create([
            'email'    => 'admin@test.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email'    => 'admin@test.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'admin' => ['id', 'name', 'email'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_admin_cannot_login_with_wrong_password(): void
    {
        Admin::factory()->create(['email' => 'admin@test.com']);

        $response = $this->postJson('/api/v1/admin/login', [
            'email'    => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_logout(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_require_auth_token(): void
    {
        $this->getJson('/api/v1/admin/me')->assertStatus(401);
        $this->getJson('/api/v1/admin/venues')->assertStatus(401);
        $this->getJson('/api/v1/admin/analytics/overview')->assertStatus(401);
    }
}
