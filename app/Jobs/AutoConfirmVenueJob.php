<?php

namespace App\Jobs;

use App\Models\Session;
use App\Models\Venue;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\VenueVoteRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoConfirmVenueJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $sessionId) {}

    public function handle(
        SessionRepositoryInterface    $sessionRepository,
        VenueVoteRepositoryInterface  $voteRepository,
    ): void {
        $session = Session::find($this->sessionId);

        if ($session === null || $session->expires_at->isPast()) {
            return;
        }

        // Skip if voting has not ended yet, or if already confirmed
        if ($session->voting_ends_at === null || $session->voting_ends_at->isFuture()) {
            return;
        }

        if ($session->voting_confirmed_venue !== null) {
            return;
        }

        $voteSummary = $voteRepository->getVoteSummary($this->sessionId);

        $winner = $this->resolveWinner($voteSummary);

        if ($winner === null) {
            Log::info('AutoConfirmVenueJob: no votes cast, skipping', ['session_id' => $this->sessionId]);
            return;
        }

        $sessionRepository->autoConfirmVotingVenue($this->sessionId, $winner);

        Log::info('AutoConfirmVenueJob: confirmed venue', [
            'session_id' => $this->sessionId,
            'place_id'   => $winner,
        ]);
    }

    private function resolveWinner(array $voteSummary): ?string
    {
        if (empty($voteSummary)) {
            return null;
        }

        // Group place IDs by net score (up - down)
        $scored = [];
        foreach ($voteSummary as $placeId => $counts) {
            $scored[$placeId] = ($counts['up'] ?? 0) - ($counts['down'] ?? 0);
        }

        $maxScore = max($scored);

        if ($maxScore <= 0) {
            return null;
        }

        $tied = array_keys(array_filter($scored, fn ($s) => $s === $maxScore));

        if (count($tied) === 1) {
            return $tied[0];
        }

        // Tie-break: pick venue with higher Google rating
        return $this->tieBreakByRating($tied);
    }

    private function tieBreakByRating(array $placeIds): string
    {
        $best       = $placeIds[0];
        $bestRating = 0.0;

        foreach ($placeIds as $placeId) {
            $venue  = Venue::where('google_place_id', $placeId)->first();
            $rating = $venue?->google_rating ?? 0.0;

            if ($rating > $bestRating) {
                $bestRating = $rating;
                $best       = $placeId;
            }
        }

        return $best;
    }
}
