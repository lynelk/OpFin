<?php

declare(strict_types=1);

namespace App\Services\AssetRegistry;

use App\Models\AssetPassport;
use App\Models\FinancialSpace;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Universal Asset Registry (CAP-002): one passport lifecycle for devices, vehicles and productive
 * assets, with class-specific identity evidence. It records identity, verification, liens and theft
 * reports. It never controls a device (DEV-005 stays disabled) and never moves money.
 */
final class AssetRegistryService
{
    private const SPACE_MANAGERS = ['owner', 'administrator', 'admin', 'chairperson', 'treasurer', 'secretary', 'director', 'manager'];

    private const CLOSED_ARRANGEMENT_STATUSES = ['settled', 'closed', 'cancelled', 'declined', 'written_off'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function list(FinancialSpace $space, User $actor): array
    {
        $this->secret();
        $this->assertMember($space, $actor);

        return AssetPassport::query()->where('financial_space_id', $space->id)->orderByDesc('id')->limit(200)->get()
            ->map(fn (AssetPassport $asset): array => $this->passport($asset, false))->all();
    }

    public function show(FinancialSpace $space, User $actor, AssetPassport $asset): array
    {
        $this->secret();
        $this->assertMember($space, $actor);
        $this->assertInSpace($space, $asset);

        return $this->passport($asset, true);
    }

    public function register(FinancialSpace $space, User $actor, array $data, string $key): array
    {
        $secret = $this->secret();
        $this->assertManager($space, $actor);
        $class = config('asset_registry.classes.'.$data['asset_class']);
        abort_unless(is_array($class), 422, 'This asset class is not supported.');
        $identifiers = [];
        foreach ($data['identifiers'] as $item) {
            try {
                $value = AssetIdentifier::normalise((string) $item['type'], (string) $item['value']);
            } catch (InvalidArgumentException $error) {
                abort(422, $error->getMessage());
            }
            $hmac = AssetIdentifier::hmac((string) $item['type'], $value, $secret);
            abort_if(isset($identifiers[$hmac]), 422, 'Give each identifier once.');
            $identifiers[$hmac] = ['type' => (string) $item['type'], 'hmac' => $hmac, 'masked' => AssetIdentifier::mask($value)];
        }
        abort_if(array_intersect(array_column($identifiers, 'type'), $class['required_any']) === [], 422,
            'This asset needs one of these identifiers: '.implode(', ', str_replace('_', ' ', $class['required_any'])).'.');
        $attributes = [
            'asset_class' => $data['asset_class'], 'asset_subclass' => $data['asset_subclass'] ?? null,
            'make' => $data['make'] ?? null, 'model' => $data['model'] ?? null, 'sku' => $data['sku'] ?? null,
            'supplier_profile_id' => $data['supplier_profile_id'] ?? null, 'purchase' => $data['purchase'] ?? null,
            'ownership_evidence' => isset($data['ownership_evidence_reference']) ? ['reference' => $data['ownership_evidence_reference']] : null,
        ];
        $hmacs = array_keys($identifiers);
        sort($hmacs);
        $instruction = hash('sha256', json_encode([$attributes, $hmacs], JSON_THROW_ON_ERROR));

        // A concurrent registration can claim an identifier between the conflict check and the insert;
        // the partial unique index rejects it, and the retry then records the conflict for review.
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): array => $this->insert($space, $actor, $attributes, $identifiers, $key, $instruction));
            } catch (UniqueConstraintViolationException $error) {
                if ($attempt >= 2) {
                    throw $error;
                }
            }
        }
    }

    public function reportStolen(FinancialSpace $space, User $actor, AssetPassport $asset, array $data, string $key): array
    {
        $this->secret();
        $this->assertInSpace($space, $asset);
        abort_unless($this->isManager($space, $actor) || (int) $asset->owner_user_id === (int) $actor->id, 403);

        return $this->transition($asset, $actor, 'reported_stolen', ['registered', 'review_required', 'verified', 'encumbered'], $key, $data['reason'],
            array_filter(['police_reference' => $data['police_reference'] ?? null, 'reported_on' => $data['reported_on'] ?? null]),
            fn (): string => 'reported_stolen');
    }

    public function dispose(FinancialSpace $space, User $actor, AssetPassport $asset, array $data, string $key): array
    {
        $this->secret();
        $this->assertInSpace($space, $asset);
        $this->assertManager($space, $actor);

        return $this->transition($asset, $actor, 'disposed', ['registered', 'review_required', 'verified', 'reported_stolen'], $key, $data['reason'],
            ['disposal' => $data['disposal']], function (AssetPassport $locked): string {
                abort_if($this->activeLien($locked->id) !== null, 409, 'Release the lien before disposing of this asset.');
                DB::table('asset_identifiers')->where('asset_passport_id', $locked->id)->update(['active' => false, 'updated_at' => now()]);

                return 'disposed';
            });
    }

    public function verify(User $actor, AssetPassport $asset, array $data, string $key): array
    {
        $this->secret();

        return $this->transition($asset, $actor, 'verified', ['registered'], $key, null,
            ['method' => $data['method'], 'evidence_reference' => $data['evidence_reference']], function (AssetPassport $locked) use ($actor): string {
                abort_if((int) $locked->registered_by === (int) $actor->id, 403, 'A different person must verify an asset they registered.');
                $locked->forceFill(['verified_by' => $actor->id, 'verified_at' => now()]);

                return 'verified';
            });
    }

    public function resolveReview(User $actor, AssetPassport $asset, array $data, string $key): array
    {
        $this->secret();
        $activate = $data['decision'] === 'activate';

        return $this->transition($asset, $actor, $activate ? 'review_cleared' : 'review_rejected', ['review_required'], $key, $data['reason'], [],
            function (AssetPassport $locked) use ($activate): string {
                if (! $activate) {
                    return 'disposed';
                }
                $own = DB::table('asset_identifiers')->where('asset_passport_id', $locked->id)->get(['identifier_type', 'value_hmac']);
                $elsewhere = DB::table('asset_identifiers')->where('asset_passport_id', '!=', $locked->id)->where('active', true)
                    ->where(function ($query) use ($own): void {
                        foreach ($own as $identifier) {
                            $query->orWhere(fn ($match) => $match->where('identifier_type', $identifier->identifier_type)->where('value_hmac', $identifier->value_hmac));
                        }
                    })->exists();
                abort_if($elsewhere, 409, 'An identifier is still active on another passport. Resolve that passport first.');
                DB::table('asset_identifiers')->where('asset_passport_id', $locked->id)->update(['active' => true, 'updated_at' => now()]);
                $locked->forceFill(['review_reason' => null]);

                return 'registered';
            });
    }

    public function encumber(User $actor, AssetPassport $asset, array $data, string $key): array
    {
        $this->secret();
        $arrangementId = (int) $data['financing_arrangement_id'];

        return $this->transition($asset, $actor, 'encumbered', ['verified'], $key, null, ['financing_arrangement_id' => $arrangementId],
            function (AssetPassport $locked) use ($actor, $arrangementId): string {
                $arrangement = DB::table('financing_arrangements')->where('id', $arrangementId)->lockForUpdate()->first();
                abort_unless($arrangement !== null && (int) $arrangement->financial_space_id === (int) $locked->financial_space_id, 422,
                    'The financing arrangement must belong to the same Financial Space as the asset.');
                abort_if($arrangement->settled_at !== null || in_array($arrangement->status, self::CLOSED_ARRANGEMENT_STATUSES, true), 422,
                    'This financing arrangement is no longer open.');
                abort_if($this->activeLien($locked->id) !== null, 409, 'This asset already secures another arrangement.');
                DB::table('asset_encumbrances')->insert(['reference' => (string) Str::uuid(), 'asset_passport_id' => $locked->id,
                    'financing_arrangement_id' => $arrangementId, 'status' => 'active', 'registered_by' => $actor->id,
                    'registered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

                return 'encumbered';
            });
    }

    public function release(User $actor, int $encumbranceId, array $data, string $key): array
    {
        $this->secret();
        $lien = DB::table('asset_encumbrances')->where('id', $encumbranceId)->first();
        abort_unless($lien !== null, 404);
        $asset = AssetPassport::query()->findOrFail($lien->asset_passport_id);

        return $this->transition($asset, $actor, 'lien_released', ['encumbered', 'reported_stolen'], $key, $data['reason'], ['lien_reference' => $lien->reference],
            function (AssetPassport $locked) use ($actor, $encumbranceId, $data): string {
                $current = DB::table('asset_encumbrances')->where('id', $encumbranceId)->lockForUpdate()->first();
                abort_unless($current->status === 'active', 409, 'This lien has already been released.');
                $settled = DB::table('financing_arrangements')->where('id', $current->financing_arrangement_id)->value('settled_at') !== null;
                abort_unless($settled || $actor->role === User::ROLE_PLATFORM_ADMIN, 403,
                    'Only a platform administrator can release a lien before the arrangement is settled.');
                DB::table('asset_encumbrances')->where('id', $encumbranceId)->update(['status' => 'released', 'released_by' => $actor->id,
                    'released_at' => now(), 'release_reason' => $data['reason'], 'updated_at' => now()]);

                return $locked->status === 'reported_stolen' ? 'reported_stolen' : 'verified';
            });
    }

    public function recover(User $actor, AssetPassport $asset, array $data, string $key): array
    {
        $this->secret();

        return $this->transition($asset, $actor, 'recovered', ['reported_stolen'], $key, $data['reason'], [],
            fn (AssetPassport $locked): string => match (true) {
                $this->activeLien($locked->id) !== null => 'encumbered',
                $locked->review_reason !== null => 'review_required',
                $locked->verified_at !== null => 'verified',
                default => 'registered',
            });
    }

    /** Registry state for a lender's duplicate-finance and stolen-asset checks (DEV-002). Never reveals owner or Space. */
    public function identifierCheck(User $actor, string $type, string $value): array
    {
        $secret = $this->secret();
        try {
            $normalised = AssetIdentifier::normalise($type, $value);
        } catch (InvalidArgumentException $error) {
            abort(422, $error->getMessage());
        }
        $passports = DB::table('asset_identifiers')->join('asset_passports', 'asset_passports.id', '=', 'asset_identifiers.asset_passport_id')
            ->where('asset_identifiers.identifier_type', $type)->where('asset_identifiers.value_hmac', AssetIdentifier::hmac($type, $normalised, $secret))
            ->get(['asset_passports.id', 'asset_passports.status', 'asset_identifiers.active']);
        $result = [
            'identifier' => AssetIdentifier::mask($normalised),
            'registered' => $passports->isNotEmpty(),
            'active_passport' => $passports->contains(fn (object $row): bool => (bool) $row->active),
            'reported_stolen' => $passports->contains('status', 'reported_stolen'),
            'active_lien' => $passports->isNotEmpty()
                && DB::table('asset_encumbrances')->whereIn('asset_passport_id', $passports->pluck('id'))->where('status', 'active')->exists(),
            'note' => 'Registry state only. It does not identify the owner or Financial Space and is not proof of ownership.',
        ];
        $this->audit->record('asset.identifier_checked', $actor, null, ['identifier_type' => $type, 'registered' => $result['registered']]);

        return $result;
    }

    public function reviewQueue(): array
    {
        $this->secret();

        return AssetPassport::query()->where('status', 'review_required')->orderBy('id')->limit(100)->get()
            ->map(fn (AssetPassport $asset): array => $this->passport($asset, true)
                + ['review_reason' => $asset->review_reason, 'financial_space_id' => $asset->financial_space_id])->all();
    }

    private function insert(FinancialSpace $space, User $actor, array $attributes, array $identifiers, string $key, string $instruction): array
    {
        FinancialSpace::query()->whereKey($space->id)->lockForUpdate()->firstOrFail();
        $existing = AssetPassport::query()->where('financial_space_id', $space->id)->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            abort_unless(hash_equals((string) $existing->instruction_hash, $instruction), 409, 'This Idempotency-Key was used for a different asset.');

            return $this->passport($existing, true);
        }
        $conflict = DB::table('asset_identifiers')->join('asset_passports', 'asset_passports.id', '=', 'asset_identifiers.asset_passport_id')
            ->where(function ($query) use ($identifiers): void {
                foreach ($identifiers as $identifier) {
                    $query->orWhere(fn ($match) => $match->where('asset_identifiers.identifier_type', $identifier['type'])
                        ->where('asset_identifiers.value_hmac', $identifier['hmac']));
                }
            })
            ->where(fn ($query) => $query->where('asset_identifiers.active', true)->orWhere('asset_passports.status', 'reported_stolen'))
            ->orderByRaw("CASE WHEN asset_passports.status = 'reported_stolen' THEN 0 ELSE 1 END")
            ->value('asset_passports.status');
        $reviewReason = match (true) {
            $conflict === null => null,
            $conflict === 'reported_stolen' => 'identifier_reported_stolen',
            default => 'identifier_active_on_another_passport',
        };
        $status = $reviewReason === null ? 'registered' : 'review_required';
        $asset = AssetPassport::query()->create([...$attributes, 'reference' => (string) Str::uuid(), 'financial_space_id' => $space->id,
            'owner_user_id' => $space->type === 'personal' ? $actor->id : null, 'registered_by' => $actor->id,
            'idempotency_key' => $key, 'instruction_hash' => $instruction, 'status' => $status, 'review_reason' => $reviewReason,
            'external_identifier_hash' => array_key_first($identifiers)]);
        foreach ($identifiers as $identifier) {
            DB::table('asset_identifiers')->insert(['asset_passport_id' => $asset->id, 'identifier_type' => $identifier['type'],
                'value_hmac' => $identifier['hmac'], 'masked' => $identifier['masked'], 'active' => $reviewReason === null,
                'created_at' => now(), 'updated_at' => now()]);
        }
        $this->event($asset, 'registered', null, $status, $actor, $key, null, ['identifier_types' => array_values(array_column($identifiers, 'type'))]);
        $this->audit->record('asset.registered', $actor, $asset, ['status' => $status]);

        return $this->passport($asset->fresh(), true);
    }

    /** Runs one lifecycle step under a row lock. Replaying the same Idempotency-Key returns the current passport. */
    private function transition(AssetPassport $asset, User $actor, string $event, array $from, string $key, ?string $reason, array $evidence, callable $apply): array
    {
        return DB::transaction(function () use ($asset, $actor, $event, $from, $key, $reason, $evidence, $apply): array {
            $locked = AssetPassport::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $replay = DB::table('asset_passport_events')->where('asset_passport_id', $locked->id)->where('idempotency_key', $key)->first();
            if ($replay !== null) {
                abort_unless($replay->event_type === $event, 409, 'This Idempotency-Key was used for a different asset action.');

                return $this->passport($locked, true);
            }
            abort_unless(in_array($locked->status, $from, true), 409,
                'This action is not available while the asset is '.str_replace('_', ' ', $locked->status).'.');
            $previous = $locked->status;
            $to = $apply($locked);
            $locked->forceFill(['status' => $to])->save();
            $this->event($locked, $event, $previous, $to, $actor, $key, $reason, $evidence);
            $this->audit->record('asset.'.$event, $actor, $locked, ['from' => $previous, 'to' => $to]);

            return $this->passport($locked->fresh(), true);
        });
    }

    private function event(AssetPassport $asset, string $type, ?string $from, string $to, User $actor, string $key, ?string $reason, array $evidence): void
    {
        DB::table('asset_passport_events')->insert(['asset_passport_id' => $asset->id, 'event_type' => $type, 'from_status' => $from,
            'to_status' => $to, 'actor_user_id' => $actor->id, 'idempotency_key' => $key, 'reason' => $reason,
            'evidence' => $evidence === [] ? null : json_encode($evidence, JSON_THROW_ON_ERROR), 'occurred_at' => now(), 'created_at' => now()]);
    }

    private function passport(AssetPassport $asset, bool $history): array
    {
        $lien = $this->activeLien($asset->id);
        $passport = [
            'id' => $asset->id, 'reference' => $asset->reference, 'asset_class' => $asset->asset_class,
            'family' => config("asset_registry.classes.{$asset->asset_class}.family"), 'asset_subclass' => $asset->asset_subclass,
            'make' => $asset->make, 'model' => $asset->model, 'sku' => $asset->sku,
            'status' => $asset->status, 'status_explanation' => $this->explanation($asset->status),
            'identifiers' => DB::table('asset_identifiers')->where('asset_passport_id', $asset->id)->orderBy('id')->get(['identifier_type', 'masked', 'active'])
                ->map(fn (object $row): array => ['type' => $row->identifier_type, 'masked' => $row->masked, 'active' => (bool) $row->active])->all(),
            'verified_at' => $asset->verified_at?->toIso8601String(),
            'secured_by_financing' => $lien !== null, 'lien_reference' => $lien?->reference,
            'remote_device_controls' => 'not_available',
        ];
        if ($history) {
            $passport['history'] = DB::table('asset_passport_events')->where('asset_passport_id', $asset->id)->orderBy('id')
                ->get(['event_type', 'from_status', 'to_status', 'occurred_at'])->map(fn (object $row): array => (array) $row)->all();
        }

        return $passport;
    }

    private function explanation(string $status): string
    {
        return match ($status) {
            'registered' => 'Registered. An authorised verifier has not yet confirmed this asset.',
            'review_required' => 'This asset needs a review before it can be used for financing. We will contact you if more evidence is needed.',
            'verified' => 'Identity verified. This confirms the identifiers, not ownership or value.',
            'encumbered' => 'This asset secures a financing arrangement until that arrangement is settled.',
            'reported_stolen' => 'Reported stolen. It cannot be used for new financing.',
            'disposed' => 'No longer held in this Space (sold, traded in, scrapped, transferred or rejected at review).',
            default => 'Status recorded.',
        };
    }

    private function activeLien(int $assetId): ?object
    {
        return DB::table('asset_encumbrances')->where('asset_passport_id', $assetId)->where('status', 'active')->first();
    }

    private function secret(): string
    {
        $secret = (string) config('asset_registry.identifier_key');
        abort_if(strlen($secret) < 32, 503, 'The asset registry is not configured.');

        return $secret;
    }

    private function role(FinancialSpace $space, User $actor): ?string
    {
        abort_if($space->trashed() || $space->status !== 'active', 403);

        return DB::table('financial_space_memberships')->where('financial_space_id', $space->id)->where('user_id', $actor->id)
            ->where('status', 'active')->whereNull('deleted_at')->value('role');
    }

    private function assertMember(FinancialSpace $space, User $actor): void
    {
        abort_if($this->role($space, $actor) === null, 403);
    }

    private function isManager(FinancialSpace $space, User $actor): bool
    {
        return in_array($this->role($space, $actor), self::SPACE_MANAGERS, true);
    }

    private function assertManager(FinancialSpace $space, User $actor): void
    {
        abort_unless($this->isManager($space, $actor), 403);
    }

    private function assertInSpace(FinancialSpace $space, AssetPassport $asset): void
    {
        abort_unless((int) $asset->financial_space_id === (int) $space->id, 404);
    }
}
