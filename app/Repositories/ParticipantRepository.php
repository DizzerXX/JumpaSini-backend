<?php

namespace App\Repositories;

use App\Models\Participant;
use App\Repositories\Contracts\ParticipantRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ParticipantRepository implements ParticipantRepositoryInterface
{
    public function create(array $data): Participant
    {
        return Participant::create($data);
    }

    public function findBySession(string $sessionId): Collection
    {
        return Participant::where('session_id', $sessionId)->get();
    }

    public function deleteBySession(string $sessionId): int
    {
        return Participant::where('session_id', $sessionId)->delete();
    }
}
