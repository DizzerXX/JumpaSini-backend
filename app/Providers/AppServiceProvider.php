<?php

namespace App\Providers;

use App\Services\GoogleMaps\CachedGoogleMapsClient;
use App\Services\GoogleMaps\GoogleMapsClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleMapsClient::class);

        $this->app->singleton(CachedGoogleMapsClient::class, function ($app) {
            return new CachedGoogleMapsClient($app->make(GoogleMapsClient::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
