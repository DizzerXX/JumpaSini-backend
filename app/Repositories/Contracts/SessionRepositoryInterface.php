<?php

namespace App\Repositories\Contracts;

use App\Models\Session;

interface SessionRepositoryInterface
{
    public function create(array $data): Session;

    public function findByShortCode(string $shortCode): ?Session;

    public function markExpired(): int;
}
