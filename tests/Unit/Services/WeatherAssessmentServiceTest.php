<?php

namespace Tests\Unit\Services;

use App\Services\Weather\CachedWeatherClient;
use App\Services\Weather\WeatherAssessmentService;
use App\Services\Weather\WeatherData;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class WeatherAssessmentServiceTest extends TestCase
{
    private function makeService(?CachedWeatherClient $client = null): WeatherAssessmentService
    {
        return new WeatherAssessmentService(
            $client ?? Mockery::mock(CachedWeatherClient::class)
        );
    }

    private function makeWeatherData(
        int    $conditionCode = 800,
        string $conditionMain = 'Clear',
        float  $rainMmPerHour = 0.0,
    ): WeatherData {
        return new WeatherData(
            conditionCode:        $conditionCode,
            conditionMain:        $conditionMain,
            conditionDescription: strtolower($conditionMain),
            temperature:          29.0,
            feelsLike:            30.0,
            humidity:             75,
            windSpeed:            2.5,
            rainMmPerHour:        $rainMmPerHour,
            icon:                 '01d',
            timestamp:            Carbon::now(),
        );
    }

    // ─── assess() ────────────────────────────────────────────────────────────

    public function test_indoor_venue_returns_good_without_api_call(): void
    {
        $mockClient = Mockery::mock(CachedWeatherClient::class);
        $mockClient->shouldNotReceive('getCurrentWeather');
        $mockClient->shouldNotReceive('getForecast');

        $service    = $this->makeService($mockClient);
        $assessment = $service->assess(3.1579, 101.7119, 'mall');

        $this->assertSame('good', $assessment->severity);
        $this->assertFalse($assessment->shouldWarn);
    }

    // ─── classifyWeather() ───────────────────────────────────────────────────

    public function test_thunderstorm_code_classified_as_severe(): void
    {
        $service = $this->makeService();
        $data    = $this->makeWeatherData(conditionCode: 211, conditionMain: 'Thunderstorm');

        $this->assertSame('severe', $service->classifyWeather($data));
    }

    public function test_heavy_rain_classified_as_warning(): void
    {
        $service = $this->makeService();
        $data    = $this->makeWeatherData(conditionCode: 501, conditionMain: 'Rain', rainMmPerHour: 4.0);

        $this->assertSame('warning', $service->classifyWeather($data));
    }

    public function test_drizzle_classified_as_caution(): void
    {
        $service = $this->makeService();
        $data    = $this->makeWeatherData(conditionCode: 300, conditionMain: 'Drizzle', rainMmPerHour: 0.5);

        $this->assertSame('caution', $service->classifyWeather($data));
    }

    public function test_light_rain_below_threshold_classified_as_caution(): void
    {
        $service = $this->makeService();
        // Rain code 500, but only 1.0 mm/h — below 2.5 threshold → caution not warning
        $data = $this->makeWeatherData(conditionCode: 500, conditionMain: 'Rain', rainMmPerHour: 1.0);

        $this->assertSame('caution', $service->classifyWeather($data));
    }

    public function test_clear_sky_classified_as_good(): void
    {
        $service = $this->makeService();
        $data    = $this->makeWeatherData(conditionCode: 800, conditionMain: 'Clear');

        $this->assertSame('good', $service->classifyWeather($data));
    }
}
