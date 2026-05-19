<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HalalOverrideRequest;
use App\Http\Requests\Admin\StoreVenueRequest;
use App\Http\Requests\Admin\UpdateHalalStatusRequest;
use App\Http\Requests\Admin\UpdateVenueRequest;
use App\Repositories\Contracts\VenueHalalReportRepositoryInterface;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueManagementController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly VenueRepositoryInterface           $venueRepository,
        private readonly VenueHalalReportRepositoryInterface $halalReportRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'halal', 'search']);
        $paginator = $this->venueRepository->paginateForAdmin($filters);

        return $this->successResponse([
            'venues'     => $paginator->items(),
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], 'Venues retrieved successfully.');
    }

    public function store(StoreVenueRequest $request): JsonResponse
    {
        $venue = $this->venueRepository->create($request->validated());

        return $this->successResponse($venue, 'Venue created successfully.', 201);
    }

    public function update(UpdateVenueRequest $request, string $id): JsonResponse
    {
        $venue = $this->venueRepository->findById($id);

        if (!$venue) {
            return $this->errorResponse('Venue not found.', null, 404);
        }

        $updated = $this->venueRepository->update($venue, $request->validated());

        return $this->successResponse($updated, 'Venue updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $venue = $this->venueRepository->findById($id);

        if (!$venue) {
            return $this->errorResponse('Venue not found.', null, 404);
        }

        $this->venueRepository->softDelete($venue);

        return $this->successResponse(null, 'Venue deactivated successfully.');
    }

    public function updateHalal(UpdateHalalStatusRequest $request, string $id): JsonResponse
    {
        $venue = $this->venueRepository->findById($id);

        if (!$venue) {
            return $this->errorResponse('Venue not found.', null, 404);
        }

        $updated = $this->venueRepository->update($venue, $request->validated());

        return $this->successResponse($updated, 'Halal status updated successfully.');
    }

    public function halalReports(string $id): JsonResponse
    {
        $venue = $this->venueRepository->findById($id);

        if (!$venue) {
            return $this->errorResponse('Venue not found.', null, 404);
        }

        $reports = $this->halalReportRepository->getByVenueId($id);

        return $this->successResponse([
            'venue_id' => $id,
            'reports'  => $reports->values(),
            'summary'  => [
                'total'     => $reports->count(),
                'halal'     => $reports->where('reported_as_halal', true)->count(),
                'not_halal' => $reports->where('reported_as_halal', false)->count(),
            ],
        ], 'Halal reports retrieved successfully.');
    }

    public function halalOverride(HalalOverrideRequest $request, string $id): JsonResponse
    {
        $venue = $this->venueRepository->findById($id);

        if (!$venue) {
            return $this->errorResponse('Venue not found.', null, 404);
        }

        $updated = $this->venueRepository->update($venue, [
            'is_halal'          => $request->boolean('is_halal'),
            'is_halal_verified' => true,
            'admin_notes'       => $request->input('notes'),
        ]);

        return $this->successResponse($updated, 'Halal status overridden by admin.');
    }
}
