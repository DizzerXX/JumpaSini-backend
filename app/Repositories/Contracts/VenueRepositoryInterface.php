<?php

namespace App\Repositories\Contracts;

use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface VenueRepositoryInterface
{
    public function findNearMidpoint(float $lat, float $lng, string $type, int $radiusMeters): Collection;

    public function findByType(string $type): Collection;

    public function findByGooglePlaceId(string $googlePlaceId): ?Venue;

    public function create(array $data): Venue;

    public function updateFromGoogle(string $googlePlaceId, array $data): Venue;

    // Admin methods
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function findById(string $id): ?Venue;

    public function update(Venue $venue, array $data): Venue;

    public function softDelete(Venue $venue): void;
}
