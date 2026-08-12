<?php

namespace App\Repositories;

use App\Models\Participant;
use App\Models\Session;
use App\Models\VenueHalalReport;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function overview(): array
    {
        $totalSessions    = Session::count();
        $totalParticipants = Participant::count();

        $popularVenueTypes = Session::select('venue_type', DB::raw('COUNT(*) as count'))
            ->groupBy('venue_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['type' => $row->venue_type, 'count' => $row->count])
            ->values()
            ->toArray();

        $popularAreas = $this->popularAreas();

        return [
            'total_sessions'              => $totalSessions,
            'active_sessions'             => Session::active()->count(),
            'completed_sessions'          => Session::where('status', 'completed')->count(),
            'total_participants'          => $totalParticipants,
            'avg_participants_per_session' => $totalSessions > 0
                ? round($totalParticipants / $totalSessions, 2)
                : 0,
            'total_venue_searches'        => Session::whereNotNull('midpoint_lat')->count(),
            'total_halal_reports'         => VenueHalalReport::count(),
            'popular_venue_types'         => $popularVenueTypes,
            'popular_areas'               => $popularAreas,
            'sessions_today'              => Session::whereDate('created_at', today())->count(),
            'sessions_this_week'          => Session::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    }

    /**
     * Most common areas, derived from the trailing comma-separated segment of each
     * address ("Jalan X, Bangsar, Kuala Lumpur" → "Kuala Lumpur").
     *
     * Grouped in PHP rather than SQL: the previous version used Postgres-only
     * SPLIT_PART/string_to_array, which locked the app to one driver and broke
     * the moment the suite ran against any other database.
     */
    private function popularAreas(int $limit = 10): array
    {
        $counts = Participant::query()
            ->select('address')
            ->pluck('address')
            ->map(function (?string $address): ?string {
                if ($address === null || trim($address) === '') {
                    return null;
                }

                $segments = array_filter(array_map('trim', explode(',', $address)), 'strlen');

                return empty($segments) ? null : end($segments);
            })
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take($limit);

        return $counts
            ->map(fn (int $count, string $area) => ['area' => $area, 'count' => $count])
            ->values()
            ->toArray();
    }

    public function paginateSessions(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Session::withCount('participants')->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['venue_type'])) {
            $query->where('venue_type', $filters['venue_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    public function venueStats(): array
    {
        $mostSearchedTypes = Session::select('venue_type', DB::raw('COUNT(*) as count'))
            ->whereNotNull('midpoint_lat')
            ->groupBy('venue_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['type' => $r->venue_type, 'count' => $r->count])
            ->values()
            ->toArray();

        $mostConfirmed = Session::select('session_confirmed_venue', DB::raw('COUNT(*) as count'))
            ->whereNotNull('session_confirmed_venue')
            ->groupBy('session_confirmed_venue')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['venue' => $r->session_confirmed_venue, 'count' => $r->count])
            ->values()
            ->toArray();

        $mostReported = VenueHalalReport::select(
            DB::raw('COALESCE(venue_id, google_place_id) as venue_key'),
            'venue_id',
            'google_place_id',
            DB::raw('COUNT(*) as total_reports'),
            // CASE rather than Postgres-only `::int` cast — keeps this driver-agnostic.
            DB::raw('SUM(CASE WHEN reported_as_halal THEN 1 ELSE 0 END) as halal_reports')
        )
            ->groupBy(DB::raw('COALESCE(venue_id, google_place_id)'), 'venue_id', 'google_place_id')
            ->orderByDesc('total_reports')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'venue_id'       => $r->venue_id,
                'google_place_id' => $r->google_place_id,
                'total_reports'  => $r->total_reports,
                'halal_reports'  => (int) $r->halal_reports,
            ])
            ->values()
            ->toArray();

        return [
            'most_searched_types' => $mostSearchedTypes,
            'most_confirmed'      => $mostConfirmed,
            'most_halal_reported' => $mostReported,
        ];
    }

    public function paginateHalalReports(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = VenueHalalReport::with('venue')->latest();

        if (!empty($filters['pending_review'])) {
            $query->whereNotNull('venue_id')
                  ->whereHas('venue', fn ($q) => $q->whereNull('is_halal'));
        }

        return $query->paginate($perPage);
    }
}
