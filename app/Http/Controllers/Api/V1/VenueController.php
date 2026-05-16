<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\Venue\VenueDiscoveryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class VenueController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly VenueDiscoveryService      $venueDiscovery,
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

        $venues = $this->venueDiscovery->discover(
            lat:           (float) $session->midpoint_lat,
            lng:           (float) $session->midpoint_lng,
            venueType:     $session->venue_type,
            halalFilter:   (bool) $session->halal_filter,
            transportMode: $session->transport_mode,
        );

        return $this->successResponse($venues, 'Venues retrieved successfully.');
    }
}
