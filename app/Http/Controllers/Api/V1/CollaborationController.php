<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CastVoteRequest;
use App\Http\Requests\Api\V1\ConfirmVenueRequest;
use App\Http\Resources\SessionResource;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\VenueVoteRepositoryInterface;
use App\Services\Session\SummaryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class CollaborationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessionRepositoryInterface  $sessionRepository,
        private readonly VenueVoteRepositoryInterface $voteRepository,
        private readonly SummaryService              $summaryService,
    ) {}

    public function castVote(CastVoteRequest $request, string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        $vote = $this->voteRepository->upsertVote(
            sessionId:       $session->id,
            placeId:         $request->google_place_id,
            venueName:       $request->venue_name,
            participantName: $request->participant_name,
            vote:            $request->vote,
        );

        $voteSummary = $this->voteRepository->getVoteSummary($session->id);

        return $this->successResponse([
            'vote_summary' => $voteSummary,
            'your_vote'    => $vote->vote,
        ], 'Vote cast successfully.');
    }

    public function getVotes(string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        $voteSummary = $this->voteRepository->getVoteSummary($session->id);

        return $this->successResponse([
            'vote_summary' => $voteSummary,
        ], 'Votes retrieved successfully.');
    }

    public function confirmVenue(ConfirmVenueRequest $request, string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        $session = $this->sessionRepository->confirmVenue($session->id, $request->google_place_id);

        return $this->successResponse(
            new SessionResource($session),
            'Venue confirmed successfully.'
        );
    }

    public function summary(string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        $summary = $this->summaryService->buildSummary($session);

        return $this->successResponse($summary, 'Session summary retrieved successfully.');
    }
}
