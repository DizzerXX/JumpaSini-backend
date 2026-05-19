<?php

namespace App\Services\Weather;

use Carbon\Carbon;

class WeatherData
{
    public function __construct(
        public readonly int    $conditionCode,
        public readonly string $conditionMain,
        public readonly string $conditionDescription,
        public readonly float  $temperature,
        public readonly float  $feelsLike,
        public readonly int    $humidity,
        public readonly float  $windSpeed,
        public readonly float  $rainMmPerHour,
        public readonly string $icon,
        public readonly Carbon $timestamp,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $weather = $data['weather'][0] ?? [];
        $main    = $data['main']       ?? [];
        $wind    = $data['wind']       ?? [];
        $rain    = $data['rain']       ?? [];

        $rainMm = (float) ($rain['1h'] ?? $rain['3h'] ?? 0.0);

        return new self(
            conditionCode:        (int)    ($weather['id']          ?? 0),
            conditionMain:        (string) ($weather['main']        ?? ''),
            conditionDescription: (string) ($weather['description'] ?? ''),
            temperature:          (float)  ($main['temp']           ?? 0.0),
            feelsLike:            (float)  ($main['feels_like']     ?? 0.0),
            humidity:             (int)    ($main['humidity']       ?? 0),
            windSpeed:            (float)  ($wind['speed']          ?? 0.0),
            rainMmPerHour:        $rainMm,
            icon:                 (string) ($weather['icon']        ?? ''),
            timestamp:            Carbon::createFromTimestamp($data['dt'] ?? now()->timestamp),
        );
    }
}
