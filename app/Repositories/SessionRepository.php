<?php

namespace App\Repositories;

use App\Models\Session;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\Midpoint\MidpointResult;

class SessionRepository implements SessionRepositoryInterface
{
    public function create(array $data): Session
    {
        return Session::create($data);
    }

    public function findByShortCode(string $shortCode): ?Session
    {
        return Session::where('short_code', strtoupper($shortCode))
            ->with('participants')
            ->first();
    }

    public function markExpired(): int
    {
        return Session::where('expires_at', '<', now())
            ->where('status', '!=', 'completed')
            ->update(['status' => 'completed']);
    }

    public function updateMidpoint(string $sessionId, MidpointResult $result): Session
    {
        $session = Session::findOrFail($sessionId);

        $session->update([
            'midpoint_lat'           => $result->lat,
            'midpoint_lng'           => $result->lng,
            'midpoint_calculated_at' => now(),
            'midpoint_is_fair'       => $result->isFair,
            'midpoint_travel_times'  => $result->travelTimes,
            'status'                 => 'active',
        ]);

        return $session->fresh('participants');
    }

    public function confirmVenue(string $sessionId, string $placeId): Session
    {
        $session = Session::findOrFail($sessionId);

        $session->update([
            'session_confirmed_venue' => $placeId,
            'session_confirmed_at'    => now(),
            'status'                  => 'completed',
        ]);

        return $session->fresh('participants');
    }

    public function resetMidpoint(string $sessionId): void
    {
        Session::where('id', $sessionId)->update([
            'midpoint_lat'           => null,
            'midpoint_lng'           => null,
            'midpoint_calculated_at' => null,
            'midpoint_is_fair'       => null,
            'midpoint_travel_times'  => null,
        ]);
    }
}
