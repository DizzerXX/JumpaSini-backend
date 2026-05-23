<?php

namespace App\Repositories\Contracts;

use App\Models\Session;
use App\Services\Midpoint\MidpointResult;

interface SessionRepositoryInterface
{
    public function create(array $data): Session;

    public function findByShortCode(string $shortCode): ?Session;

    public function markExpired(): int;

    public function updateMidpoint(string $sessionId, MidpointResult $result): Session;

    public function confirmVenue(string $sessionId, string $placeId): Session;

    public function resetMidpoint(string $sessionId): void;

    public function startVoting(string $sessionId, int $durationMinutes): Session;

    public function autoConfirmVotingVenue(string $sessionId, string $placeId): Session;
}
