<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\VenueManagementController;
use App\Http\Controllers\Api\V1\CollaborationController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Controllers\Api\V1\WeatherController;
use Illuminate\Support\Facades\Route;

// Referenced by render.yaml's healthCheckPath — do not remove without updating
// the deploy config, or Render will mark the service unhealthy.
Route::get('/v1/health', fn() => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function () {
    // ─── Public Session Routes ────────────────────────────────────────────────
    Route::post('sessions', [SessionController::class, 'store']);
    Route::get('sessions/{short_code}', [SessionController::class, 'show']);
    Route::post('sessions/{short_code}/participants', [SessionController::class, 'addParticipant']);
    Route::delete('sessions/{short_code}/participants/{participant_id}', [SessionController::class, 'removeParticipant']);
    Route::post('sessions/{short_code}/midpoint', [SessionController::class, 'calculateMidpoint']);
    Route::get('sessions/{short_code}/venues', [VenueController::class, 'index']);
    Route::get('sessions/{short_code}/recommendation', [RecommendationController::class, 'show']);
    Route::post('sessions/{short_code}/votes', [CollaborationController::class, 'castVote']);
    Route::get('sessions/{short_code}/votes', [CollaborationController::class, 'getVotes']);
    Route::post('sessions/{short_code}/confirm', [CollaborationController::class, 'confirmVenue']);
    Route::post('sessions/{short_code}/voting/start', [CollaborationController::class, 'startVoting']);
    Route::get('sessions/{short_code}/summary', [CollaborationController::class, 'summary']);
    Route::get('sessions/{short_code}/weather', [WeatherController::class, 'show']);
    Route::post('venues/{place_id}/halal-report', [VenueController::class, 'reportHalal']);

    // ─── Places proxy ─────────────────────────────────────────────────────────
    // Keeps the Google key server-side and every lookup behind the shared cache.
    // Throttled: autocomplete fires per keystroke, so it is the easiest endpoint
    // to run up a Google bill with.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('places/autocomplete', [PlaceController::class, 'autocomplete']);
        Route::get('places/reverse-geocode', [PlaceController::class, 'reverseGeocode']);
        Route::get('places/{place_id}', [PlaceController::class, 'details']);
    });

    // ─── Admin Routes ─────────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        // Stricter than the global api throttle — this endpoint is a brute-force target.
        Route::post('login', [AdminController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AdminController::class, 'logout']);
            Route::get('me', [AdminController::class, 'me']);

            // Venue management
            Route::get('venues', [VenueManagementController::class, 'index']);
            Route::post('venues', [VenueManagementController::class, 'store']);
            Route::put('venues/{id}', [VenueManagementController::class, 'update']);
            Route::delete('venues/{id}', [VenueManagementController::class, 'destroy']);
            Route::patch('venues/{id}/halal', [VenueManagementController::class, 'updateHalal']);
            Route::get('venues/{id}/halal-reports', [VenueManagementController::class, 'halalReports']);
            Route::post('venues/{id}/halal-override', [VenueManagementController::class, 'halalOverride']);

            // Analytics
            Route::prefix('analytics')->group(function () {
                Route::get('overview', [AnalyticsController::class, 'overview']);
                Route::get('sessions', [AnalyticsController::class, 'sessions']);
                Route::get('venues', [AnalyticsController::class, 'venues']);
                Route::get('halal-reports', [AnalyticsController::class, 'halalReports']);
            });
        });
    });
});
