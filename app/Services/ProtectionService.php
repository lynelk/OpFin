<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use App\Models\ProtectionClaim;
use App\Models\ProtectionPolicy;
use App\Models\ProtectionPremiumPayment;
use App\Models\ProtectionProduct;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProtectionService
{
    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly SaveProtectionLedgerService $ledger,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function activeProducts(string $countryCode): mixed
    {
        return ProtectionProduct::query()
            ->where('country_code', strtoupper($countryCode))
            ->where('status', ProtectionProduct::STATUS_ACTIVE)
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            ->where(function ($query) {
                $query->whereNull('effective_at')->orWhere('effective_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('name')
            ->get()
            ->map(fn (ProtectionProduct $product) => $this->presentProduct($product));
    }

    public function presentProduct(ProtectionProduct $product): array
    {
        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'insurer_name' => $product->insurer_name,
            'underwriter_name' => $product->underwriter_name,
            'country_code' => $product->country_code,
            'currency' => $product->currency,
            'product_type' => $product->product_type,
            'premium_amount_minor' => $product->premium_amount_minor,
            'premium_frequency' => $product->premium_frequency,
            'coverage_limit_minor' => $product->coverage_limit_minor,
            'benefits' => $product->benefits,
            'exclusions' => $product->exclusions,
            'disclosure_version' => $product->disclosure_version,
            'disclosure_payload' => $product->disclosure_payload,
            'terms_url' => $product->terms_url,
            'disclosure_hash' => $product->disclosureHash(),
        ];
    }

    public function policiesFor(User $user): mixed
    {
        return ProtectionPolicy::query()
            ->with(['product', 'premiumPayments', 'claims'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }

    public function enroll(
        User $user,
        ProtectionProduct $product,
        string $disclosureHash,
        array $acceptanceMetadata = [],
    ): ProtectionPolicy {
        $this->assertProductAvailable($product);
        if (! hash_equals($product->disclosureHash(), strtolower($disclosureHash))) {
            throw new InvalidArgumentException('Protection disclosure hash does not match the current product disclosure.');
        }

        $duplicate = ProtectionPolicy::query()
            ->where('user_id', $user->id)
            ->where('protection_product_id', $product->id)
            ->whereIn('status', [
                ProtectionPolicy::STATUS_PREMIUM_DUE,
                ProtectionPolicy::STATUS_PREMIUM_PENDING,
                ProtectionPolicy::STATUS_PENDING_ISSUANCE,
                ProtectionPolicy::STATUS_ACTIVE,
            ])
            ->first();
        if ($duplicate) {
            return $duplicate->load('product');
        }

        $policy = ProtectionPolicy::create([
            'protection_product_id' => $product->id,
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'policy_reference' => 'OPF-PRT-'.Str::upper(Str::random(16)),
            'status' => ProtectionPolicy::STATUS_PREMIUM_DUE,
            'premium_amount_minor' => $product->premium_amount_minor,
            'premium_frequency' => $product->premium_frequency,
            'coverage_limit_minor' => $product->coverage_limit_minor,
            'disclosure_hash' => $product->disclosureHash(),
            'acceptance_metadata' => $acceptanceMetadata,
            'enrolled_at' => now(),
        ]);

        $this->auditLogger->record('protection.enrollment.accepted', $user, $policy, [
            'protection_product_id' => $product->id,
            'insurer_name' => $product->insurer_name,
            'underwriter_name' => $product->underwriter_name,
            'disclosure_hash' => $policy->disclosure_hash,
        ]);

        return $policy->fresh('product');
    }

    public function payPremium(
        ProtectionPolicy $policy,
        User $user,
        string $clientIdempotencyKey,
    ): ProtectionPremiumPayment {
        $this->assertOwnedPolicy($policy, $user);
        $policy->loadMissing('product');
        $this->assertProductAvailable($policy->product, allowRetiredForExistingPolicy: true);

        if (in_array($policy->status, [ProtectionPolicy::STATUS_CANCELLED, ProtectionPolicy::STATUS_EXPIRED], true)) {
            throw new InvalidArgumentException('Premium collection is unavailable for this policy state.');
        }

        $idempotencyKey = "protection:policy:{$policy->id}:premium:{$clientIdempotencyKey}";
        $existing = ProtectionPremiumPayment::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->load(['policy.product', 'mobileMoneyTransaction']);
        }

        $pendingExists = ProtectionPremiumPayment::query()
            ->where('protection_policy_id', $policy->id)
            ->whereIn('status', [
                ProtectionPremiumPayment::STATUS_COLLECTION_PENDING,
                ProtectionPremiumPayment::STATUS_COLLECTED_PENDING_PARTNER,
            ])
            ->exists();
        if ($pendingExists) {
            throw new InvalidArgumentException('This protection policy already has a premium payment in progress.');
        }

        [$periodStart, $periodEnd] = $this->premiumPeriod($policy);
        $payment = ProtectionPremiumPayment::create([
            'protection_policy_id' => $policy->id,
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'payment_reference' => 'OPF-PRM-'.Str::upper(Str::random(16)),
            'idempotency_key' => $idempotencyKey,
            'status' => ProtectionPremiumPayment::STATUS_COLLECTION_PENDING,
            'amount_minor' => $policy->premium_amount_minor,
            'currency' => $policy->product->currency,
            'coverage_period_start' => $periodStart,
            'coverage_period_end' => $periodEnd,
            'requested_at' => now(),
            'metadata' => [
                'insurer_name' => $policy->product->insurer_name,
                'underwriter_name' => $policy->product->underwriter_name,
            ],
        ]);

        if ($policy->status !== ProtectionPolicy::STATUS_ACTIVE) {
            $policy->update(['status' => ProtectionPolicy::STATUS_PREMIUM_PENDING]);
        }

        try {
            $mobileMoney = $this->mobileMoney->collect([
                'user_id' => $user->id,
                'institution_id' => $user->institution_id,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'phone' => $user->phone,
                'idempotency_key' => $idempotencyKey,
                'internal_reference' => $payment->payment_reference,
                'description' => 'OpFin protection premium',
                'purpose' => 'protection_premium',
                'protection_premium_payment_id' => $payment->id,
            ]);
            $payment->update(['mobile_money_transaction_id' => $mobileMoney->id]);
            $this->syncMobileMoney($mobileMoney->fresh());
        } catch (\Throwable $exception) {
            $payment->update([
                'status' => ProtectionPremiumPayment::STATUS_FAILED,
                'metadata' => array_merge($payment->metadata ?? [], ['failure' => $exception->getMessage()]),
            ]);
            if ($policy->status !== ProtectionPolicy::STATUS_ACTIVE) {
                $policy->update(['status' => ProtectionPolicy::STATUS_PREMIUM_DUE]);
            }
            throw $exception;
        }

        $this->auditLogger->record('protection.premium.requested', $user, $payment, [
            'protection_policy_id' => $policy->id,
            'amount_minor' => $payment->amount_minor,
        ]);

        return $payment->fresh(['policy.product', 'mobileMoneyTransaction']);
    }

    public function syncMobileMoney(MobileMoneyTransaction $mobileMoney): ?ProtectionPremiumPayment
    {
        $paymentId = (int) ($mobileMoney->metadata['protection_premium_payment_id'] ?? 0);
        if ($paymentId <= 0) {
            return null;
        }

        $payment = ProtectionPremiumPayment::query()->with('policy.product')->find($paymentId);
        if (! $payment) {
            return null;
        }

        if ($mobileMoney->status === MobileMoneyTransaction::STATUS_FAILED) {
            $payment->update(['status' => ProtectionPremiumPayment::STATUS_FAILED]);
            if ($payment->policy->status !== ProtectionPolicy::STATUS_ACTIVE) {
                $payment->policy->update(['status' => ProtectionPolicy::STATUS_PREMIUM_DUE]);
            }

            return $payment->fresh(['policy.product', 'mobileMoneyTransaction']);
        }

        if ($mobileMoney->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            return $payment->fresh(['policy.product', 'mobileMoneyTransaction']);
        }

        if ($payment->status !== ProtectionPremiumPayment::STATUS_CONFIRMED) {
            $payment->update([
                'status' => ProtectionPremiumPayment::STATUS_COLLECTED_PENDING_PARTNER,
                'provider_completed_at' => $payment->provider_completed_at ?: now(),
            ]);
            $this->ledger->postProtectionPremiumCollection($payment->fresh(['policy.product', 'mobileMoneyTransaction']), $mobileMoney);
        }

        $this->auditLogger->record('protection.premium.money_movement_synchronized', null, $payment, [
            'mobile_money_transaction_id' => $mobileMoney->id,
            'mobile_money_status' => $mobileMoney->status,
        ]);

        return $payment->fresh(['policy.product', 'mobileMoneyTransaction']);
    }

    public function confirmPremiumSettlement(
        ProtectionPremiumPayment $payment,
        User $actor,
        string $partnerReference,
        string $evidenceHash,
    ): ProtectionPremiumPayment {
        $payment->loadMissing(['policy.product', 'mobileMoneyTransaction']);
        if ($payment->status !== ProtectionPremiumPayment::STATUS_COLLECTED_PENDING_PARTNER) {
            throw new InvalidArgumentException('Only a collected premium awaiting insurer settlement can be confirmed.');
        }

        DB::transaction(function () use ($payment, $actor, $partnerReference, $evidenceHash) {
            $locked = ProtectionPremiumPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === ProtectionPremiumPayment::STATUS_CONFIRMED) {
                return;
            }
            if ($locked->status !== ProtectionPremiumPayment::STATUS_COLLECTED_PENDING_PARTNER) {
                throw new InvalidArgumentException('Protection premium state changed before partner confirmation.');
            }

            $locked->update([
                'status' => ProtectionPremiumPayment::STATUS_CONFIRMED,
                'partner_reference' => $partnerReference,
                'partner_evidence_hash' => strtolower($evidenceHash),
                'partner_confirmed_at' => now(),
            ]);
            $this->ledger->postProtectionPremiumSettlement($locked->fresh(['policy.product', 'mobileMoneyTransaction']), $actor);

            $policy = $locked->policy;
            if ($policy->status !== ProtectionPolicy::STATUS_ACTIVE) {
                $policy->update(['status' => ProtectionPolicy::STATUS_PENDING_ISSUANCE]);
            } else {
                $policy->update(['next_premium_due_date' => $this->nextPremiumDueDate($policy)]);
            }
        });

        $payment = $payment->fresh(['policy.product', 'mobileMoneyTransaction']);
        $this->auditLogger->record('protection.premium.partner_confirmed', $actor, $payment, [
            'partner_reference' => $partnerReference,
            'partner_evidence_hash' => strtolower($evidenceHash),
        ]);

        return $payment;
    }

    public function issuePolicy(
        ProtectionPolicy $policy,
        User $actor,
        string $externalPolicyNumber,
        string $partnerReference,
        string $coverStartDate,
        string $coverEndDate,
    ): ProtectionPolicy {
        $policy->loadMissing(['product', 'premiumPayments']);
        if ($policy->status === ProtectionPolicy::STATUS_ACTIVE) {
            return $policy;
        }
        if ($policy->status !== ProtectionPolicy::STATUS_PENDING_ISSUANCE) {
            throw new InvalidArgumentException('Policy issuance requires a partner-confirmed premium.');
        }
        if (! $policy->premiumPayments->contains('status', ProtectionPremiumPayment::STATUS_CONFIRMED)) {
            throw new InvalidArgumentException('Policy issuance requires at least one confirmed insurer premium settlement.');
        }

        $start = CarbonImmutable::parse($coverStartDate)->startOfDay();
        $end = CarbonImmutable::parse($coverEndDate)->startOfDay();
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('Protection cover end date must be after the start date.');
        }

        $policy->update([
            'external_policy_number' => $externalPolicyNumber,
            'partner_reference' => $partnerReference,
            'status' => ProtectionPolicy::STATUS_ACTIVE,
            'cover_start_date' => $start->toDateString(),
            'cover_end_date' => $end->toDateString(),
            'next_premium_due_date' => $this->nextPremiumDueDate($policy, $start),
            'issued_at' => now(),
        ]);

        $this->auditLogger->record('protection.policy.issued', $actor, $policy, [
            'insurer_name' => $policy->product->insurer_name,
            'external_policy_number' => $externalPolicyNumber,
            'partner_reference' => $partnerReference,
        ]);

        return $policy->fresh(['product', 'premiumPayments']);
    }

    public function submitClaim(ProtectionPolicy $policy, User $user, array $attributes): ProtectionClaim
    {
        $this->assertOwnedPolicy($policy, $user);
        if ($policy->status !== ProtectionPolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Claims can only be submitted against an active protection policy.');
        }

        $incidentDate = CarbonImmutable::parse($attributes['incident_date'])->startOfDay();
        if ($incidentDate->isFuture()) {
            throw new InvalidArgumentException('Claim incident date cannot be in the future.');
        }
        if ($policy->cover_start_date && $incidentDate->lessThan(CarbonImmutable::parse($policy->cover_start_date))) {
            throw new InvalidArgumentException('Claim incident predates the policy cover period.');
        }
        if ($policy->cover_end_date && $incidentDate->greaterThan(CarbonImmutable::parse($policy->cover_end_date))) {
            throw new InvalidArgumentException('Claim incident falls outside the policy cover period.');
        }

        $claim = ProtectionClaim::create([
            'protection_policy_id' => $policy->id,
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'claim_reference' => 'OPF-CLM-'.Str::upper(Str::random(16)),
            'status' => ProtectionClaim::STATUS_SUBMITTED,
            'incident_date' => $incidentDate->toDateString(),
            'category' => trim((string) $attributes['category']),
            'description' => trim((string) $attributes['description']),
            'claimed_amount_minor' => $attributes['claimed_amount_minor'] ?? null,
            'evidence' => $attributes['evidence'] ?? [],
            'submitted_at' => now(),
        ]);

        $this->auditLogger->record('protection.claim.submitted', $user, $claim, [
            'protection_policy_id' => $policy->id,
            'insurer_name' => $policy->product()->value('insurer_name'),
        ]);

        return $claim->fresh('policy.product');
    }

    public function disputeClaim(ProtectionClaim $claim, User $user, string $reason): ProtectionClaim
    {
        if ($claim->user_id !== $user->id) {
            throw new InvalidArgumentException('This protection claim does not belong to the authenticated customer.');
        }
        if (! in_array($claim->status, [ProtectionClaim::STATUS_DECLINED, ProtectionClaim::STATUS_CLOSED], true)) {
            throw new InvalidArgumentException('Only a declined or closed claim can enter the dispute workflow.');
        }

        $claim->update([
            'status' => ProtectionClaim::STATUS_DISPUTED,
            'decision_reason' => trim($reason),
            'resolved_at' => null,
        ]);
        $this->auditLogger->record('protection.claim.disputed', $user, $claim, ['customer_reason' => trim($reason)]);

        return $claim->fresh('policy.product');
    }

    public function updateClaim(
        ProtectionClaim $claim,
        User $actor,
        string $nextStatus,
        ?string $partnerClaimReference,
        ?string $decisionReason,
        ?int $approvedAmountMinor,
    ): ProtectionClaim {
        $allowed = [
            ProtectionClaim::STATUS_SUBMITTED => [ProtectionClaim::STATUS_PARTNER_REVIEW],
            ProtectionClaim::STATUS_PARTNER_REVIEW => [ProtectionClaim::STATUS_APPROVED, ProtectionClaim::STATUS_DECLINED],
            ProtectionClaim::STATUS_APPROVED => [ProtectionClaim::STATUS_PAID, ProtectionClaim::STATUS_CLOSED],
            ProtectionClaim::STATUS_DECLINED => [ProtectionClaim::STATUS_DISPUTED, ProtectionClaim::STATUS_CLOSED],
            ProtectionClaim::STATUS_DISPUTED => [ProtectionClaim::STATUS_PARTNER_REVIEW, ProtectionClaim::STATUS_APPROVED, ProtectionClaim::STATUS_DECLINED],
            ProtectionClaim::STATUS_PAID => [ProtectionClaim::STATUS_CLOSED],
            ProtectionClaim::STATUS_CLOSED => [],
        ];

        if (! in_array($nextStatus, $allowed[$claim->status] ?? [], true)) {
            throw new InvalidArgumentException("Invalid protection claim transition from {$claim->status} to {$nextStatus}.");
        }
        if (in_array($nextStatus, [ProtectionClaim::STATUS_PARTNER_REVIEW, ProtectionClaim::STATUS_APPROVED, ProtectionClaim::STATUS_DECLINED, ProtectionClaim::STATUS_PAID], true)
            && ! $partnerClaimReference) {
            throw new InvalidArgumentException('Partner claim reference is required for insurer-controlled claim states.');
        }
        if ($nextStatus === ProtectionClaim::STATUS_DECLINED && ! $decisionReason) {
            throw new InvalidArgumentException('A clear insurer decision reason is required for a declined claim.');
        }
        if ($nextStatus === ProtectionClaim::STATUS_APPROVED && ($approvedAmountMinor === null || $approvedAmountMinor < 0)) {
            throw new InvalidArgumentException('Approved claims require an approved amount in integer minor units.');
        }

        $claim->update([
            'status' => $nextStatus,
            'partner_claim_reference' => $partnerClaimReference ?: $claim->partner_claim_reference,
            'decision_reason' => $decisionReason,
            'approved_amount_minor' => $approvedAmountMinor ?? $claim->approved_amount_minor,
            'resolved_at' => in_array($nextStatus, [ProtectionClaim::STATUS_DECLINED, ProtectionClaim::STATUS_PAID, ProtectionClaim::STATUS_CLOSED], true) ? now() : null,
        ]);

        $this->auditLogger->record('protection.claim.partner_status_updated', $actor, $claim, [
            'status' => $nextStatus,
            'partner_claim_reference' => $claim->partner_claim_reference,
            'approved_amount_minor' => $claim->approved_amount_minor,
            'decision_authority' => 'insurer_or_underwriter',
        ]);

        return $claim->fresh('policy.product');
    }

    private function premiumPeriod(ProtectionPolicy $policy): array
    {
        $start = $policy->next_premium_due_date
            ? CarbonImmutable::parse($policy->next_premium_due_date)
            : CarbonImmutable::today();
        $end = match (strtolower($policy->premium_frequency)) {
            'weekly' => $start->addWeek()->subDay(),
            'monthly' => $start->addMonth()->subDay(),
            'quarterly' => $start->addMonths(3)->subDay(),
            'annual', 'yearly' => $start->addYear()->subDay(),
            'one_off', 'single' => $start,
            default => throw new InvalidArgumentException('Unsupported protection premium frequency.'),
        };

        return [$start->toDateString(), $end->toDateString()];
    }

    private function nextPremiumDueDate(ProtectionPolicy $policy, ?CarbonImmutable $from = null): ?string
    {
        $from ??= $policy->next_premium_due_date
            ? CarbonImmutable::parse($policy->next_premium_due_date)
            : CarbonImmutable::today();

        return match (strtolower($policy->premium_frequency)) {
            'weekly' => $from->addWeek()->toDateString(),
            'monthly' => $from->addMonth()->toDateString(),
            'quarterly' => $from->addMonths(3)->toDateString(),
            'annual', 'yearly' => $from->addYear()->toDateString(),
            'one_off', 'single' => null,
            default => throw new InvalidArgumentException('Unsupported protection premium frequency.'),
        };
    }

    private function assertOwnedPolicy(ProtectionPolicy $policy, User $user): void
    {
        if ($policy->user_id !== $user->id) {
            throw new InvalidArgumentException('This protection policy does not belong to the authenticated customer.');
        }
    }

    private function assertProductAvailable(ProtectionProduct $product, bool $allowRetiredForExistingPolicy = false): void
    {
        if ($product->status !== ProtectionProduct::STATUS_ACTIVE) {
            if (! $allowRetiredForExistingPolicy || ! in_array($product->status, [ProtectionProduct::STATUS_PAUSED, ProtectionProduct::STATUS_RETIRED], true)) {
                throw new InvalidArgumentException('This protection product is not currently available.');
            }
        }
        if (! $product->approved_by || ! $product->approved_at) {
            throw new InvalidArgumentException('This protection product has not completed independent product approval.');
        }
        if ($product->effective_at && $product->effective_at->isFuture()) {
            throw new InvalidArgumentException('This protection product is not effective yet.');
        }
        if ($product->expires_at && $product->expires_at->isPast() && ! $allowRetiredForExistingPolicy) {
            throw new InvalidArgumentException('This protection product has expired.');
        }
    }
}
