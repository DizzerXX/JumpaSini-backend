<?php

namespace App\Services;

class BoundaryValidationService
{
    private const float MIN_LAT = 2.6000;
    private const float MAX_LAT = 3.9000;
    private const float MIN_LNG = 101.2000;
    private const float MAX_LNG = 102.0000;

    public function isWithinKLSelangor(float $lat, float $lng): bool
    {
        return $lat >= self::MIN_LAT
            && $lat <= self::MAX_LAT
            && $lng >= self::MIN_LNG
            && $lng <= self::MAX_LNG;
    }
}
