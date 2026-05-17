<?php

namespace App\Repositories\Contracts;

use App\Models\VenueVote;
use Illuminate\Support\Collection;

interface VenueVoteRepositoryInterface
{
    public function upsertVote(
        string $sessionId,
        string $placeId,
        string $venueName,
        ?string $participantName,
        string $vote
    ): VenueVote;

    public function getVotesForSession(string $sessionId): Collection;

    public function getVoteSummary(string $sessionId): array;
}
