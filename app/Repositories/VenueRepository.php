<?php

namespace App\Repositories;

use App\Helpers\GeoHelper;
use App\Models\Venue;
use App\Repositories\Contracts\VenueRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class VenueRepository implements VenueRepositoryInterface
{
    /**
     * Venues of a type within a radius of a point, nearest first.
     *
     * The database does the indexable part (type + active + bounding box) and PHP
     * does the exact distance. The haversine used to run as raw SQL, which relied on
     * acos/radians — Postgres has those, SQLite does not, so the query exploded the
     * moment the suite ran against the test database. Distance is computed in PHP
     * against the small set the bounding box returns, which is portable and reuses
     * the same GeoHelper the venue results are scored with.
     */
    public function findNearMidpoint(float $lat, float $lng, string $type, int $radiusMeters): Collection
    {
        [$latMin, $latMax, $lngMin, $lngMax] = $this->boundingBox($lat, $lng, $radiusMeters);

        return Venue::active()
            ->byType($type)
            ->whereBetween('latitude', [$latMin, $latMax])
            ->whereBetween('longitude', [$lngMin, $lngMax])
            ->get()
            ->map(function (Venue $venue) use ($lat, $lng): Venue {
                $venue->distance_meters = GeoHelper::haversineDistance(
                    $lat,
                    $lng,
                    (float) $venue->latitude,
                    (float) $venue->longitude,
                );

                return $venue;
            })
            ->filter(fn (Venue $venue): bool => $venue->distance_meters <= $radiusMeters)
            ->sortBy('distance_meters')
            ->values();
    }

    /**
     * Latitude/longitude window enclosing a radius around a point.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [latMin, latMax, lngMin, lngMax]
     */
    private function boundingBox(float $lat, float $lng, int $radiusMeters): array
    {
        $latDelta = $radiusMeters / 111_320.0;

        // Longitude degrees shrink toward the poles; guard against cos() hitting 0.
        $cosLat   = max(cos(deg2rad($lat)), 0.000001);
        $lngDelta = $radiusMeters / (111_320.0 * $cosLat);

        return [
            $lat - $latDelta,
            $lat + $latDelta,
            $lng - $lngDelta,
            $lng + $lngDelta,
        ];
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
