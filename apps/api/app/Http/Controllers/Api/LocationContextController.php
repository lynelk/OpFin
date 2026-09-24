<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocationContext;
use App\Models\User;
use App\Services\GoogleMapsLocationService;
use App\Services\LocationContextService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class LocationContextController extends Controller
{
    public function __construct(
        private readonly LocationContextService $locations,
        private readonly GoogleMapsLocationService $google,
    ) {}

    public function status(): JsonResponse
    {
        return ApiResponse::success('Location capability status loaded.', [
            'google_maps_configured' => $this->google->enabled(),
            'static_maps_enabled' => $this->google->enabled() && (bool) config('services.google_maps.static_maps_enabled'),
            'routes_enabled' => $this->google->enabled() && (bool) config('services.google_maps.routes_enabled'),
            'background_tracking' => false,
            'default_precision' => 'approximate',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(LocationContextService::SUBJECT_TYPES)],
            'subject_id' => ['required', 'integer', 'min:1'],
        ]);

        return ApiResponse::success('Location contexts loaded.', [
            'locations' => $this->locations->listFor(
                $request->user(),
                (string) $validated['subject_type'],
                (int) $validated['subject_id'],
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(LocationContextService::SUBJECT_TYPES)],
            'subject_id' => ['required', 'integer', 'min:1'],
            'purpose' => ['required', Rule::in(LocationContextService::PURPOSES)],
            'source' => ['required', Rule::in(LocationContextService::SOURCES)],
            'precision_level' => ['required', Rule::in(LocationContextService::PRECISION_LEVELS)],
            'place_name' => ['nullable', 'string', 'max:255'],
            'formatted_address' => ['nullable', 'string', 'max:500'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'plus_code' => ['nullable', 'string', 'max:64'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'admin_area_1' => ['nullable', 'string', 'max:160'],
            'admin_area_2' => ['nullable', 'string', 'max:160'],
            'locality' => ['nullable', 'string', 'max:160'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy_metres' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'consent_purpose' => ['required', Rule::in(LocationContextService::PURPOSES)],
            'captured_at' => ['nullable', 'date'],
            'resolve_with_google' => ['sometimes', 'boolean'],
        ]);

        try {
            if (($validated['source'] ?? null) === 'google_place'
                && empty($validated['google_place_id'])) {
                return ApiResponse::error(
                    'Google-place provenance requires a server-resolved Place ID.',
                    422
                );
            }

            $attributes = $validated;
            unset($attributes['resolve_with_google']);

            if (! empty($validated['google_place_id'])) {
                $details = $this->google->placeDetails((string) $validated['google_place_id']);
                $attributes = array_merge($attributes, array_filter(
                    $details,
                    fn ($value) => $value !== null && $value !== ''
                ));
                $attributes['source'] = 'google_place';
            } elseif (($validated['resolve_with_google'] ?? false)
                && isset($validated['latitude'], $validated['longitude'])) {
                try {
                    $details = $this->google->reverseGeocode(
                        (float) $validated['latitude'],
                        (float) $validated['longitude'],
                    );
                    $attributes = array_merge($details, $attributes);
                } catch (InvalidArgumentException|RuntimeException) {
                    $attributes['metadata'] = array_merge(
                        $attributes['metadata'] ?? [],
                        ['google_resolution' => 'unavailable_at_capture']
                    );
                }
            }

            $context = $this->locations->save($request->user(), $attributes);

            return ApiResponse::success('Location context saved.', [
                'location' => $this->locations->present($context),
            ], 201);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 503);
        }
    }

    public function destroy(LocationContext $context, Request $request): JsonResponse
    {
        $this->locations->delete($request->user(), $context);

        return ApiResponse::success('Location context removed.', ['deleted' => true]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:160'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'session_token' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            return ApiResponse::success('Place suggestions loaded.', [
                'suggestions' => $this->google->autocomplete(
                    (string) $validated['query'],
                    $validated['country_code'] ?? null,
                    $validated['session_token'] ?? null,
                ),
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 503);
        }
    }

    public function staticMap(LocationContext $context, Request $request)
    {
        $this->locations->assertReadable($request->user(), $context);
        abort_if($context->latitude === null || $context->longitude === null, 422, 'This location has no map coordinates.');

        try {
            $map = $this->google->staticMap(
                (float) $context->latitude,
                (float) $context->longitude,
                (int) $request->query('zoom', 14),
            );

            return response($map->body(), 200, [
                'Content-Type' => $map->header('Content-Type') ?: 'image/png',
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 503);
        }
    }

    public function route(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_location_id' => ['required', 'integer', 'min:1'],
            'destination_location_id' => ['required', 'integer', 'min:1'],
            'travel_mode' => ['sometimes', Rule::in(['DRIVE', 'WALK', 'BICYCLE', 'TWO_WHEELER'])],
        ]);

        $origin = LocationContext::query()->findOrFail((int) $validated['origin_location_id']);
        $destination = LocationContext::query()->findOrFail((int) $validated['destination_location_id']);
        $this->locations->assertReadable($request->user(), $origin);
        $this->locations->assertReadable($request->user(), $destination);
        abort_if(
            $origin->latitude === null || $origin->longitude === null
                || $destination->latitude === null || $destination->longitude === null,
            422,
            'Both locations require map coordinates.'
        );

        try {
            return ApiResponse::success('Route calculated.', [
                'route' => $this->google->route(
                    (float) $origin->latitude,
                    (float) $origin->longitude,
                    (float) $destination->latitude,
                    (float) $destination->longitude,
                    (string) ($validated['travel_mode'] ?? 'DRIVE'),
                ),
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 503);
        }
    }

    public function nearbyServices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'numeric', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success('Nearby partner service points loaded.', [
            'service_points' => $this->locations->nearbyServicePoints(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) ($validated['radius_km'] ?? 25),
            ),
            'location_stored' => false,
        ]);
    }

    public function partnerNetwork(Request $request): JsonResponse
    {
        return ApiResponse::success('Partner location network loaded.', [
            'service_points' => $this->locations->partnerNetwork($request->user()),
            'individual_customer_locations_exposed' => false,
        ]);
    }

    public function insights(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole([User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS]), 403);

        return ApiResponse::success('Aggregate location insights loaded.', [
            'minimum_cohort' => 5,
            'individual_locations_exposed' => false,
            'rows' => $this->locations->aggregateInsights(5),
        ]);
    }
}
