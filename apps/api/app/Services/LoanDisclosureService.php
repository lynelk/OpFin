<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\LoanApplication;

class LoanDisclosureService
{
    public function build(
        LoanApplication $application,
        int $principalMinor,
        int $interestMinor,
        int $accessFeeMinor,
        int $disbursementFeeMinor,
        int $totalRepaymentMinor,
        int $netDisbursementMinor,
        int $durationDays,
        float $configuredRatePercent,
        float $termRatePercent,
        string $interestCycle,
        string $interestType,
        string $repaymentFrequency,
        string $feeTreatment,
    ): array {
        $complaints = [
            'channel' => (string) config('opfin.compliance.complaints_channel', 'In-app Support'),
            'email' => config('opfin.compliance.complaints_email'),
            'phone' => config('opfin.compliance.complaints_phone'),
            'resolution_sla_days' => 30,
            'process' => 'Submit a complaint through the disclosed support channel. OpFin records the complaint, assigns a case number, investigates it and targets resolution within 30 days.',
        ];

        return [
            'schema_version' => 'umra-loan-disclosure-v1',
            'currency' => 'UGX',
            'principal_amount_minor' => $principalMinor,
            'amount_customer_receives_minor' => $netDisbursementMinor,
            'interest_amount_minor' => $interestMinor,
            'fees_minor' => $accessFeeMinor + $disbursementFeeMinor,
            'total_cost_of_credit_minor' => $interestMinor + $accessFeeMinor + $disbursementFeeMinor,
            'total_repayment_minor' => $totalRepaymentMinor,
            'duration_days' => $durationDays,
            'repayment_frequency' => $repaymentFrequency,
            'repayment_timing' => [
                'first_payment_due_days_after_disbursement' => $this->frequencyDays($repaymentFrequency),
                'final_payment_due_days_after_disbursement' => $durationDays,
                'explanation' => 'Exact calendar dates are fixed from the confirmed disbursement date and are available in the repayment schedule.',
            ],
            'interest' => [
                'configured_rate_percent' => round($configuredRatePercent, 6),
                'cycle' => $interestCycle,
                'type' => $interestType,
                'term_rate_percent' => round($termRatePercent, 6),
                'calculation' => 'Term rate = configured rate ÷ cycle days × loan duration days. Interest = principal × term rate.',
            ],
            'fees' => [
                'access_fee_minor' => $accessFeeMinor,
                'access_fee_calculation' => $accessFeeMinor > 0 ? 'Fixed or policy-calculated access fee disclosed before acceptance.' : 'No access fee.',
                'disbursement_fee_minor' => $disbursementFeeMinor,
                'disbursement_fee_calculation' => $disbursementFeeMinor > 0 ? 'Fixed disbursement fee disclosed before acceptance.' : 'No disbursement fee.',
                'fee_treatment' => $feeTreatment,
            ],
            'default_and_penalties' => [
                'default_penalty_interest_ceiling' => 'Tracked against the UMRA default-interest ceiling; enforcement mode is controlled by the Uganda compliance policy.',
                'npl_recovery_ceiling' => 'For a non-performing loan, OpFin tracks the principal at NPL, contractual interest recovery ceiling and total regulatory recovery cap.',
            ],
            'complaints' => $complaints,
            'credit_information_exchange' => [
                'notice' => 'Positive and negative credit performance may be submitted to the applicable credit-reference mechanism in accordance with the accepted loan agreement and applicable requirements.',
                'reporting_control' => 'OpFin validates required borrower and account fields before outbound reporting and records each submission.',
            ],
            'guarantors' => [
                'required_count' => (int) ($application->loanProductTerm?->guarantors_required ?? 0),
                'maximum_contacts' => 2,
                'notice' => 'OpFin does not read the customer contact list. Any guarantor contact must be provided deliberately and electronically verified before it can be relied upon.',
            ],
            'term_variation' => [
                'notice' => 'Accepted credit terms are not changed unilaterally. Any variation requires customer consent; an interest-rate change additionally requires recorded prior UMRA approval before it may take effect.',
            ],
            'provider_identity' => [
                'licensed_entity_name' => config('opfin.compliance.licensed_entity_name'),
                'trading_name' => 'OpFin',
                'business_address' => config('opfin.compliance.business_address'),
                'regulator' => 'Uganda Microfinance Regulatory Authority (UMRA)',
            ],
        ];
    }

    private function frequencyDays(string $frequency): int
    {
        return match (strtolower($frequency)) {
            'daily' => 1,
            'weekly' => 7,
            'fortnightly' => 14,
            'monthly' => 30,
            default => 30,
        };
    }

    public function hash(CreditOffer $offer): string
    {
        return hash('sha256', json_encode($offer->disclosure_snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }
}
