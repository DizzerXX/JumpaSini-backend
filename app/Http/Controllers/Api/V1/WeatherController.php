<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\Weather\WeatherAssessmentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly WeatherAssessmentService   $weatherAssessment,
    ) {}

    public function show(string $shortCode): JsonResponse
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

        $lat       = (float) $session->midpoint_lat;
        $lng       = (float) $session->midpoint_lng;
        $venueType = $session->venue_type;

        $outdoorTypes = (array) config('jumpa.weather.outdoor_types', []);
        $isOutdoor    = in_array($venueType, $outdoorTypes, true);

        $assessment = $this->weatherAssessment->assess($lat, $lng, $venueType);
        $forecast   = $isOutdoor ? $this->weatherAssessment->assessForecast($lat, $lng) : [];

        return $this->successResponse(
            [
                'assessment' => [
                    'severity'    => $assessment->severity,
                    'headline'    => $assessment->headline,
                    'message'     => $assessment->message,
                    'emoji'       => $assessment->emoji,
                    'should_warn' => $assessment->shouldWarn,
                    'is_safe'     => $assessment->isSafe,
                    'temperature' => $assessment->temperature,
                    'humidity'    => $assessment->humidity,
                ],
                'forecast'   => $forecast,
                'venue_type' => $venueType,
                'is_outdoor' => $isOutdoor,
                'midpoint'   => ['lat' => $lat, 'lng' => $lng],
            ],
            'Weather assessment retrieved successfully.'
        );
    }
}
