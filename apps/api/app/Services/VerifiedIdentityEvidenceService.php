<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\IdentityVerificationReceipt;
use App\Models\KycCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VerifiedIdentityEvidenceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function reusableNin(User $user, KycCase $case): ?array
    {
        $context = $this->context($user, $case);
        if ($context === null) {
            return null;
        }
        // A newer rejection or invalidation supersedes an old PASS. Never
        // search backwards for a successful observation after a failed check.
        $receipt = IdentityVerificationReceipt::query()
            ->where('user_id', $user->id)
            ->where('consent_record_id', $context['consent']->id)
            ->where('lookup_hash', $context['lookup_hash'])
            ->orderByDesc('id')->first();
        if (! $receipt || $receipt->status !== 'PASS' || $receipt->revoked_at !== null
            || $receipt->verified_at->isFuture() || ! $receipt->expires_at->isFuture()
            || ! $receipt->retention_until->isFuture()) {
            return null;
        }
        $this->audit->record('identity.evidence.reused', $user, $case, [
            'receipt_reference' => $receipt->public_id,
            'provider' => $receipt->provider,
            'verified_at' => $receipt->verified_at->toIso8601String(),
            'policy_version' => $receipt->policy_version,
        ]);

        return array_merge($receipt->result, [
            'providerReference' => $receipt->provider_reference,
            'evidenceSource' => 'internal_verified_evidence',
            'evidenceReference' => $receipt->public_id,
            'verifiedAt' => $receipt->verified_at->toIso8601String(),
            'expiresAt' => $receipt->expires_at->toIso8601String(),
            'revalidationDue' => ! $receipt->refresh_after->isFuture(),
        ]);
    }

    public function rememberNin(User $user, KycCase $case, array $result, bool $systemInitiated = false): void
    {
        $context = $this->context($user, $case);
        $status = strtoupper(trim((string) ($result['status'] ?? '')));
        $providerReference = trim((string) ($result['providerReference'] ?? ''));
        if ($context === null || ! in_array($status, ['PASS', 'FAIL'], true)
            || $providerReference === '' || strlen($providerReference) > 255) {
            return;
        }
        $receiptKey = hash_hmac('sha256', $context['lookup_hash'].'|'.$providerReference.'|'.$status, $context['key']);
        $verifiedAt = now()->toImmutable();
        $reference = is_string($result['reference'] ?? null) ? substr($result['reference'], 0, 255) : null;
        $safeResult = ['status' => $status, 'reference' => $reference, 'reasonCodes' => []];
        // A replay of the provider reference cannot renew its observation age.
        $receipt = IdentityVerificationReceipt::query()->firstOrCreate(
            ['receipt_key' => $receiptKey],
            [
                'public_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'kyc_case_id' => $case->id,
                'consent_record_id' => $context['consent']->id,
                'lookup_hash' => $context['lookup_hash'],
                'policy_version' => $context['policy_version'],
                'provider' => 'cito',
                'environment' => $context['environment'],
                'provider_reference' => $providerReference,
                'status' => $status,
                'result' => $safeResult,
                'verified_at' => $verifiedAt,
                'refresh_after' => $verifiedAt->addSeconds($context['refresh_after_seconds']),
                'expires_at' => $verifiedAt->addSeconds($context['max_age_seconds']),
                'retention_until' => $verifiedAt->addSeconds($context['retention_seconds']),
            ],
        );
        if ($receipt->wasRecentlyCreated) {
            $this->audit->record('identity.evidence.recorded', $systemInitiated ? null : $user, $case, [
                'receipt_reference' => $receipt->public_id,
                'provider' => 'cito',
                'status' => $status,
                'policy_version' => $context['policy_version'],
                'trigger' => $systemInitiated ? 'scheduled_revalidation' : 'interactive_verification',
            ]);
        }
    }

    public function matchesReceiptContext(IdentityVerificationReceipt $receipt, User $user, KycCase $case): bool
    {
        if ((int) $receipt->user_id !== (int) $user->id || (int) $receipt->kyc_case_id !== (int) $case->id) {
            return false;
        }
        $context = $this->context($user, $case);

        return $context !== null && hash_equals($receipt->lookup_hash, $context['lookup_hash']);
    }

    public function invalidateForUser(User $user, bool $systemInitiated = false): int
    {
        if (! Schema::hasTable('identity_verification_receipts')) {
            return 0;
        }
        $count = IdentityVerificationReceipt::query()->where('user_id', $user->id)
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        $this->audit->record('identity.evidence.invalidated', $systemInitiated ? null : $user, $user, [
            'receipt_count' => $count,
            'trigger' => $systemInitiated ? 'scheduled_revalidation' : 'interactive_verification',
        ]);

        return $count;
    }

    public function purgeExpiredRetention(int $limit = 500): int
    {
        if (! Schema::hasTable('identity_verification_receipts')) {
            return 0;
        }
        $ids = DB::table('identity_verification_receipts')->where('retention_until', '<=', now())
            ->orderBy('id')->limit(max(1, min($limit, 5000)))->pluck('id');

        return DB::table('identity_verification_receipts')->whereIn('id', $ids)->delete();
    }

    private function context(User $user, KycCase $case): ?array
    {
        if ((int) $case->user_id !== (int) $user->id || ! $user->exists || $user->deleted_at !== null) {
            throw new InvalidArgumentException('Identity evidence must belong to the authenticated subject.');
        }
        if (! config('identity_evidence.enabled', false) || ! Schema::hasTable('identity_verification_receipts')) {
            return null;
        }
        $version = trim((string) config('identity_evidence.policy_version', ''));
        $approval = trim((string) config('identity_evidence.approval_reference', ''));
        $maxAge = (int) config('identity_evidence.max_age_seconds', 0);
        $refresh = (int) config('identity_evidence.refresh_after_seconds', 0);
        $retention = (int) config('identity_evidence.retention_seconds', 0);
        if ($version === '' || strlen($version) > 64 || $approval === '' || $maxAge <= 0
            || $refresh <= 0 || $refresh >= $maxAge || $retention < $maxAge) {
            return null;
        }
        $consent = ConsentRecord::query()->where('user_id', $user->id)
            ->where('purpose', 'identity_evidence_reuse')->where('status', ConsentRecord::STATUS_GRANTED)
            ->whereNull('revoked_at')->latest('id')->first();
        if (! $consent || $case->status === KycCase::STATUS_REJECTED) {
            return null;
        }
        $nin = strtoupper(preg_replace('/\s+/', '', (string) $case->national_id) ?? '');
        if (! preg_match('/^[A-Z0-9]{14}$/D', $nin)) {
            return null;
        }
        $secret = (string) config('app.key', '');
        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7), true) ?: '';
        }
        if (strlen($secret) < 16) {
            return null;
        }
        $environment = strtoupper((string) config('services.cito.environment', 'SANDBOX'));
        if (app()->environment('production') && $environment !== 'LIVE') {
            return null;
        }
        $key = hash_hkdf('sha256', $secret, 32, 'opfin-identity-evidence-v1');
        $name = trim(implode(' ', array_filter([$user->first_name, $user->other_name, $user->last_name]))) ?: (string) $user->name;
        $scope = [
            'subject' => (int) $user->id,
            'nin' => $nin,
            'name' => mb_strtoupper(preg_replace('/\s+/', ' ', trim($name)) ?? ''),
            'date_of_birth' => (string) $user->getRawOriginal('date_of_birth'),
            'country' => strtoupper((string) config('opfin.default_country', 'UG')),
            'purpose' => 'identity_verification',
            'provider' => 'cito',
            'endpoint' => rtrim((string) config('services.cito.base_url'), '/'),
            'merchant' => (string) config('services.cito.merchant_number'),
            'environment' => $environment,
            'app_environment' => app()->environment(),
            'policy_version' => $version,
            'approval_reference' => $approval,
            'consent_id' => (int) $consent->id,
        ];

        return [
            'lookup_hash' => hash_hmac('sha256', json_encode($scope, JSON_THROW_ON_ERROR), $key),
            'key' => $key,
            'consent' => $consent,
            'policy_version' => $version,
            'environment' => $environment,
            'max_age_seconds' => $maxAge,
            'refresh_after_seconds' => $refresh,
            'retention_seconds' => $retention,
        ];
    }
}
