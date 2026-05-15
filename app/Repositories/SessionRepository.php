<?php

namespace App\Repositories;

use App\Models\Session;
use App\Repositories\Contracts\SessionRepositoryInterface;

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
}
