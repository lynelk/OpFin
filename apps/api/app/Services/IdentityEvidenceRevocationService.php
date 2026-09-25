<?php

namespace App\Services;

use App\Models\KycCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IdentityEvidenceRevocationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function recordDefinitiveResult(User $user, KycCase $case, array $result, bool $systemInitiated = false): void
    {
        if ((int) $case->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Identity evidence must belong to the verified subject.');
        }
        DB::transaction(function () use ($user, $case, $result, $systemInitiated): void {
            $evidence = app(VerifiedIdentityEvidenceService::class);
            $failed = strtoupper(trim((string) ($result['status'] ?? ''))) === 'FAIL';
            if ($failed) {
                // Missing provider attribution is not a reason to keep using
                // an old PASS after the provider has rejected the identity.
                // Revoke old receipts, but do not invent a failure reference.
                $evidence->invalidateForUser($user, $systemInitiated);
            }
            $evidence->rememberNin($user, $case, $result, $systemInitiated);
            if (! $failed) {
                return;
            }
            $nin = $this->normalise((string) $case->national_id);
            $cases = KycCase::query()->where('user_id', $user->id)
                ->whereIn('status', [KycCase::STATUS_VERIFIED, KycCase::STATUS_PENDING_REVIEW])
                ->lockForUpdate()->get();
            foreach ($cases as $existing) {
                if ($nin === '' || ! hash_equals($nin, $this->normalise((string) $existing->national_id))) {
                    continue;
                }
                $flags = array_merge((array) $existing->risk_flags, [
                    'nin_revalidation' => [
                        'status' => 'failed', 'provider' => 'cito',
                        'observed_at' => now()->toIso8601String(),
                    ],
                ]);
                $existing->update([
                    'status' => KycCase::STATUS_PENDING_REVIEW,
                    'expires_at' => now(),
                    'risk_flags' => $flags,
                ]);
                $this->audit->record('kyc.nin_revalidation_failed', $systemInitiated ? null : $user, $existing, [
                    'provider' => 'cito', 'review_required' => true,
                    'trigger' => $systemInitiated ? 'scheduled_revalidation' : 'interactive_verification',
                ]);
            }
        });
    }

    private function normalise(string $nin): string
    {
        return strtoupper(preg_replace('/\s+/', '', $nin) ?? '');
    }
}
