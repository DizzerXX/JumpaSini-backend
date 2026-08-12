<?php

use App\Exceptions\GoogleMapsException;
use App\Exceptions\WeatherServiceException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Upstream provider failures (Google Maps / OpenWeatherMap) — surface as a
        // clean 502 instead of a generic 500 with a debug stack trace.
        $exceptions->render(function (GoogleMapsException $e, Request $request) {
            if ($request->expectsJson()) {
                Log::error('Google Maps API failure', [
                    'api_status' => $e->apiStatus,
                    'message'    => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'We could not reach Google Maps right now. Please try again shortly.',
                    'errors'  => null,
                ], 502);
            }
        });

        $exceptions->render(function (WeatherServiceException $e, Request $request) {
            if ($request->expectsJson()) {
                Log::error('Weather service failure', [
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'We could not reach the weather service right now. Please try again shortly.',
                    'errors'  => null,
                ], 502);
            }
        });
    })->create();
