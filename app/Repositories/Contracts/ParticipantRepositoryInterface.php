<?php

namespace App\Repositories\Contracts;

use App\Models\Participant;
use Illuminate\Database\Eloquent\Collection;

interface ParticipantRepositoryInterface
{
    public function create(array $data): Participant;

    public function findBySession(string $sessionId): Collection;

    public function deleteBySession(string $sessionId): int;
}
