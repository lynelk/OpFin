<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class GoogleMapsLocationService
{
    public function enabled(): bool
    {
        return (bool) config('services.google_maps.enabled')
            && trim((string) config('services.google_maps.server_api_key')) !== '';
    }

    public function autocomplete(
        string $query,
        ?string $countryCode = null,
        ?string $sessionToken = null,
    ): array {
        $this->assertEnabled();

        $payload = [
            'input' => trim($query),
            'languageCode' => 'en',
        ];
        if ($countryCode) {
            $payload['includedRegionCodes'] = [strtoupper($countryCode)];
        }
        if ($sessionToken) {
            $payload['sessionToken'] = $sessionToken;
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'X-Goog-Api-Key' => $this->key(),
                'X-Goog-FieldMask' => implode(',', [
                    'suggestions.placePrediction.placeId',
                    'suggestions.placePrediction.text.text',
                    'suggestions.placePrediction.structuredFormat.mainText.text',
                    'suggestions.placePrediction.structuredFormat.secondaryText.text',
                ]),
            ])
            ->post('https://places.googleapis.com/v1/places:autocomplete', $payload);

        $this->assertSuccessful($response, 'Google Places autocomplete');

        return collect($response->json('suggestions', []))
            ->map(function (array $suggestion) {
                $prediction = $suggestion['placePrediction'] ?? [];

                return [
                    'place_id' => $prediction['placeId'] ?? null,
                    'text' => $prediction['text']['text'] ?? null,
                    'main_text' => $prediction['structuredFormat']['mainText']['text'] ?? null,
                    'secondary_text' => $prediction['structuredFormat']['secondaryText']['text'] ?? null,
                ];
            })
            ->filter(fn (array $item) => filled($item['place_id']) && filled($item['text']))
            ->values()
            ->all();
    }

    public function placeDetails(string $placeId): array
    {
        $this->assertEnabled();

        $response = Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'X-Goog-Api-Key' => $this->key(),
                'X-Goog-FieldMask' => implode(',', [
                    'id',
                    'displayName',
                    'formattedAddress',
                    'location',
                    'addressComponents',
                    'plusCode',
                ]),
            ])
            ->get('https://places.googleapis.com/v1/places/'.rawurlencode($placeId));

        $this->assertSuccessful($response, 'Google Place details');

        return $this->normalisePlace($response->json());
    }

    public function reverseGeocode(float $latitude, float $longitude): array
    {
        $this->assertEnabled();

        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => $latitude.','.$longitude,
            'key' => $this->key(),
            'language' => 'en',
        ]);

        $this->assertSuccessful($response, 'Google reverse geocoding');
        $status = (string) $response->json('status');
        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw new RuntimeException('Google reverse geocoding returned '.$status.'.');
        }

        $result = $response->json('results.0');
        if (! is_array($result)) {
            return [
                'place_name' => null,
                'formatted_address' => null,
                'google_place_id' => null,
                'plus_code' => $response->json('plus_code.global_code'),
                'country_code' => null,
                'admin_area_1' => null,
                'admin_area_2' => null,
                'locality' => null,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        return $this->normaliseGeocode($result, $latitude, $longitude);
    }

    public function staticMap(float $latitude, float $longitude, int $zoom = 14): Response
    {
        $this->assertEnabled();
        if (! (bool) config('services.google_maps.static_maps_enabled')) {
            throw new InvalidArgumentException('Static maps are not enabled.');
        }

        $zoom = max(3, min(19, $zoom));

        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/staticmap', [
            'center' => $latitude.','.$longitude,
            'zoom' => $zoom,
            'size' => '640x320',
            'scale' => 1,
            'maptype' => 'roadmap',
            'markers' => 'color:red|'.$latitude.','.$longitude,
            'key' => $this->key(),
        ]);

        $this->assertSuccessful($response, 'Google Static Maps');

        return $response;
    }

    public function route(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude,
        string $travelMode = 'DRIVE',
    ): array {
        $this->assertEnabled();
        if (! (bool) config('services.google_maps.routes_enabled')) {
            throw new InvalidArgumentException('Google Routes is not enabled.');
        }

        $travelMode = strtoupper($travelMode);
        if (! in_array($travelMode, ['DRIVE', 'WALK', 'BICYCLE', 'TWO_WHEELER'], true)) {
            throw new InvalidArgumentException('Unsupported travel mode.');
        }

        $payload = [
            'origin' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $originLatitude,
                        'longitude' => $originLongitude,
                    ],
                ],
            ],
            'destination' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $destinationLatitude,
                        'longitude' => $destinationLongitude,
                    ],
                ],
            ],
            'travelMode' => $travelMode,
            'computeAlternativeRoutes' => false,
            'languageCode' => 'en',
            'units' => 'METRIC',
        ];
        if ($travelMode === 'DRIVE') {
            $payload['routingPreference'] = 'TRAFFIC_UNAWARE';
        }

        $response = Http::timeout(12)
            ->acceptJson()
            ->withHeaders([
                'X-Goog-Api-Key' => $this->key(),
                'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline',
            ])
            ->post('https://routes.googleapis.com/directions/v2:computeRoutes', $payload);

        $this->assertSuccessful($response, 'Google Routes');

        $route = $response->json('routes.0');
        if (! is_array($route)) {
            throw new RuntimeException('No route was returned for the selected locations.');
        }

        return [
            'distance_metres' => (int) ($route['distanceMeters'] ?? 0),
            'duration' => $route['duration'] ?? null,
            'encoded_polyline' => $route['polyline']['encodedPolyline'] ?? null,
            'travel_mode' => $travelMode,
        ];
    }

    public function mapsUrl(?string $placeId, ?float $latitude, ?float $longitude): ?string
    {
        if ($placeId) {
            return 'https://www.google.com/maps/search/?api=1&query_place_id='.rawurlencode($placeId)
                .'&query='.rawurlencode(($latitude !== null && $longitude !== null) ? $latitude.','.$longitude : 'place');
        }

        if ($latitude !== null && $longitude !== null) {
            return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($latitude.','.$longitude);
        }

        return null;
    }

    private function normalisePlace(array $place): array
    {
        $components = $place['addressComponents'] ?? [];
        $byType = function (string $type) use ($components): ?string {
            foreach ($components as $component) {
                if (in_array($type, $component['types'] ?? [], true)) {
                    return $component['longText'] ?? $component['shortText'] ?? null;
                }
            }

            return null;
        };

        $countryCode = null;
        foreach ($components as $component) {
            if (in_array('country', $component['types'] ?? [], true)) {
                $countryCode = $component['shortText'] ?? null;
                break;
            }
        }

        return [
            'place_name' => $place['displayName']['text'] ?? null,
            'formatted_address' => $place['formattedAddress'] ?? null,
            'google_place_id' => $place['id'] ?? null,
            'plus_code' => $place['plusCode']['globalCode'] ?? null,
            'country_code' => $countryCode ? strtoupper($countryCode) : null,
            'admin_area_1' => $byType('administrative_area_level_1'),
            'admin_area_2' => $byType('administrative_area_level_2'),
            'locality' => $byType('locality') ?? $byType('sublocality'),
            'latitude' => isset($place['location']['latitude']) ? (float) $place['location']['latitude'] : null,
            'longitude' => isset($place['location']['longitude']) ? (float) $place['location']['longitude'] : null,
        ];
    }

    private function normaliseGeocode(array $result, float $latitude, float $longitude): array
    {
        $components = $result['address_components'] ?? [];
        $byType = function (string $type) use ($components): ?string {
            foreach ($components as $component) {
                if (in_array($type, $component['types'] ?? [], true)) {
                    return $component['long_name'] ?? $component['short_name'] ?? null;
                }
            }

            return null;
        };

        $countryCode = null;
        foreach ($components as $component) {
            if (in_array('country', $component['types'] ?? [], true)) {
                $countryCode = $component['short_name'] ?? null;
                break;
            }
        }

        return [
            'place_name' => $byType('point_of_interest') ?? $byType('premise') ?? $byType('locality'),
            'formatted_address' => $result['formatted_address'] ?? null,
            'google_place_id' => $result['place_id'] ?? null,
            'plus_code' => $result['plus_code']['global_code'] ?? null,
            'country_code' => $countryCode ? strtoupper($countryCode) : null,
            'admin_area_1' => $byType('administrative_area_level_1'),
            'admin_area_2' => $byType('administrative_area_level_2'),
            'locality' => $byType('locality') ?? $byType('sublocality'),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function key(): string
    {
        return trim((string) config('services.google_maps.server_api_key'));
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new InvalidArgumentException('Google Maps Platform is not configured.');
        }
    }

    private function assertSuccessful(Response $response, string $operation): void
    {
        if (! $response->successful()) {
            throw new RuntimeException($operation.' is currently unavailable.');
        }
    }
}
