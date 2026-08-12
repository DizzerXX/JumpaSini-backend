<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddParticipantRequest;
use App\Http\Requests\Api\V1\CreateSessionRequest;
use App\Http\Requests\Api\V1\RemoveParticipantRequest;
use App\Http\Resources\ParticipantResource;
use App\Http\Resources\SessionResource;
use App\Repositories\Contracts\ParticipantRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\Midpoint\MidpointCalculatorService;
use App\Services\Session\ParticipantAuthenticator;
use App\Services\ShortCodeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessionRepositoryInterface     $sessionRepository,
        private readonly ParticipantRepositoryInterface $participantRepository,
        private readonly ShortCodeService               $shortCodeService,
        private readonly MidpointCalculatorService      $midpointCalculator,
        private readonly ParticipantAuthenticator       $participantAuthenticator,
    ) {}

    public function store(CreateSessionRequest $request): JsonResponse
    {
        $session = $this->sessionRepository->create([
            'short_code'     => $this->shortCodeService->generate(),
            'transport_mode' => $request->transport_mode,
            'venue_type'     => $request->venue_type,
            'halal_filter'   => $request->boolean('halal_filter', true),
            'expires_at'     => now()->addHours(48),
        ]);

        $session->load('participants');

        return $this->successResponse(
            new SessionResource($session),
            'Session created successfully.',
            201
        );
    }

    public function show(string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        return $this->successResponse(
            new SessionResource($session),
            'Session retrieved successfully.'
        );
    }

    public function addParticipant(AddParticipantRequest $request, string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        if ($session->participants->count() >= 6) {
            return $this->errorResponse(
                'This session already has the maximum of 6 participants.',
                null,
                422
            );
        }

        $accessToken = Str::random(40);

        $participant = $this->participantRepository->create([
            'session_id'   => $session->id,
            'name'         => $request->name,
            'address'      => $request->address,
            'latitude'     => $request->latitude,
            'longitude'    => $request->longitude,
            'access_token' => $accessToken,
        ]);

        $session->refresh()->load('participants');

        try {
            $participants = $this->participantRepository->findBySession($session->id);

            if ($participants->count() >= 1) {
                $result = $this->midpointCalculator->calculate(
                    $participants->all(),
                    $session->transport_mode
                );
                $this->sessionRepository->updateMidpoint($session->id, $result);
                $session->refresh()->load('participants');
            }
        } catch (\Exception $e) {
            Log::error('Midpoint calculation failed', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $this->successResponse(
            [
                'participant'  => new ParticipantResource($participant),
                'session'      => new SessionResource($session),
                // Returned once, here only — the client must store this to prove
                // ownership later (e.g. to remove itself from the session).
                'access_token' => $accessToken,
            ],
            'Participant added successfully.',
            201
        );
    }

    public function calculateMidpoint(string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        $participants = $this->participantRepository->findBySession($session->id);

        if ($participants->count() < 1) {
            return $this->errorResponse(
                'At least 1 participant is required to calculate a midpoint.',
                null,
                422
            );
        }

        $result = $this->midpointCalculator->calculate($participants->all(), $session->transport_mode);

        $this->sessionRepository->updateMidpoint($session->id, $result);

        return $this->successResponse(
            [
                'lat'            => $result->lat,
                'lng'            => $result->lng,
                'is_fair'        => $result->isFair,
                'travel_times'   => $result->travelTimes,
                'iterations'     => $result->iterations,
                'transport_mode' => $result->transportMode,
            ],
            'Midpoint calculated successfully.'
        );
    }

    public function removeParticipant(RemoveParticipantRequest $request, string $shortCode, string $participantId): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        if ($session->participants->find($participantId) === null) {
            return $this->errorResponse('Participant not found in this session.', null, 404);
        }

        $participant = $this->participantAuthenticator->authenticate(
            $session,
            $participantId,
            $request->access_token,
        );

        if ($participant === null) {
            return $this->errorResponse('You are not authorized to remove this participant.', null, 403);
        }

        $participant->delete();
        $session->load('participants');

        return $this->successResponse(
            new SessionResource($session),
            'Participant removed successfully.'
        );
    }
}
