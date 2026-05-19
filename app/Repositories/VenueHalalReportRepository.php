<?php

namespace App\Repositories;

use App\Models\VenueHalalReport;
use App\Repositories\Contracts\VenueHalalReportRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    public function getByVenueId(string $venueId): Collection
    {
        return VenueHalalReport::where('venue_id', $venueId)->latest()->get();
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = VenueHalalReport::query()->latest();

        if (!empty($filters['pending_review'])) {
            $query->whereNotNull('venue_id')
                  ->whereHas('venue', fn ($q) => $q->whereNull('is_halal'));
        }

        return $query->paginate($perPage);
    }
}
