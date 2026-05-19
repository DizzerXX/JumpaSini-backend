<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AnalyticsRepositoryInterface $analytics,
    ) {}

    public function overview(): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->overview(),
            'Analytics overview retrieved successfully.'
        );
    }

    public function sessions(Request $request): JsonResponse
    {
        $filters   = $request->only(['status', 'date_from', 'date_to', 'venue_type']);
        $paginator = $this->analytics->paginateSessions($filters);

        return $this->successResponse([
            'sessions'   => $paginator->items(),
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], 'Sessions retrieved successfully.');
    }

    public function venues(): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->venueStats(),
            'Venue analytics retrieved successfully.'
        );
    }

    public function halalReports(Request $request): JsonResponse
    {
        $filters   = $request->only(['pending_review']);
        $paginator = $this->analytics->paginateHalalReports($filters);

        return $this->successResponse([
            'reports'    => $paginator->items(),
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], 'Halal reports retrieved successfully.');
    }
}
