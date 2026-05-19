<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportHalalRequest;
use App\Models\Venue;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\VenueHalalReportRepositoryInterface;
use App\Services\Venue\BestPickService;
use App\Services\Venue\VenueDiscoveryService;
use App\Services\Weather\WeatherAssessment;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class VenueController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessionRepositoryInterface           $sessionRepository,
        private readonly VenueDiscoveryService                $venueDiscovery,
        private readonly VenueHalalReportRepositoryInterface  $reportRepository,
        private readonly BestPickService                      $bestPickService,
    ) {}

    public function index(string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        if ($session->midpoint_lat === null) {
            return $this->errorResponse(
                'Midpoint not yet calculated. Call POST /sessions/{code}/midpoint first.',
                null,
                422
            );
        }

        $session->load('participants');

        $result = $this->venueDiscovery->discover(
            lat:           (float) $session->midpoint_lat,
            lng:           (float) $session->midpoint_lng,
            venueType:     $session->venue_type,
            halalFilter:   (bool) $session->halal_filter,
            transportMode: $session->transport_mode,
            participants:  $session->participants->all(),
        );

        $venues  = $this->bestPickService->selectBestPick($result['venues']);
        $weather = $result['weather'];

        return $this->successResponse(
            [
                'venues'  => $venues,
                'weather' => $weather ? $this->formatWeather($weather) : null,
            ],
            'Venues retrieved successfully.'
        );
    }

    private function formatWeather(WeatherAssessment $w): array
    {
        return [
            'severity'    => $w->severity,
            'headline'    => $w->headline,
            'message'     => $w->message,
            'emoji'       => $w->emoji,
            'should_warn' => $w->shouldWarn,
            'is_safe'     => $w->isSafe,
            'temperature' => $w->temperature,
            'humidity'    => $w->humidity,
            'forecast'    => $w->forecast,
        ];
    }

    public function reportHalal(ReportHalalRequest $request, string $placeId): JsonResponse
    {
        // Find seeded venue by google_place_id (may be null if Google-only)
        $venue = Venue::where('google_place_id', $placeId)->first();

        $this->reportRepository->create([
            'venue_id'          => $venue?->id,
            'google_place_id'   => $placeId,
            'reported_as_halal' => $request->boolean('reported_as_halal'),
            'session_id'        => $request->input('session_id'),
        ]);

        // Recalculate majority vote and update the seeded venue if it exists
        if ($venue) {
            $counts  = $this->reportRepository->countByPlaceId($placeId);
            $isHalal = $counts['total'] > 0 && ($counts['halal'] / $counts['total']) > 0.6;

            $venue->update(['is_halal' => $isHalal]);
        }

        return $this->successResponse(null, 'Halal report submitted successfully.');
    }
}
