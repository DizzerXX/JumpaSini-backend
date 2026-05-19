<?php

use App\Http\Controllers\Api\V1\CollaborationController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Controllers\Api\V1\WeatherController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
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
    Route::get('sessions/{short_code}/summary', [CollaborationController::class, 'summary']);
    Route::get('sessions/{short_code}/weather', [WeatherController::class, 'show']);
    Route::post('venues/{place_id}/halal-report', [VenueController::class, 'reportHalal']);
});
