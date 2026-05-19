<?php

return [

    'urls' => [
        'app'            => env('APP_URL', 'http://localhost:8000'),
        'frontend'       => env('FRONTEND_URL', 'http://localhost:4200'),
        'shareable_base' => env('FRONTEND_URL', 'http://localhost:4200') . '/session/',
    ],

    'boundary' => [
        'lat_min' => 2.6000,
        'lat_max' => 3.9000,
        'lng_min' => 101.2000,
        'lng_max' => 102.0000,
    ],

    'midpoint' => [
        'max_imbalance_minutes'    => env('MIDPOINT_MAX_IMBALANCE_MINUTES', 15),
        'max_adjustment_iterations' => env('MIDPOINT_MAX_ADJUSTMENT_ITERATIONS', 5),
        'adjustment_step'          => 0.1,
    ],

    'cache' => [
        'places_ttl'   => 86400,
        'distance_ttl' => 3600,
    ],

    'participants' => [
        'min' => 2,
        'max' => 6,
    ],

    'weather' => [
        'enabled'              => env('WEATHER_ENABLED', true),
        'outdoor_types'        => ['park', 'beach', 'waterfall', 'river'],
        'cache_ttl'            => 1800,
        'rain_threshold_mm'    => 2.5,
        'thunderstorm_codes'   => [200, 201, 202, 210, 211, 212, 221, 230, 231, 232],
        'rain_codes'           => [
            300, 301, 302, 310, 311, 312, 313, 314, 321,
            500, 501, 502, 503, 504, 511, 520, 521, 522, 531,
        ],
        'drizzle_codes'        => [300, 301, 302, 310, 311, 312, 313, 314, 321],
    ],

];
