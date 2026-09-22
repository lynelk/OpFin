<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanProductTerm;
use InvalidArgumentException;

class CreditEconomicsService
{
    public function __construct(private readonly FinancialPolicyService $policies) {}

    public function quoteForApplication(LoanApplication $application, int $principalMinor, array $pricing): array
    {
        $application->loadMissing('loanProductTerm');
        if (! $application->loanProductTerm) {
            throw new InvalidArgumentException('A loan product term is required to calculate credit economics.');
        }

        return $this->quote($application->loanProductTerm, $principalMinor, $pricing, (string) $application->loan_product_id);
    }

    public function quote(LoanProductTerm $term, int $principalMinor, array $pricing, ?string $productScope = null): array
    {
        if ($principalMinor <= 0) {
            throw new InvalidArgumentException('Credit principal must be a positive integer minor-unit amount.');
        }

        $durationDays = (int) $term->duration;
        if ($durationDays <= 0) {
            throw new InvalidArgumentException('Credit duration must be positive.');
        }

        $ratePercent = (float) $term->interest_rate;
        if (! is_finite($ratePercent) || $ratePercent < 0) {
            throw new InvalidArgumentException('Credit interest rate must be a finite non-negative percentage.');
        }

        $policy = $this->policies->active('regulatory_pricing', $productScope);
        $rules = $this->policies->rules($policy);
        $cycleDays = $this->cycleDays((string) $term->interest_cycle, $rules);
        $feeTreatment = strtolower((string) ($pricing['fee_treatment'] ?? 'financed'));
        if (! in_array($feeTreatment, ['financed', 'deducted'], true)) {
            throw new InvalidArgumentException('Credit fee treatment must be financed or deducted.');
        }

        $accessFeeMinor = (int) ($pricing['access_fee_minor'] ?? 0);
        $disbursementFeeMinor = (int) ($pricing['disbursement_fee_minor'] ?? 0);
        if ($accessFeeMinor < 0 || $disbursementFeeMinor < 0) {
            throw new InvalidArgumentException('Credit fees cannot be negative.');
        }
        $feesMinor = $accessFeeMinor + $disbursementFeeMinor;
        if ($feeTreatment === 'deducted' && $feesMinor >= $principalMinor) {
            throw new InvalidArgumentException('Deducted credit fees must remain below approved principal.');
        }

        $this->validateRateAndFees($rules, $term, $principalMinor, $accessFeeMinor, $disbursementFeeMinor);

        $frequencyDays = $this->frequencyDays((string) $term->repayment_frequency, $rules);
        $installments = max(1, (int) ceil($durationDays / $frequencyDays));
        $method = strtolower((string) $term->interest_type);
        $interestBasis = strtolower((string) ($rules['interest_basis'] ?? 'either'));

        if ($interestBasis === 'outstanding_principal' && ! in_array($method, ['amortization', 'amortisation', 'reducing_balance'], true)) {
            throw new InvalidArgumentException('The active regulatory pricing policy requires interest on outstanding principal.');
        }
        if ($interestBasis === 'original_principal' && strcasecmp((string) $term->interest_type, 'Flat') !== 0) {
            throw new InvalidArgumentException('The active regulatory pricing policy requires original-principal interest.');
        }

        $schedule = in_array($method, ['amortization', 'amortisation', 'reducing_balance'], true)
            ? $this->reducingBalanceSchedule($principalMinor, $ratePercent, $cycleDays, $durationDays, $frequencyDays, $installments)
            : $this->flatSchedule($principalMinor, $ratePercent, $cycleDays, $durationDays, $frequencyDays, $installments);

        $interestMinor = array_sum(array_column($schedule, 'interest_minor'));
        $repayableFees = $feeTreatment === 'financed' ? $feesMinor : 0;
        $feeSchedule = $this->allocate($repayableFees, $installments);

        foreach ($schedule as $i => &$row) {
            $row['fees_minor'] = $feeSchedule[$i];
            $row['total_due_minor'] = $row['principal_minor'] + $row['interest_minor'] + $row['fees_minor'];
        }
        unset($row);

        $netDisbursement = $feeTreatment === 'deducted' ? $principalMinor - $feesMinor : $principalMinor;
        $totalRepayment = $principalMinor + $interestMinor + $repayableFees;
        $financeCharge = $totalRepayment - $netDisbursement;
        $simpleAnnualCostPercent = $netDisbursement > 0
            ? ($financeCharge / $netDisbursement) * (365 / $durationDays) * 100
            : 0.0;

        return [
            'algorithm_version' => 'canonical-credit-economics-v1',
            'principal_amount_minor' => $principalMinor,
            'interest_amount_minor' => $interestMinor,
            'fees_minor' => $feesMinor,
            'access_fee_minor' => $accessFeeMinor,
            'disbursement_fee_minor' => $disbursementFeeMinor,
            'fee_treatment' => $feeTreatment,
            'net_disbursement_minor' => $netDisbursement,
            'total_repayment_minor' => $totalRepayment,
            'total_cost_of_credit_minor' => $interestMinor + $feesMinor,
            'duration_days' => $durationDays,
            'interest_rate_percent' => $ratePercent,
            'interest_cycle' => (string) $term->interest_cycle,
            'interest_type' => (string) $term->interest_type,
            'repayment_frequency' => (string) $term->repayment_frequency,
            'simple_annualised_cost_percent' => round($simpleAnnualCostPercent, 6),
            'schedule' => $schedule,
            'policy' => [
                'id' => $policy->id,
                'code' => $policy->code,
                'version' => $policy->version,
                'licence_class' => $policy->licence_class,
                'effective_from' => $policy->effective_from,
                'rules' => $rules,
            ],
        ];
    }

