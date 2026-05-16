<?php

namespace App\Repositories\Contracts;

use App\Models\Venue;
use Illuminate\Support\Collection;

interface VenueRepositoryInterface
{
    public function findNearMidpoint(float $lat, float $lng, string $type, int $radiusMeters): Collection;

    public function findByType(string $type): Collection;

    public function create(array $data): Venue;

    public function updateFromGoogle(string $googlePlaceId, array $data): Venue;
}
