<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddParticipantRequest;
use App\Http\Requests\Api\V1\CreateSessionRequest;
use App\Http\Resources\ParticipantResource;
use App\Http\Resources\SessionResource;
use App\Repositories\Contracts\ParticipantRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\ShortCodeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class SessionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessionRepositoryInterface     $sessionRepository,
        private readonly ParticipantRepositoryInterface $participantRepository,
        private readonly ShortCodeService               $shortCodeService,
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

        $participant = $this->participantRepository->create([
            'session_id' => $session->id,
            'name'       => $request->name,
            'address'    => $request->address,
            'latitude'   => $request->latitude,
            'longitude'  => $request->longitude,
        ]);

        $session->load('participants');

        return $this->successResponse(
            [
                'participant' => new ParticipantResource($participant),
                'session'     => new SessionResource($session),
            ],
            'Participant added successfully.',
            201
        );
    }

    public function removeParticipant(string $shortCode, string $participantId): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        $participant = $session->participants->find($participantId);

        if (!$participant) {
            return $this->errorResponse('Participant not found in this session.', null, 404);
        }

        $participant->delete();
        $session->load('participants');

        return $this->successResponse(
            new SessionResource($session),
            'Participant removed successfully.'
        );
    }
}
