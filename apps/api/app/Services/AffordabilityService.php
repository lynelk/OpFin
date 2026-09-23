<?php

namespace App\Services;

use App\Models\CreditScoreComponent;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AffordabilityService
{
    public function __construct(private readonly CreditEconomicsService $economics) {}

    public function assess(User $user, LoanApplication $application, int $amountMinor, ?array $quote = null): array
    {
        $incomeMinor = $this->verifiedMonthlyIncomeMinor($user);
        if ($incomeMinor === null || $incomeMinor <= 0) {
            return [
                'status' => 'unavailable',
                'reason_code' => 'AFFORDABILITY_DATA_REQUIRED',
                'monthly_income_minor' => null,
                'estimated_obligation_minor' => null,
                'debt_service_ratio_percent' => null,
            ];
        }

        $existing = $this->existingThirtyDayDebtServiceMinor($user->id);
        try {
            $proposed = $quote
                ? $this->economics->debtServiceWithinDays($quote, 30)
                : $this->projectedThirtyDayDebtServiceMinor($application, $amountMinor);
        } catch (\InvalidArgumentException $exception) {
            return [
                'status' => 'unavailable',
                'reason_code' => 'REGULATORY_PRICING_POLICY_REQUIRED',
                'monthly_income_minor' => $incomeMinor,
                'estimated_obligation_minor' => null,
                'debt_service_ratio_percent' => null,
                'detail' => $exception->getMessage(),
            ];
        }
        $externalNetOfOpfin = $this->verifiedExternalObligationNetOfOpfinMinor($user);
        $externalObligation = $externalNetOfOpfin ?? ($this->verifiedMonthlyObligationMinor($user) ?? 0);
        $effective = $externalObligation + $existing + $proposed;
        $dsr = round(($effective / $incomeMinor) * 100, 2);
        $maximum = (float) config('opfin.credit.max_debt_service_ratio_percent', 35);

        return [
            'status' => $dsr <= $maximum ? 'eligible' : 'ineligible',
            'reason_code' => $dsr <= $maximum
                ? 'AFFORDABILITY_DSR_WITHIN_POLICY'
                : 'AFFORDABILITY_DSR_EXCEEDED',
            'monthly_income_minor' => $incomeMinor,
            'estimated_obligation_minor' => $effective,
            'debt_service_ratio_percent' => $dsr,
            'maximum_debt_service_ratio_percent' => $maximum,
            'existing_thirty_day_debt_service_minor' => $existing,
            'proposed_thirty_day_debt_service_minor' => $proposed,
            'verified_external_obligation_minor' => $externalObligation,
            'external_obligation_is_net_of_opfin' => $externalNetOfOpfin !== null,
        ];
    }

    private function verifiedMonthlyIncomeMinor(User $user): ?int
    {
        return $this->valueFromExternalComponents($user, 'verified_monthly_income_minor');
    }

    private function verifiedMonthlyObligationMinor(User $user): ?int
    {
        return $this->valueFromExternalComponents($user, 'verified_monthly_obligation_minor');
    }

    private function verifiedExternalObligationNetOfOpfinMinor(User $user): ?int
    {
        return $this->valueFromExternalComponents($user, 'verified_external_obligation_excluding_opfin_minor');
    }

    private function valueFromExternalComponents(User $user, string $key): ?int
    {
        $components = CreditScoreComponent::query()
            ->where('user_id', $user->id)
            ->whereIn('source', ['third_party', 'mno', 'crb'])
            ->where('status', CreditScoreComponent::STATUS_READY)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('received_at')
            ->get();

        foreach ($components as $component) {
            $value = $this->findNumericKey($component->raw_payload ?? [], $key);
            if ($value !== null && $value >= 0) {
                return $value;
            }
        }

        return null;
    }

    private function findNumericKey(array $payload, string $target): ?int
    {
        foreach ($payload as $key => $value) {
            if ((string) $key === $target && is_numeric($value)) {
                return (int) round((float) $value);
            }
            if (is_array($value)) {
                $found = $this->findNumericKey($value, $target);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function existingThirtyDayDebtServiceMinor(int $userId): int
    {
        $from = now()->toDateString();
        $to = now()->addDays(30)->toDateString();

        $production = 0;
        if (Schema::hasTable('credit_repayment_schedule_items')) {
            $production = (int) DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNotNull('loans.credit_offer_id')
                ->whereNull('loans.deleted_at')
                ->whereNotIn('loans.status', ['Reversed'])
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->whereBetween('schedule.due_date', [$from, $to])
                ->sum('schedule.total_outstanding_minor');
        }

        $legacy = 0;
        if (Schema::hasTable('loan_schedules')) {
            $legacy = (int) round((float) DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNull('loans.credit_offer_id')
                ->whereNull('loans.deleted_at')
                ->whereNotIn('loans.status', ['Reversed'])
                ->where('schedule.total_outstanding', '>', 0)
                ->whereBetween('schedule.due_date', [$from, $to])
                ->sum('schedule.total_outstanding'));
        }

        $essentials = 0;
        if (Schema::hasTable('essentials_repayment_schedule_items')) {
            $essentials = (int) DB::table('essentials_repayment_schedule_items as schedule')
                ->join('essentials_advances as advance', 'advance.id', '=', 'schedule.advance_id')
                ->where('advance.user_id', $userId)
                ->whereIn('advance.status', ['active', 'overdue'])
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->whereBetween('schedule.due_date', [$from, $to])
                ->sum('schedule.total_outstanding_minor');
        }

        return $production + $legacy + $essentials;
    }

    private function projectedThirtyDayDebtServiceMinor(
        LoanApplication $application,
        int $approvedAmountMinor,
    ): int {
        $pricing = [
            'access_fee_minor' => (int) round(
                $approvedAmountMinor * ((float) config('opfin.credit.default_pricing.access_fee_percent', 0) / 100)
            ),
            'disbursement_fee_minor' => (int) config('opfin.credit.default_pricing.disbursement_fee_minor', 0),
            'fee_treatment' => (string) config('opfin.credit.default_pricing.fee_treatment', 'financed'),
        ];

        $quote = $this->economics->quoteForApplication($application, $approvedAmountMinor, $pricing);

        return $this->economics->debtServiceWithinDays($quote, 30);
    }
}
