<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CastVoteRequest;
use App\Http\Requests\Api\V1\ConfirmVenueRequest;
use App\Http\Resources\SessionResource;
use App\Jobs\AutoConfirmVenueJob;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\VenueVoteRepositoryInterface;
use App\Services\Session\ParticipantAuthenticator;
use App\Services\Session\SummaryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaborationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessionRepositoryInterface   $sessionRepository,
        private readonly VenueVoteRepositoryInterface $voteRepository,
        private readonly SummaryService               $summaryService,
        private readonly ParticipantAuthenticator     $participantAuthenticator,
    ) {}

    public function castVote(CastVoteRequest $request, string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        $participant = $this->participantAuthenticator->authenticate(
            $session,
            $request->participant_id,
            $request->access_token,
        );

        if ($participant === null) {
            return $this->errorResponse(
                'You must be a participant in this session to vote.',
                null,
                403
            );
        }

        $vote = $this->voteRepository->upsertVote(
            sessionId:       $session->id,
            participantId:   $participant->id,
            placeId:         $request->google_place_id,
            venueName:       $request->venue_name,
            participantName: $request->participant_name ?? $participant->name,
            vote:            $request->vote,
        );

        $voteSummary = $this->voteRepository->getVoteSummary($session->id, $participant->id);

        return $this->successResponse([
            'vote_summary' => $voteSummary,
            'your_vote'    => $vote->vote,
        ], 'Vote cast successfully.');
    }

    public function getVotes(Request $request, string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        // Anonymous callers still get tallies; identified ones also get my_vote.
        $participant = $this->participantAuthenticator->fromRequest($session, $request);

        $voteSummary = $this->voteRepository->getVoteSummary($session->id, $participant?->id);

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

    public function startVoting(Request $request, string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session || $session->expires_at->isPast()) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        if (!config('jumpa.voting.enabled')) {
            return $this->errorResponse('Voting is disabled.', null, 422);
        }

        if ($session->voting_starts_at !== null) {
            return $this->errorResponse('Voting has already started for this session.', null, 422);
        }

        $durationMinutes = (int) config('jumpa.voting.duration_minutes', 5);
        $session = $this->sessionRepository->startVoting($session->id, $durationMinutes);

        // Dispatch auto-confirm job with delay equal to voting duration.
        // With QUEUE_CONNECTION=sync (Render free tier), the delay is ignored and
        // the job runs immediately — use the sessions:auto-confirm scheduled command
        // as the reliable fallback. Upgrade to Redis for proper delayed job support.
        AutoConfirmVenueJob::dispatch($session->id)
            ->delay(now()->addMinutes($durationMinutes));

        $participant = $this->participantAuthenticator->fromRequest($session, $request);
        $summary     = $this->summaryService->buildSummary($session, $participant?->id);

        return $this->successResponse($summary, 'Voting started successfully.');
    }

    public function summary(Request $request, string $shortCode): JsonResponse
    {
        $session = $this->sessionRepository->findByShortCode($shortCode);

        if (!$session) {
            return $this->errorResponse('Session not found or has expired.', null, 404);
        }

        $participant = $this->participantAuthenticator->fromRequest($session, $request);
        $summary     = $this->summaryService->buildSummary($session, $participant?->id);

        return $this->successResponse($summary, 'Session summary retrieved successfully.');
    }
}
