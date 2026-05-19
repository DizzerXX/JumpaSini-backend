<?php

namespace App\Repositories\Contracts;

use App\Models\VenueHalalReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface VenueHalalReportRepositoryInterface
{
    public function create(array $data): VenueHalalReport;

    /** Returns ['total' => int, 'halal' => int] for all reports matching this place ID. */
    public function countByPlaceId(string $googlePlaceId): array;

    public function getByVenueId(string $venueId): Collection;

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator;
}
