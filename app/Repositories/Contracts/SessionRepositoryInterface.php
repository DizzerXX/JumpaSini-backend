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
}
