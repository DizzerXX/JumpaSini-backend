<?php

namespace Tests\Feature\Api\V1;

use App\Models\Session;
use App\Services\Weather\CachedWeatherClient;
use App\Services\Weather\WeatherData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeWeatherData(
        int    $conditionCode = 800,
        string $conditionMain = 'Clear',
        float  $rainMmPerHour = 0.0,
    ): WeatherData {
        return new WeatherData(
            conditionCode:        $conditionCode,
            conditionMain:        $conditionMain,
            conditionDescription: strtolower($conditionMain),
            temperature:          29.5,
            feelsLike:            31.0,
            humidity:             80,
            windSpeed:            3.2,
            rainMmPerHour:        $rainMmPerHour,
            icon:                 '01d',
            timestamp:            Carbon::now(),
        );
    }

    private function sessionWithMidpoint(array $overrides = []): Session
    {
        return Session::factory()->create(array_merge([
            'midpoint_lat'   => 3.1579,
            'midpoint_lng'   => 101.7119,
            'venue_type'     => 'park',
            'transport_mode' => 'car',
        ], $overrides));
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_weather_endpoint_requires_midpoint(): void
    {
        $session = Session::factory()->create(['midpoint_lat' => null, 'venue_type' => 'park']);

        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/weather");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_weather_returns_good_for_indoor_venue(): void
    {
        $this->mock(CachedWeatherClient::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getCurrentWeather');
            $mock->shouldNotReceive('getForecast');
        });

        $session  = $this->sessionWithMidpoint(['venue_type' => 'mall']);
        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/weather");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assessment.severity', 'good')
            ->assertJsonPath('data.is_outdoor', false);
    }

    public function test_weather_returns_warning_for_outdoor_venue_with_rain(): void
    {
        $weatherData = $this->makeWeatherData(
            conditionCode: 501,
            conditionMain: 'Rain',
            rainMmPerHour: 5.0,
        );

        $this->mock(CachedWeatherClient::class, function (MockInterface $mock) use ($weatherData) {
            $mock->shouldReceive('getCurrentWeather')->andReturn($weatherData);
            $mock->shouldReceive('getForecast')->andReturn([]);
        });

        $session  = $this->sessionWithMidpoint(['venue_type' => 'park']);
        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/weather");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assessment.severity', 'warning')
            ->assertJsonPath('data.assessment.should_warn', true)
            ->assertJsonPath('data.is_outdoor', true);
    }

    public function test_weather_returns_severe_for_thunderstorm(): void
    {
        $weatherData = $this->makeWeatherData(
            conditionCode: 200,
            conditionMain: 'Thunderstorm',
        );

        $this->mock(CachedWeatherClient::class, function (MockInterface $mock) use ($weatherData) {
            $mock->shouldReceive('getCurrentWeather')->andReturn($weatherData);
            $mock->shouldReceive('getForecast')->andReturn([]);
        });

        $session  = $this->sessionWithMidpoint(['venue_type' => 'park']);
        $response = $this->getJson("/api/v1/sessions/{$session->short_code}/weather");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assessment.severity', 'severe')
            ->assertJsonPath('data.assessment.is_safe', false);
    }
}
