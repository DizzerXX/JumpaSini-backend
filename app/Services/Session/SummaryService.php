<?php

namespace App\Services\Session;

use App\Models\Session;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Repositories\Contracts\VenueVoteRepositoryInterface;
use App\Services\Venue\BestPickService;
use App\Services\Venue\RecommendationService;
use App\Services\Venue\VenueDiscoveryService;

class SummaryService
{
    public function __construct(
        private readonly VenueVoteRepositoryInterface $voteRepository,
        private readonly VenueRepositoryInterface     $venueRepository,
        private readonly VenueDiscoveryService        $venueDiscovery,
        private readonly BestPickService              $bestPickService,
        private readonly RecommendationService        $recommendationService,
    ) {}

    public function buildSummary(Session $session): array
    {
        $session->loadMissing('participants');

        $voteSummary     = $this->voteRepository->getVoteSummary($session->id);
        $confirmedVenue  = $this->resolveConfirmedVenue($session);
        $recommendation  = $this->resolveRecommendation($session);

        return [
            'session'          => $this->formatSession($session),
            'participants'     => $session->participants->map(fn ($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'address'   => $p->address,
            ])->values()->all(),
            'midpoint'         => $session->midpoint_lat !== null ? [
                'lat' => $session->midpoint_lat,
                'lng' => $session->midpoint_lng,
            ] : null,
            'confirmed_venue'  => $confirmedVenue,
            'vote_summary'     => $voteSummary,
            'recommendation'   => $recommendation,
            'voting_state'     => $this->buildVotingState($session, $voteSummary),
            'is_complete'      => $session->status === 'completed',
            'expires_at'       => $session->expires_at?->toIso8601String(),
        ];
    }

    private function formatSession(Session $session): array
    {
        return [
            'id'                      => $session->id,
            'short_code'              => $session->short_code,
            'transport_mode'          => $session->transport_mode,
            'venue_type'              => $session->venue_type,
            'halal_filter'            => $session->halal_filter,
            'status'                  => $session->status,
            'session_confirmed_venue' => $session->session_confirmed_venue,
            'session_confirmed_at'    => $session->session_confirmed_at?->toIso8601String(),
            'expires_at'              => $session->expires_at?->toIso8601String(),
            'shareable_url'           => $session->shareable_url,
        ];
    }

    private function buildVotingState(Session $session, array $voteSummary): array
    {
        $now    = now();
        $active = $session->voting_starts_at !== null
               && $session->voting_ends_at !== null
               && $session->voting_starts_at->lte($now)
               && $session->voting_ends_at->gte($now);

        $secondsRemaining = ($session->voting_ends_at !== null && $session->voting_ends_at->gte($now))
            ? (int) $now->diffInSeconds($session->voting_ends_at)
            : 0;

        $winningVenue = $this->resolveWinningVenue($voteSummary);

        return [
            'active'                    => $active,
            'ends_at'                   => $session->voting_ends_at?->toIso8601String(),
            'seconds_remaining'         => $secondsRemaining,
            'winning_venue'             => $winningVenue,
            'voting_confirmed_venue'    => $session->voting_confirmed_venue,
        ];
    }

    private function resolveWinningVenue(array $voteSummary): ?string
    {
        if (empty($voteSummary)) {
            return null;
        }

        $best      = null;
        $bestScore = -1;

        foreach ($voteSummary as $placeId => $counts) {
            $score = ($counts['up'] ?? 0) - ($counts['down'] ?? 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $placeId;
            }
        }

        return $best;
    }

    private function resolveConfirmedVenue(Session $session): ?array
    {
        if ($session->session_confirmed_venue === null) {
            return null;
        }

        $venue = $this->venueRepository->findByGooglePlaceId($session->session_confirmed_venue);

        if ($venue === null) {
            return ['google_place_id' => $session->session_confirmed_venue];
        }

        return [
            'google_place_id' => $venue->google_place_id,
            'name'            => $venue->name,
            'address'         => $venue->address,
            'latitude'        => $venue->latitude,
            'longitude'       => $venue->longitude,
            'google_rating'   => $venue->google_rating,
            'is_halal'        => $venue->is_halal,
            'is_halal_verified' => $venue->is_halal_verified,
        ];
    }

    private function resolveRecommendation(Session $session): ?array
    {
        if ($session->midpoint_lat === null) {
            return null;
        }

        try {
            $venues = $this->venueDiscovery->discover(
                lat:           (float) $session->midpoint_lat,
                lng:           (float) $session->midpoint_lng,
                venueType:     $session->venue_type,
                halalFilter:   (bool) $session->halal_filter,
                transportMode: $session->transport_mode,
                participants:  $session->participants->all(),
            );

            $venues = $this->bestPickService->selectBestPick($venues);

            return $this->recommendationService->buildRecommendation($session, $venues);
        } catch (\Throwable) {
            return null;
        }
    }
}
