<?php

namespace App\Services;

use App\Models\KycCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IdentityEvidenceRevocationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function recordDefinitiveResult(User $user, KycCase $case, array $result): void
    {
        if ((int) $case->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Identity evidence must belong to the verified subject.');
        }
        DB::transaction(function () use ($user, $case, $result): void {
            app(VerifiedIdentityEvidenceService::class)->rememberNin($user, $case, $result);
            if (strtoupper((string) ($result['status'] ?? '')) !== 'FAIL') {
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
                $this->audit->record('kyc.nin_revalidation_failed', $user, $existing, [
                    'provider' => 'cito', 'review_required' => true,
                ]);
            }
        });
    }

    private function normalise(string $nin): string
    {
        return strtoupper(preg_replace('/\s+/', '', $nin) ?? '');
    }
}
