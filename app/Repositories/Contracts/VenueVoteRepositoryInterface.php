<?php

namespace App\Repositories\Contracts;

use App\Models\VenueVote;
use Illuminate\Support\Collection;

interface VenueVoteRepositoryInterface
{
    public function upsertVote(
        string $sessionId,
        string $participantId,
        string $placeId,
        string $venueName,
        ?string $participantName,
        string $vote
    ): VenueVote;

    public function getVotesForSession(string $sessionId): Collection;

    /**
     * Vote tallies keyed by google_place_id.
     *
     * When $participantId is given, each entry also carries `my_vote` — that
     * participant's own vote ('up'/'down'), or null if it has not voted on it.
     */
    public function getVoteSummary(string $sessionId, ?string $participantId = null): array;
}
