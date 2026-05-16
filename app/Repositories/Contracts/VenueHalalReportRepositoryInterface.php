<?php

namespace App\Repositories\Contracts;

use App\Models\VenueHalalReport;

interface VenueHalalReportRepositoryInterface
{
    public function create(array $data): VenueHalalReport;

    /** Returns ['total' => int, 'halal' => int] for all reports matching this place ID. */
    public function countByPlaceId(string $googlePlaceId): array;
}
