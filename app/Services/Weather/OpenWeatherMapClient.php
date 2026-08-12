<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherServiceException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OpenWeatherMapClient
{
    private const BASE_URL = 'https://api.openweathermap.org/data/2.5';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openweathermap.key', '');
    }

    /**
     * @throws WeatherServiceException
     */
    public function getCurrentWeather(float $lat, float $lng): WeatherData
    {
        $response = $this->client()->get(self::BASE_URL . '/weather', [
            'lat'   => $lat,
            'lon'   => $lng,
            'appid' => $this->apiKey,
            'units' => 'metric',
        ]);

        if ($response->failed()) {
            throw new WeatherServiceException(
                'OpenWeatherMap current weather request failed: ' . $response->status()
            );
        }

        return WeatherData::fromApiResponse($response->json());
    }

    /**
     * @return WeatherData[]
     * @throws WeatherServiceException
     */
    public function getForecast(float $lat, float $lng, int $hours = 24): array
    {
        $cnt = (int) ceil($hours / 3);

        $response = $this->client()->get(self::BASE_URL . '/forecast', [
            'lat'   => $lat,
            'lon'   => $lng,
            'appid' => $this->apiKey,
            'units' => 'metric',
            'cnt'   => $cnt,
        ]);

        if ($response->failed()) {
            throw new WeatherServiceException(
                'OpenWeatherMap forecast request failed: ' . $response->status()
            );
        }

        $list = $response->json('list', []);

        return array_map(
            static fn (array $entry) => WeatherData::fromApiResponse($entry),
            $list
        );
    }

    private function client(): PendingRequest
    {
        return Http::timeout(5)->retry(2, 200);
    }
}
