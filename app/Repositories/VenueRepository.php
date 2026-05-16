<?php

namespace App\Repositories;

use App\Models\Venue;
use App\Repositories\Contracts\VenueRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VenueRepository implements VenueRepositoryInterface
{
    public function findNearMidpoint(float $lat, float $lng, string $type, int $radiusMeters): Collection
    {
        // Haversine formula in raw SQL to find venues within radius
        return Venue::active()
            ->byType($type)
            ->selectRaw(
                '*, ( 6371000 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance_meters',
                [$lat, $lng, $lat]
            )
            ->having('distance_meters', '<=', $radiusMeters)
            ->orderBy('distance_meters')
            ->get();
    }

    public function findByType(string $type): Collection
    {
        return Venue::active()->byType($type)->get();
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
}
