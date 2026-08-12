<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_maps.key' => 'test-key']);
    }

    public function test_autocomplete_returns_normalised_suggestions(): void
    {
        Http::fake([
            '*/place/autocomplete/json*' => Http::response([
                'status'      => 'OK',
                'predictions' => [
                    [
                        'place_id'    => 'ChIJplace1',
                        'description' => 'KLCC, Kuala Lumpur, Malaysia',
                        'structured_formatting' => [
                            'main_text'      => 'KLCC',
                            'secondary_text' => 'Kuala Lumpur, Malaysia',
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/places/autocomplete?input=KLCC');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.place_id', 'ChIJplace1')
            ->assertJsonPath('data.0.primary_text', 'KLCC')
            ->assertJsonPath('data.0.secondary_text', 'Kuala Lumpur, Malaysia');
    }

    public function test_autocomplete_requires_a_minimum_input_length(): void
    {
        $this->getJson('/api/v1/places/autocomplete?input=KL')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_details_returns_coordinates(): void
    {
        Http::fake([
            '*/place/details/json*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'name'              => 'KLCC',
                    'formatted_address' => 'KLCC, Kuala Lumpur',
                    'geometry'          => ['location' => ['lat' => 3.1579, 'lng' => 101.7119]],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/places/ChIJplace1');

        $response->assertStatus(200)
            ->assertJsonPath('data.lat', 3.1579)
            ->assertJsonPath('data.lng', 101.7119)
            ->assertJsonPath('data.address', 'KLCC, Kuala Lumpur');
    }

    public function test_upstream_failure_surfaces_as_502_not_500(): void
    {
        Http::fake([
            '*/place/details/json*' => Http::response([
                'status' => 'OVER_QUERY_LIMIT',
            ]),
        ]);

        $this->getJson('/api/v1/places/ChIJplace1')
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_reverse_geocode_returns_an_address(): void
    {
        Http::fake([
            '*/geocode/json*' => Http::response([
                'status'  => 'OK',
                'results' => [['formatted_address' => 'Bangsar, Kuala Lumpur']],
            ]),
        ]);

        $this->getJson('/api/v1/places/reverse-geocode?lat=3.1298&lng=101.6719')
            ->assertStatus(200)
            ->assertJsonPath('data.address', 'Bangsar, Kuala Lumpur');
    }
}
