<?php

namespace App\Console\Commands;

use App\Models\ConsentRecord;
use App\Models\IdentityVerificationReceipt;
use App\Models\KycCase;
use App\Models\User;
use App\Services\CitoCapabilityClient;
use App\Services\VerifiedIdentityEvidenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MaintainIdentityEvidence extends Command
{
    protected $signature = 'identity:evidence-maintain {--refresh : Revalidate due evidence under the approved policy} {--limit=50 : Maximum receipts inspected per run}';

    protected $description = 'Purge expired optional identity receipts and refresh due consented NIN evidence without extending stale results.';

    public function handle(VerifiedIdentityEvidenceService $evidence, CitoCapabilityClient $cito): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $purged = $evidence->purgeExpiredRetention($limit);
        $refreshed = 0;
        $failed = 0;
        $retired = 0;
        $maxAge = (int) config('identity_evidence.max_age_seconds', 0);
        $interval = (int) config('identity_evidence.refresh_after_seconds', 0);
        $retention = (int) config('identity_evidence.retention_seconds', 0);
        $enabled = $this->option('refresh') && config('identity_evidence.enabled', false)
            && trim((string) config('identity_evidence.approval_reference', '')) !== ''
            && trim((string) config('identity_evidence.policy_version', '')) !== ''
            && $maxAge > 0 && $interval > 0 && $interval < $maxAge && $retention >= $maxAge;
        if ($enabled && $cito->configured() && Schema::hasTable('identity_verification_receipts')) {
            $receipts = IdentityVerificationReceipt::query()
                ->whereNull('revoked_at')->where('refresh_after', '<=', now())
                ->where('retention_until', '>', now())
                ->where('policy_version', config('identity_evidence.policy_version'))
                ->where('environment', strtoupper((string) config('services.cito.environment', 'SANDBOX')))
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('identity_verification_receipts as newer')
                        ->whereColumn('newer.lookup_hash', 'identity_verification_receipts.lookup_hash')
                        ->whereColumn('newer.id', '>', 'identity_verification_receipts.id');
                })->orderBy('refresh_after')->limit($limit)->get();
            $seen = [];
            foreach ($receipts as $receipt) {
                $consent = ConsentRecord::query()->whereKey($receipt->consent_record_id)
                    ->where('user_id', $receipt->user_id)->where('purpose', 'identity_evidence_reuse')
                    ->where('status', 'granted')->whereNull('revoked_at')->first();
                $user = User::query()->find($receipt->user_id);
                $case = KycCase::query()->where('user_id', $receipt->user_id)->find($receipt->kyc_case_id);
                $latestCase = KycCase::query()->where('user_id', $receipt->user_id)->latest('id')->first();
                $obsolete = ! $consent || ! $user || ! $case || $case->status === KycCase::STATUS_REJECTED;
                if (! $obsolete) {
                    $obsolete = ! $evidence->matchesReceiptContext($receipt, $user, $case)
                        || ($latestCase && $this->normalise((string) $latestCase->national_id) !== $this->normalise((string) $case->national_id));
                }
                if ($obsolete) {
                    $receipt->update(['revoked_at' => now()]);
                    $retired++;
                    continue;
                }
                if (isset($seen[$receipt->user_id])) {
                    continue;
                }
                $seen[$receipt->user_id] = true;
                // Backoff applies after both outcomes; timeouts never renew age.
                if (! Cache::add('opfin:identity:refresh:'.$receipt->public_id, true, $interval)) {
                    continue;
                }
                try {
                    $cito->identityCheck($user, $case, 'NIN', forceRefresh: true, systemInitiated: true);
                    $refreshed++;
                } catch (Throwable) {
                    $failed++;
                }
            }
        }
        $this->info("Identity evidence maintenance: purged={$purged}, checked={$refreshed}, deferred={$failed}, retired={$retired}.");

        return self::SUCCESS;
    }

    private function normalise(string $nin): string
    {
        return strtoupper(preg_replace('/\s+/', '', $nin) ?? '');
    }
}
