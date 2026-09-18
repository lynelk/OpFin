<?php

namespace App\Services;

use App\Models\CreditTermVariation;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreditTermVariationService
{
    private const ALLOWED_KEYS = [
        'interest_rate_percent',
        'fees_minor',
        'duration_days',
        'repayment_frequency',
        'credit_limit_minor',
        'payment_date',
    ];

    public function propose(Loan $loan, User $actor, array $changes, string $reason): CreditTermVariation
    {
        if ($changes === []) {
            throw new InvalidArgumentException('At least one proposed term change is required.');
        }

        $unknown = array_values(array_diff(array_keys($changes), self::ALLOWED_KEYS));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported credit-term variation fields: '.implode(', ', $unknown));
        }

        if (isset($changes['interest_rate_percent']) && (float) $changes['interest_rate_percent'] < 0) {
            throw new InvalidArgumentException('Interest rate cannot be negative.');
        }
        if (isset($changes['fees_minor']) && (int) $changes['fees_minor'] < 0) {
            throw new InvalidArgumentException('Fees cannot be negative.');
        }

        $requiresUmraApproval = array_key_exists('interest_rate_percent', $changes);

        return CreditTermVariation::create([
            'loan_id' => $loan->id,
            'credit_offer_id' => $loan->credit_offer_id,
            'proposed_by' => $actor->id,
            'status' => CreditTermVariation::STATUS_PROPOSED,
            'proposed_changes' => $changes,
            'reason' => $reason,
            'requires_umra_approval' => $requiresUmraApproval,
        ]);
    }

    public function recordUmraApproval(
        CreditTermVariation $variation,
        string $approvalReference,
        string $approvalDocumentHash,
    ): CreditTermVariation {
        if (! $variation->requires_umra_approval) {
            throw new InvalidArgumentException('This variation does not require UMRA interest-rate approval.');
        }

        if (trim($approvalReference) === '' || ! preg_match('/^[a-f0-9]{64}$/i', $approvalDocumentHash)) {
            throw new InvalidArgumentException('A UMRA approval reference and SHA-256 evidence hash are required.');
        }

        $variation->update([
            'status' => CreditTermVariation::STATUS_UMRA_APPROVED,
            'umra_approval_reference' => trim($approvalReference),
            'umra_approval_document_hash' => strtolower($approvalDocumentHash),
            'umra_approved_at' => now(),
        ]);

        return $variation->fresh();
    }

    public function customerConsent(CreditTermVariation $variation, User $customer, array $metadata): CreditTermVariation
    {
        $loan = Loan::withoutGlobalScopes()->findOrFail($variation->loan_id);
        if ((int) $loan->user_id !== (int) $customer->id) {
            throw new InvalidArgumentException('This term variation does not belong to the authenticated customer.');
        }

        if ($variation->requires_umra_approval && ! $variation->umra_approved_at) {
            throw new InvalidArgumentException('An interest-rate variation cannot be presented for acceptance before prior UMRA approval is recorded.');
        }

        $canonical = json_encode([
            'variation_id' => $variation->id,
            'loan_id' => $loan->id,
            'proposed_changes' => $variation->proposed_changes,
            'reason' => $variation->reason,
            'umra_approval_reference' => $variation->umra_approval_reference,
            'accepted_at' => now()->toIso8601String(),
            'metadata' => $metadata,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        $variation->update([
            'status' => CreditTermVariation::STATUS_CUSTOMER_CONSENTED,
            'customer_consent_hash' => hash('sha256', $canonical),
            'customer_consent_metadata' => array_merge($metadata, [
                'consent_reference' => 'CTV-'.Str::upper(Str::random(12)),
            ]),
            'customer_consented_at' => now(),
        ]);

        return $variation->fresh();
    }

    public function markApplied(CreditTermVariation $variation): CreditTermVariation
    {
        if (! $variation->customer_consented_at) {
            throw new InvalidArgumentException('No credit-term variation may take effect without recorded customer consent.');
        }

        if ($variation->requires_umra_approval && ! $variation->umra_approved_at) {
            throw new InvalidArgumentException('Interest-rate variation requires prior UMRA approval.');
        }

        $variation->update([
            'status' => CreditTermVariation::STATUS_APPLIED,
            'effective_at' => $variation->effective_at ?: now(),
            'applied_at' => now(),
        ]);

        return $variation->fresh();
    }
}
