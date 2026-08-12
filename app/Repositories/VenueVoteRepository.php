<?php

namespace App\Repositories;

use App\Models\VenueVote;
use App\Repositories\Contracts\VenueVoteRepositoryInterface;
use Illuminate\Support\Collection;

class VenueVoteRepository implements VenueVoteRepositoryInterface
{
    public function upsertVote(
        string $sessionId,
        string $participantId,
        string $placeId,
        string $venueName,
        ?string $participantName,
        string $vote
    ): VenueVote {
        // Keyed on participant_id, never participant_name — two participants who both
        // skipped entering a name must still cast two distinct votes.
        $existing = VenueVote::where('session_id', $sessionId)
            ->where('google_place_id', $placeId)
            ->where('participant_id', $participantId)
            ->first();

        if ($existing) {
            $existing->update([
                'vote'             => $vote,
                'participant_name' => $participantName,
            ]);
            return $existing->fresh();
        }

        return VenueVote::create([
            'session_id'       => $sessionId,
            'participant_id'   => $participantId,
            'google_place_id'  => $placeId,
            'venue_name'       => $venueName,
            'participant_name' => $participantName,
            'vote'             => $vote,
        ]);
    }

    public function getVotesForSession(string $sessionId): Collection
    {
        return VenueVote::forSession($sessionId)->get();
    }

    public function getVoteSummary(string $sessionId): array
    {
        $votes = $this->getVotesForSession($sessionId);

        $summary = [];
        foreach ($votes as $vote) {
            $placeId = $vote->google_place_id;
            if (!isset($summary[$placeId])) {
                $summary[$placeId] = ['up' => 0, 'down' => 0, 'total' => 0];
            }
            $summary[$placeId][$vote->vote]++;
            $summary[$placeId]['total']++;
        }

        return $summary;
    }
}
