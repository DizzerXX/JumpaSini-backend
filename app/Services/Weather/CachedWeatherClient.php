<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherServiceException;
use Illuminate\Support\Facades\Cache;

class CachedWeatherClient
{
    public function __construct(
        private readonly OpenWeatherMapClient $client,
    ) {}

    /**
     * @throws WeatherServiceException
     */
    public function getCurrentWeather(float $lat, float $lng): WeatherData
    {
        $key = $this->cacheKey('current', $lat, $lng);
        $ttl = (int) config('jumpa.weather.cache_ttl');

        return Cache::remember($key, $ttl, fn () => $this->client->getCurrentWeather($lat, $lng));
    }

    /**
     * @return WeatherData[]
     * @throws WeatherServiceException
     */
    public function getForecast(float $lat, float $lng, int $hours = 24): array
    {
        $key = $this->cacheKey('forecast', $lat, $lng);
        $ttl = (int) config('jumpa.weather.cache_ttl');

        return Cache::remember($key, $ttl, fn () => $this->client->getForecast($lat, $lng, $hours));
    }

    private function cacheKey(string $method, float $lat, float $lng): string
    {
        return "weather:{$method}:{$lat}:{$lng}";
    }
}
