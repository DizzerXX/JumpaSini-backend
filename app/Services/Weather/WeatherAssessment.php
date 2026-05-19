<?php

namespace App\Services\Weather;

class WeatherAssessment
{
    public function __construct(
        public readonly string $severity,
        public readonly string $headline,
        public readonly string $message,
        public readonly string $emoji,
        public readonly bool   $shouldWarn,
        public readonly bool   $isSafe,
        public readonly array  $forecast,
        public readonly float  $temperature,
        public readonly int    $humidity,
    ) {}
}
