<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinancialPolicyService
{
    public function active(
        string $policyType,
        ?string $productScope = null,
        ?string $licenceClass = null,
        ?string $country = null,
        ?Carbon $asOf = null,
    ): object {
        $country = strtoupper($country ?: (string) config('opfin.default_country', 'UG'));
        $licenceClass = $licenceClass ?? (string) config('opfin.regulatory.licence_class');
        $asOf ??= now();

        $query = DB::table('financial_policies')
            ->where('policy_type', $policyType)
            ->where('jurisdiction_country', $country)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOf->toDateString());
            });

        if ($licenceClass !== '') {
            $query->where(function ($q) use ($licenceClass) {
                $q->whereNull('licence_class')->orWhere('licence_class', $licenceClass);
            });
        }
        if ($productScope !== null && $productScope !== '') {
            $query->where(function ($q) use ($productScope) {
                $q->whereNull('product_scope')->orWhere('product_scope', $productScope);
            });
        }

        if ($productScope === null || $productScope === '') {
            $query->whereNull('product_scope');
        }
        if ($licenceClass === '') {
            $query->whereNull('licence_class');
        }

        $policy = $query
            ->orderByRaw('CASE WHEN licence_class IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN product_scope IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();

        if (! $policy) {
            throw new InvalidArgumentException(
                "No active {$policyType} policy is configured for country {$country}"
                .($licenceClass !== '' ? " and licence class {$licenceClass}" : '')
                .'. Regulated financial action is fail-closed.'
            );
        }

        $policy->rules = $this->decodeRules($policy->rules);

        return $policy;
    }

    public function rules(object $policy): array
    {
        return is_array($policy->rules ?? null)
            ? $policy->rules
            : $this->decodeRules($policy->rules ?? null);
    }

    public function requestOverride(
        string $controlCode,
        string $subjectType,
        int $subjectId,
        User $actor,
        string $reason,
        array $evidence = [],
        ?Carbon $expiresAt = null,
    ): object {
        $id = DB::table('financial_control_overrides')->insertGetId([
            'control_code' => $controlCode,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'requested_by' => $actor->id,
            'status' => 'pending',
            'reason' => trim($reason),
            'evidence' => $evidence === [] ? null : json_encode($evidence, JSON_THROW_ON_ERROR),
            'requested_at' => now(),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('financial_control_overrides')->find($id);
    }

    public function approveOverride(int $overrideId, User $checker, array $evidence = []): object
    {
        return DB::transaction(function () use ($overrideId, $checker, $evidence) {
            $override = DB::table('financial_control_overrides')->where('id', $overrideId)->lockForUpdate()->first();
            if (! $override || $override->status !== 'pending') {
                throw new InvalidArgumentException('Only a pending financial-control override can be approved.');
            }
            if ((int) $override->requested_by === (int) $checker->id) {
                throw new InvalidArgumentException('Maker-checker requires a different authorised user to approve this override.');
            }

            DB::table('financial_control_overrides')->where('id', $overrideId)->update([
                'status' => 'approved',
                'approved_by' => $checker->id,
                'approved_at' => now(),
                'evidence' => json_encode(array_merge(
                    (array) json_decode((string) ($override->evidence ?? '[]'), true),
                    $evidence,
                ), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return DB::table('financial_control_overrides')->find($overrideId);
        });
    }

    public function approvedOverride(string $controlCode, string $subjectType, int $subjectId): ?object
    {
        return DB::table('financial_control_overrides')
            ->where('control_code', $controlCode)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('approved_at')
            ->first();
    }

    private function decodeRules(mixed $rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }
        if (! is_string($rules) || trim($rules) === '') {
            return [];
        }

        $decoded = json_decode($rules, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
