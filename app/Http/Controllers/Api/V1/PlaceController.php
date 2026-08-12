<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlaceAutocompleteRequest;
use App\Services\GoogleMaps\CachedGoogleMapsClient;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxies Google Places/Geocoding for the dashboard and mobile app.
 *
 * Exists so no client ever holds a Places-capable API key: clients call these
 * endpoints, the backend holds the single server-side key, and every response
 * flows through the shared cache instead of being billed per keystroke per user.
 */
class PlaceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CachedGoogleMapsClient $mapsClient,
    ) {}

    public function autocomplete(PlaceAutocompleteRequest $request): JsonResponse
    {
        $predictions = $this->mapsClient->placeAutocomplete(
            $request->input('input'),
            $request->input('session_token'),
        );

        $suggestions = array_map(static fn (array $p): array => [
            'place_id'       => $p['place_id'] ?? '',
            'primary_text'   => $p['structured_formatting']['main_text'] ?? ($p['description'] ?? ''),
            'secondary_text' => $p['structured_formatting']['secondary_text'] ?? '',
            'full_text'      => $p['description'] ?? '',
        ], $predictions);

        return $this->successResponse($suggestions, 'Suggestions retrieved successfully.');
    }

    public function details(Request $request, string $placeId): JsonResponse
    {
        $place = $this->mapsClient->placeDetails(
            $placeId,
            $request->query('session_token'),
        );

        return $this->successResponse($place, 'Place details retrieved successfully.');
    }

    public function reverseGeocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $address = $this->mapsClient->reverseGeocode(
            (float) $validated['lat'],
            (float) $validated['lng'],
        );

        return $this->successResponse(
            ['address' => $address],
            'Address resolved successfully.'
        );
    }
}
