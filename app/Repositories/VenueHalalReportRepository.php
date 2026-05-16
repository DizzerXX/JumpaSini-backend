<?php

namespace App\Repositories;

use App\Models\VenueHalalReport;
use App\Repositories\Contracts\VenueHalalReportRepositoryInterface;

class VenueHalalReportRepository implements VenueHalalReportRepositoryInterface
{
    public function create(array $data): VenueHalalReport
    {
        return VenueHalalReport::create($data);
    }

    public function countByPlaceId(string $googlePlaceId): array
    {
        $reports = VenueHalalReport::where('google_place_id', $googlePlaceId)->get();

        return [
            'total' => $reports->count(),
            'halal' => $reports->where('reported_as_halal', true)->count(),
        ];
    }
}
