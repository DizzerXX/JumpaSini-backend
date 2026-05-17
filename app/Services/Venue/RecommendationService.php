<?php

namespace App\Services\Venue;

use App\Models\Session;

class RecommendationService
{
    public function __construct(
        private readonly BestPickService $bestPickService,
    ) {}

    /**
     * Build a structured recommendation summary from a session and its ranked venues.
     *
     * @param  Session $session      Session with participants already loaded
     * @param  array   $venueResults Array of venue arrays (output of BestPickService::selectBestPick)
     */
    public function buildRecommendation(Session $session, array $venueResults): array
    {
        $bestVenue = $this->extractBestVenue($venueResults);

        $travelTimes        = $bestVenue['travel_times'] ?? [];
        $participantSummary = $this->buildParticipantSummary($session, $travelTimes);
        $fairnessScore      = $this->bestPickService->calculateFairness($travelTimes);

        return [
            'session_short_code'  => $session->short_code,
            'midpoint'            => [
                'lat' => $session->midpoint_lat,
                'lng' => $session->midpoint_lng,
            ],
            'best_venue'          => $bestVenue,
            'participant_summary' => $participantSummary,
            'fairness_score'      => round($fairnessScore, 4),
            'transport_mode'      => $session->transport_mode,
            'venue_type'          => $session->venue_type,
            'calculated_at'       => now()->toIso8601String(),
        ];
    }

    /** Returns the venue marked as best pick, falling back to the first result. */
    private function extractBestVenue(array $venueResults): array
    {
        foreach ($venueResults as $venue) {
            if ($venue['is_best_pick'] ?? false) {
                return $venue;
            }
        }

        return $venueResults[0] ?? [];
    }

    /** Maps session participants to their travel time summary for the best venue. */
    private function buildParticipantSummary(Session $session, array $travelTimes): array
    {
        return $session->participants->map(function ($participant) use ($travelTimes): array {
            return [
                'participant_id'      => $participant->id,
                'name'                => $participant->name,
                'travel_time_to_best' => $travelTimes[$participant->id] ?? null,
                'starting_address'    => $participant->address,
            ];
        })->values()->all();
    }
}
