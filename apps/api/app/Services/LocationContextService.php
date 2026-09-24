<?php

namespace App\Services;

use App\Models\LocationContext;
use App\Models\ProtectionPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocationContextService
{
    public const SUBJECT_TYPES = [
        'user',
        'financial_space',
        'financial_asset',
        'protection_policy',
        'protection_claim',
        'partner_service_point',
    ];

    public const PURPOSES = [
        'personal_service_discovery',
        'group_operating_area',
        'group_meeting_place',
        'insured_risk_location',
        'investment_asset_location',
        'claim_incident_location',
        'partner_service_point',
        'partner_aggregate_insights',
    ];

    public const SOURCES = [
        'device',
        'manual',
        'google_place',
        'partner',
        'field_verified',
    ];

    public const PRECISION_LEVELS = [
        'country',
        'region',
        'district',
        'locality',
        'approximate',
        'precise',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly GoogleMapsLocationService $google,
    ) {}

    public function listFor(User $actor, string $subjectType, int $subjectId): array
    {
        $this->authorise($actor, $subjectType, $subjectId, false);

        return LocationContext::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderBy('purpose')
            ->get()
            ->map(fn (LocationContext $context) => $this->present($context))
            ->all();
    }

    public function save(User $actor, array $attributes): LocationContext
    {
        $subjectType = (string) $attributes['subject_type'];
        $subjectId = (int) $attributes['subject_id'];
        $this->authorise($actor, $subjectType, $subjectId, true);
        $this->validatePurpose($subjectType, (string) $attributes['purpose']);

        if ((string) $attributes['consent_purpose'] !== (string) $attributes['purpose']) {
            throw ValidationException::withMessages([
                'consent_purpose' => ['Location consent must match the financial task that is using the location.'],
            ]);
        }

        [$userId, $spaceId] = $this->ownership($subjectType, $subjectId);
        $precision = (string) $attributes['precision_level'];
        if ($subjectType === 'user' && $attributes['purpose'] === 'personal_service_discovery') {
            $precision = 'approximate';
        }
        [$latitude, $longitude] = $this->privacyCoordinates(
            isset($attributes['latitude']) ? (float) $attributes['latitude'] : null,
            isset($attributes['longitude']) ? (float) $attributes['longitude'] : null,
            $precision,
        );

        $context = LocationContext::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('purpose', $attributes['purpose'])
            ->first();

        $payload = [
            'public_id' => $context?->public_id ?? (string) Str::uuid(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'financial_space_id' => $spaceId,
            'user_id' => $userId,
            'created_by_user_id' => $actor->id,
            'purpose' => $attributes['purpose'],
            'source' => $attributes['source'],
            'precision_level' => $precision,
            'place_name' => $attributes['place_name'] ?? null,
            'formatted_address' => $attributes['formatted_address'] ?? null,
            'google_place_id' => $attributes['google_place_id'] ?? null,
            'plus_code' => $attributes['plus_code'] ?? null,
            'country_code' => isset($attributes['country_code']) ? strtoupper((string) $attributes['country_code']) : null,
            'admin_area_1' => $attributes['admin_area_1'] ?? null,
            'admin_area_2' => $attributes['admin_area_2'] ?? null,
            'locality' => $attributes['locality'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_metres' => $attributes['accuracy_metres'] ?? null,
            'consent_purpose' => $attributes['consent_purpose'],
            'verification_status' => $this->verificationStatus($actor, (string) $attributes['source']),
            'captured_at' => $attributes['captured_at'] ?? now(),
            'verified_at' => $this->verificationStatus($actor, (string) $attributes['source']) === 'user_declared' ? null : now(),
            'metadata' => $attributes['metadata'] ?? null,
        ];

        if ($context) {
            $context->fill($payload)->save();
        } else {
            $context = LocationContext::query()->create($payload);
        }

        $this->auditLogger->record('location_context.saved', $actor, $context, [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'purpose' => $context->purpose,
            'source' => $context->source,
            'precision_level' => $context->precision_level,
            'consent_purpose' => $context->consent_purpose,
        ]);

        return $context->fresh();
    }

    public function delete(User $actor, LocationContext $context): void
    {
        $this->authorise($actor, $context->subject_type, (int) $context->subject_id, true);
        $this->auditLogger->record('location_context.deleted', $actor, $context, [
            'subject_type' => $context->subject_type,
            'subject_id' => $context->subject_id,
            'purpose' => $context->purpose,
        ]);
        $context->delete();
    }

    public function assertReadable(User $actor, LocationContext $context): void
    {
        $this->authorise($actor, $context->subject_type, (int) $context->subject_id, false);
    }

    public function present(LocationContext $context): array
    {
        $latitude = $context->latitude !== null ? (float) $context->latitude : null;
        $longitude = $context->longitude !== null ? (float) $context->longitude : null;

        return [
            'id' => $context->id,
            'public_id' => $context->public_id,
            'subject_type' => $context->subject_type,
            'subject_id' => (int) $context->subject_id,
            'purpose' => $context->purpose,
            'source' => $context->source,
            'precision_level' => $context->precision_level,
            'place_name' => $context->place_name,
            'formatted_address' => $context->formatted_address,
            'google_place_id' => $context->google_place_id,
            'plus_code' => $context->plus_code,
            'country_code' => $context->country_code,
            'admin_area_1' => $context->admin_area_1,
            'admin_area_2' => $context->admin_area_2,
            'locality' => $context->locality,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_metres' => $context->accuracy_metres,
            'consent_purpose' => $context->consent_purpose,
            'verification_status' => $context->verification_status,
            'credit_decision_eligible' => false,
            'captured_at' => $context->captured_at?->toIso8601String(),
            'maps_url' => $this->google->mapsUrl($context->google_place_id, $latitude, $longitude),
        ];
    }

    public function nearbyServicePoints(float $latitude, float $longitude, float $radiusKm = 25): array
    {
        $radiusKm = max(1, min(100, $radiusKm));

        return LocationContext::query()
            ->where('subject_type', 'partner_service_point')
            ->where('purpose', 'partner_service_point')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function (LocationContext $context) use ($latitude, $longitude) {
                $distance = $this->distanceKm(
                    $latitude,
                    $longitude,
                    (float) $context->latitude,
                    (float) $context->longitude,
                );

                $partner = DB::table('partners')->where('id', $context->subject_id)->whereNull('deleted_at')->first();

                return [
                    ...$this->present($context),
                    'partner_name' => $partner?->name,
                    'partner_type' => $partner?->partner_type,
                    'distance_km' => round($distance, 1),
                ];
            })
            ->filter(fn (array $item) => $item['distance_km'] <= $radiusKm)
            ->sortBy('distance_km')
            ->take(25)
            ->values()
            ->all();
    }

    public function partnerNetwork(User $actor): array
    {
        if ($actor->institution_id === null) {
            return [];
        }

        $partners = DB::table('partners')
            ->where('institution_id', $actor->institution_id)
            ->whereNull('deleted_at')
            ->pluck('name', 'id');

        if ($partners->isEmpty()) {
            return [];
        }

        return LocationContext::query()
            ->where('subject_type', 'partner_service_point')
            ->where('purpose', 'partner_service_point')
            ->whereIn('subject_id', $partners->keys()->map(fn ($id) => (int) $id)->all())
            ->orderBy('place_name')
            ->get()
            ->map(fn (LocationContext $context) => [
                ...$this->present($context),
                'partner_name' => $partners->get($context->subject_id),
            ])
            ->values()
            ->all();
    }

    public function aggregateInsights(int $minimumCohort = 5): array
    {
        $minimumCohort = max(5, $minimumCohort);

        $rows = LocationContext::query()
            ->whereIn('subject_type', ['financial_space', 'partner_service_point'])
            ->whereNotNull('country_code')
            ->get();

        $groups = $rows->groupBy(function (LocationContext $context) {
            return implode('|', [
                $context->country_code ?? '',
                $context->admin_area_1 ?? '',
                $context->admin_area_2 ?? '',
                $context->subject_type,
                $context->purpose,
            ]);
        });

        return $groups
            ->map(function ($items) {
                /** @var LocationContext $first */
                $first = $items->first();

                return [
                    'country_code' => $first->country_code,
                    'admin_area_1' => $first->admin_area_1,
                    'admin_area_2' => $first->admin_area_2,
                    'subject_type' => $first->subject_type,
                    'purpose' => $first->purpose,
                    'count' => $items->count(),
                ];
            })
            ->filter(fn (array $row) => $row['count'] >= $minimumCohort)
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function validatePurpose(string $subjectType, string $purpose): void
    {
        $allowed = match ($subjectType) {
            'user' => ['personal_service_discovery', 'partner_aggregate_insights'],
            'financial_space' => ['group_operating_area', 'group_meeting_place'],
            'financial_asset' => ['investment_asset_location'],
            'protection_policy' => ['insured_risk_location'],
            'protection_claim' => ['claim_incident_location'],
            'partner_service_point' => ['partner_service_point'],
            default => [],
        };

        if (! in_array($purpose, $allowed, true)) {
            throw ValidationException::withMessages([
                'purpose' => ['This location purpose is not valid for the selected financial context.'],
            ]);
        }
    }

    private function verificationStatus(User $actor, string $source): string
    {
        return match ($source) {
            'device' => 'device_confirmed',
            'google_place' => 'place_confirmed',
            'partner' => $actor->hasAnyRole([User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS])
                ? 'partner_confirmed'
                : 'user_declared',
            'field_verified' => $actor->hasAnyRole([User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS])
                ? 'verified'
                : 'user_declared',
            default => 'user_declared',
        };
    }

    private function ownership(string $subjectType, int $subjectId): array
    {
        return match ($subjectType) {
            'user' => [$subjectId, $this->personalSpaceId($subjectId)],
            'financial_space' => [null, $subjectId],
            'financial_asset' => [null, (int) DB::table('financial_assets')->where('id', $subjectId)->whereNull('deleted_at')->value('financial_space_id')],
            'protection_policy' => $this->protectionOwnership($subjectId),
            'protection_claim' => $this->claimOwnership($subjectId),
            'partner_service_point' => $this->partnerOwnership($subjectId),
            default => throw ValidationException::withMessages(['subject_type' => ['Unsupported location subject type.']]),
        };
    }

    private function authorise(User $actor, string $subjectType, int $subjectId, bool $write): void
    {
        if ($actor->hasAnyRole([User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS])) {
            return;
        }

        if ($subjectType === 'user') {
            abort_unless($actor->id === $subjectId, 403);
            return;
        }

        if ($subjectType === 'partner_service_point') {
            abort(403);
        }

        [$userId, $spaceId] = $this->ownership($subjectType, $subjectId);
        if ($userId !== null && $userId === $actor->id) {
            return;
        }

        abort_unless($spaceId !== null, 404);

        $membership = DB::table('financial_space_memberships')
            ->where('financial_space_id', $spaceId)
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        abort_unless($membership, 403);

        if ($write) {
            abort_unless(
                in_array($membership->role, ['owner', 'administrator', 'admin', 'chairperson', 'treasurer', 'secretary', 'director', 'manager'], true),
                403
            );
        }
    }

    private function partnerOwnership(int $partnerId): array
    {
        abort_unless(
            DB::table('partners')->where('id', $partnerId)->whereNull('deleted_at')->exists(),
            404
        );

        return [null, null];
    }

    private function protectionOwnership(int $policyId): array
    {
        $policy = ProtectionPolicy::query()->findOrFail($policyId);

        return [$policy->user_id ? (int) $policy->user_id : null, $policy->financial_space_id ? (int) $policy->financial_space_id : null];
    }

    private function claimOwnership(int $claimId): array
    {
        $row = DB::table('protection_claims as claims')
            ->join('protection_policies as policies', 'policies.id', '=', 'claims.protection_policy_id')
            ->where('claims.id', $claimId)
            ->select('policies.user_id', 'policies.financial_space_id')
            ->first();

        abort_unless($row, 404);

        return [$row->user_id ? (int) $row->user_id : null, $row->financial_space_id ? (int) $row->financial_space_id : null];
    }

    private function personalSpaceId(int $userId): ?int
    {
        $id = DB::table('financial_spaces as spaces')
            ->join('financial_space_memberships as memberships', 'memberships.financial_space_id', '=', 'spaces.id')
            ->where('memberships.user_id', $userId)
            ->where('memberships.status', 'active')
            ->whereNull('memberships.deleted_at')
            ->where('spaces.type', 'personal')
            ->whereNull('spaces.deleted_at')
            ->value('spaces.id');

        return $id === null ? null : (int) $id;
    }

    private function privacyCoordinates(?float $latitude, ?float $longitude, string $precision): array
    {
        if ($latitude === null || $longitude === null) {
            return [null, null];
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw ValidationException::withMessages(['location' => ['Coordinates are outside valid geographic bounds.']]);
        }

        return match ($precision) {
            'precise' => [round($latitude, 7), round($longitude, 7)],
            'approximate', 'locality' => [round($latitude, 3), round($longitude, 3)],
            'district' => [round($latitude, 2), round($longitude, 2)],
            'region' => [round($latitude, 1), round($longitude, 1)],
            'country' => [null, null],
            default => [round($latitude, 3), round($longitude, 3)],
        };
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0088;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
