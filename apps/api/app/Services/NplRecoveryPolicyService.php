<?php

namespace App\Services;

use App\Models\CreditRepaymentScheduleItem;
use App\Models\Loan;
use App\Models\LoanNplControl;
use App\Models\MobileMoneyTransaction;
use InvalidArgumentException;

class NplRecoveryPolicyService
{
    public function evaluate(Loan $loan): LoanNplControl
    {
        $loan->loadMissing('creditOffer');

        $pastDue = $loan->credit_offer_id
            ? CreditRepaymentScheduleItem::query()
                ->where('loan_id', $loan->id)
                ->where('total_outstanding_minor', '>', 0)
                ->whereDate('due_date', '<', today())
                ->orderBy('due_date')
                ->first()
            : $loan->schedules()
                ->where('total_outstanding', '>', 0)
                ->whereDate('due_date', '<', today())
                ->orderBy('due_date')
                ->first();

        $existing = LoanNplControl::firstOrNew(['loan_id' => $loan->id]);
        $mode = (string) config('opfin.compliance.umra_npl_cap_mode', 'enforce');

        if (! $pastDue) {
            $existing->fill([
                'enforcement_mode' => $mode,
                'last_evaluated_at' => now(),
                'metadata' => array_merge($existing->metadata ?? [], ['currently_non_performing' => false]),
            ])->save();

            return $existing->fresh();
        }

        $principalOutstanding = $loan->credit_offer_id
            ? (int) CreditRepaymentScheduleItem::query()->where('loan_id', $loan->id)->sum('principal_outstanding_minor')
            : (int) round((float) $loan->schedules()->sum('principal_outstanding'));

        $initialInterest = (int) ($loan->creditOffer?->interest_amount_minor
            ?? max(0, ((int) $loan->repayment_amount - (int) $loan->amount)));

        $nonPerformingAt = $existing->non_performing_at
            ?? \Illuminate\Support\Carbon::parse($pastDue->due_date)->addDay()->startOfDay();

        $totalRecovered = (int) MobileMoneyTransaction::query()
            ->where('loan_id', $loan->id)
            ->where('direction', MobileMoneyTransaction::DIRECTION_COLLECTION)
            ->where('status', MobileMoneyTransaction::STATUS_SUCCESSFUL)
            ->where('created_at', '>=', $nonPerformingAt)
            ->sum('amount_minor');

        $defaultPenaltyCap = intdiv($initialInterest, 2);
        $recoverableInterestCap = $principalOutstanding;
        $totalRecoverableCap = $principalOutstanding + $recoverableInterestCap;

        $existing->fill([
            'non_performing_at' => $nonPerformingAt,
            'principal_at_npl_minor' => $existing->principal_at_npl_minor ?: $principalOutstanding,
            'initial_interest_minor' => $initialInterest,
            'default_penalty_cap_minor' => $defaultPenaltyCap,
            'recoverable_interest_cap_minor' => $recoverableInterestCap,
            'total_recoverable_cap_minor' => $totalRecoverableCap,
            'total_recovered_since_npl_minor' => $totalRecovered,
            'enforcement_mode' => $mode,
            'last_evaluated_at' => now(),
            'metadata' => [
                'currently_non_performing' => true,
                'first_unpaid_due_date' => (string) $pastDue->due_date,
                'rule_basis' => 'UMRA Digital Lending Guidelines 2024 section 14 tracking',
            ],
        ])->save();

        return $existing->fresh();
    }

    public function assertCollectionPermitted(Loan $loan, int $requestedAmountMinor): void
    {
        $control = $this->evaluate($loan);

        if (! $control->non_performing_at || $control->enforcement_mode !== 'enforce') {
            return;
        }

        $remainingRegulatoryCap = max(
            0,
            (int) $control->total_recoverable_cap_minor - (int) $control->total_recovered_since_npl_minor,
        );

        if ($requestedAmountMinor > $remainingRegulatoryCap) {
            throw new InvalidArgumentException(
                'Repayment amount exceeds the configured UMRA non-performing-loan recovery ceiling.',
            );
        }
    }

    public function recordPenaltyAccrual(Loan $loan, int $penaltyInterestMinor): LoanNplControl
    {
        if ($penaltyInterestMinor < 0) {
            throw new InvalidArgumentException('Penalty interest cannot be negative.');
        }

        $control = $this->evaluate($loan);
        $newTotal = (int) $control->penalty_interest_accrued_minor + $penaltyInterestMinor;

        if ($control->enforcement_mode === 'enforce' && $newTotal > (int) $control->default_penalty_cap_minor) {
            throw new InvalidArgumentException(
                'Penalty interest would exceed the configured UMRA default-interest ceiling.',
            );
        }

        $control->update(['penalty_interest_accrued_minor' => $newTotal]);

        return $control->fresh();
    }
}
