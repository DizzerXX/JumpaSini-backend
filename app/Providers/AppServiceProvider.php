<?php

namespace App\Providers;

use App\Services\GoogleMaps\CachedGoogleMapsClient;
use App\Services\GoogleMaps\GoogleMapsClient;
use App\Services\Weather\CachedWeatherClient;
use App\Services\Weather\OpenWeatherMapClient;
use App\Services\Weather\WeatherAssessmentService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleMapsClient::class);

        $this->app->singleton(CachedGoogleMapsClient::class, function ($app) {
            return new CachedGoogleMapsClient($app->make(GoogleMapsClient::class));
        });

        $this->app->singleton(OpenWeatherMapClient::class);

        $this->app->singleton(CachedWeatherClient::class, function ($app) {
            return new CachedWeatherClient($app->make(OpenWeatherMapClient::class));
        });

        $this->app->singleton(WeatherAssessmentService::class, function ($app) {
            return new WeatherAssessmentService($app->make(CachedWeatherClient::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
