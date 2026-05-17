<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\Venue\BestPickService;
use App\Services\Venue\RecommendationService;
use App\Services\Venue\VenueDiscoveryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly VenueDiscoveryService      $venueDiscovery,
        private readonly BestPickService            $bestPickService,
        private readonly RecommendationService      $recommendationService,
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

        $session->load('participants');

        $venues = $this->venueDiscovery->discover(
            lat:           (float) $session->midpoint_lat,
            lng:           (float) $session->midpoint_lng,
            venueType:     $session->venue_type,
            halalFilter:   (bool) $session->halal_filter,
            transportMode: $session->transport_mode,
            participants:  $session->participants->all(),
        );

        $venues = $this->bestPickService->selectBestPick($venues);

        $recommendation = $this->recommendationService->buildRecommendation($session, $venues);

        return $this->successResponse($recommendation, 'Recommendation retrieved successfully.');
    }
}