    public function debtServiceWithinDays(array $quote, int $days): int
    {
        $total = 0;
        foreach ($quote['schedule'] as $row) {
            if ((int) $row['due_offset_days'] <= $days) {
                $total += (int) $row['total_due_minor'];
            }
        }

        return $total;
    }

    private function validateRateAndFees(array $rules, LoanProductTerm $term, int $principalMinor, int $accessFeeMinor, int $disbursementFeeMinor): void
    {
        if (isset($rules['max_rate_percent'])) {
            $policyCycle = strtolower((string) ($rules['rate_cycle'] ?? $term->interest_cycle));
            $termCycle = strtolower((string) $term->interest_cycle);
            $configured = (float) $term->interest_rate;
            $max = (float) $rules['max_rate_percent'];

            $termDaily = $configured / $this->cycleDays($termCycle, $rules);
            $maxDaily = $max / $this->cycleDays($policyCycle, $rules);
            if ($termDaily > $maxDaily + 0.0000001) {
                throw new InvalidArgumentException('Configured interest rate exceeds the active effective-dated regulatory pricing policy.');
            }
        }

        $feeCaps = (array) ($rules['fee_caps'] ?? []);
        if (isset($feeCaps['access_fee_percent'])) {
            $capMinor = (int) floor($principalMinor * ((float) $feeCaps['access_fee_percent'] / 100));
            if ($accessFeeMinor > $capMinor) {
                throw new InvalidArgumentException('Access/origination fee exceeds the active regulatory pricing policy.');
            }
        }
        if (isset($feeCaps['disbursement_fee_minor']) && $disbursementFeeMinor > (int) $feeCaps['disbursement_fee_minor']) {
            throw new InvalidArgumentException('Disbursement fee exceeds the active regulatory pricing policy.');
        }
    }

    private function flatSchedule(int $principal, float $ratePercent, int $cycleDays, int $durationDays, int $frequencyDays, int $installments): array
    {
        $termRate = ($ratePercent / $cycleDays) * $durationDays / 100;
        $totalInterest = (int) round($principal * $termRate);
        $principalParts = $this->allocate($principal, $installments);
        $interestParts = $this->allocate($totalInterest, $installments);
        $rows = [];

        for ($i = 0; $i < $installments; $i++) {
            $rows[] = [
                'installment_number' => $i + 1,
                'due_offset_days' => min($durationDays, ($i + 1) * $frequencyDays),
                'principal_minor' => $principalParts[$i],
                'interest_minor' => $interestParts[$i],
            ];
        }

        return $rows;
    }

    private function reducingBalanceSchedule(int $principal, float $ratePercent, int $cycleDays, int $durationDays, int $frequencyDays, int $installments): array
    {
        $periodDays = $durationDays / $installments;
        $periodRate = ($ratePercent / 100) * ($periodDays / $cycleDays);
        $payment = $periodRate == 0.0
            ? $principal / $installments
            : ($principal * $periodRate * pow(1 + $periodRate, $installments)) / (pow(1 + $periodRate, $installments) - 1);

        $remaining = $principal;
        $rows = [];
        for ($i = 0; $i < $installments; $i++) {
            $interest = $periodRate == 0.0 ? 0 : (int) round($remaining * $periodRate);
            $principalPart = $i === $installments - 1
                ? $remaining
                : max(0, min($remaining, (int) round($payment) - $interest));
            $remaining -= $principalPart;
            $rows[] = [
                'installment_number' => $i + 1,
                'due_offset_days' => min($durationDays, (int) round(($i + 1) * $durationDays / $installments)),
                'principal_minor' => $principalPart,
                'interest_minor' => $interest,
            ];
        }

        if ($remaining !== 0) {
            throw new InvalidArgumentException('Reducing-balance schedule failed to allocate principal exactly.');
        }

        return $rows;
    }

    private function allocate(int $total, int $count): array
    {
        if ($total < 0 || $count <= 0) {
            throw new InvalidArgumentException('Invalid monetary allocation parameters.');
        }
        $base = intdiv($total, $count);
        $remainder = $total % $count;
        $parts = array_fill(0, $count, $base);
        $parts[$count - 1] += $remainder;

        return $parts;
    }

    private function cycleDays(string $cycle, array $rules): int
    {
        $configured = (array) ($rules['cycle_days'] ?? []);
        $key = strtolower($cycle);
        if (isset($configured[$key])) {
            return max(1, (int) $configured[$key]);
        }

        return match ($key) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            'annual', 'annually', 'yearly' => 365,
            default => throw new InvalidArgumentException("Unsupported interest cycle: {$cycle}"),
        };
    }

    private function frequencyDays(string $frequency, array $rules): int
    {
        $configured = (array) ($rules['repayment_frequency_days'] ?? []);
        $key = strtolower($frequency);
        if (isset($configured[$key])) {
            return max(1, (int) $configured[$key]);
        }

        return match ($key) {
            'daily' => 1,
            'weekly' => 7,
            'fortnightly' => 14,
            'monthly' => 30,
            default => throw new InvalidArgumentException("Unsupported repayment frequency: {$frequency}"),
        };
    }
}
