<?php

namespace App\Repositories;

use App\Models\Venue;
use App\Repositories\Contracts\VenueRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class VenueRepository implements VenueRepositoryInterface
{
    public function findNearMidpoint(float $lat, float $lng, string $type, int $radiusMeters): Collection
    {
        $haversine = '6371000 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )';

        $inner = Venue::active()
            ->byType($type)
            ->selectRaw("*, ($haversine) AS distance_meters", [$lat, $lng, $lat])
            ->toBase();

        $rows = DB::query()
            ->fromSub($inner, 'subquery')
            ->where('distance_meters', '<=', $radiusMeters)
            ->orderBy('distance_meters')
            ->get();

        return Venue::hydrate($rows->map(fn ($r) => (array) $r)->all());
    }

    public function findByType(string $type): Collection
    {
        return Venue::active()->byType($type)->get();
    }

    public function findByGooglePlaceId(string $googlePlaceId): ?Venue
    {
        return Venue::where('google_place_id', $googlePlaceId)->first();
    }

    public function create(array $data): Venue
    {
        return Venue::create($data);
    }

    public function updateFromGoogle(string $googlePlaceId, array $data): Venue
    {
        $venue = Venue::where('google_place_id', $googlePlaceId)->firstOrFail();
        $venue->update($data);

        return $venue->fresh();
    }

    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Venue::query();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['halal'])) {
            if ($filters['halal'] === 'unverified') {
                $query->whereNull('is_halal');
            } elseif ($filters['halal'] === 'true' || $filters['halal'] === true) {
                $query->where('is_halal', true);
            } elseif ($filters['halal'] === 'false' || $filters['halal'] === false) {
                $query->where('is_halal', false);
            }
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('address', 'like', $term);
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function findById(string $id): ?Venue
    {
        return Venue::find($id);
    }

    public function update(Venue $venue, array $data): Venue
    {
        $venue->update($data);

        return $venue->fresh();
    }

    public function softDelete(Venue $venue): void
    {
        $venue->update(['is_active' => false]);
    }
}
