<?php

namespace App\Services;

use App\Models\CreditRepaymentScheduleItem;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UmraNplCapService
{
    public function evaluate(Loan $loan): Loan
    {
        if (! $loan->credit_offer_id) {
            return $loan;
        }

        $overdue = CreditRepaymentScheduleItem::query()
            ->where('loan_id', $loan->id)
            ->where('due_date', '<', now()->toDateString())
            ->where('total_outstanding_minor', '>', 0)
            ->exists();

        if (! $overdue) {
            return $loan;
        }

        $offer = $loan->creditOffer()->firstOrFail();
        $principalOutstanding = (int) CreditRepaymentScheduleItem::query()
            ->where('loan_id', $loan->id)
            ->sum('principal_outstanding_minor');
        $interestOutstanding = (int) CreditRepaymentScheduleItem::query()
            ->where('loan_id', $loan->id)
            ->sum('interest_outstanding_minor');

        $initialInterest = (int) $offer->interest_amount_minor;
        $defaultInterestCap = intdiv($initialInterest, 2);
        $interestRecoveryCap = $principalOutstanding;
        $contractualPlusDefault = min(
            $interestRecoveryCap,
            $interestOutstanding + min((int) $loan->default_interest_accrued_minor, $defaultInterestCap),
        );

        $loan->forceFill([
            'non_performing_at' => $loan->non_performing_at ?? now(),
            'principal_at_npl_minor' => $loan->principal_at_npl_minor ?? $principalOutstanding,
            'initial_interest_minor' => $loan->initial_interest_minor ?? $initialInterest,
            'default_interest_cap_minor' => $defaultInterestCap,
            'npl_recovery_cap_minor' => $principalOutstanding + $contractualPlusDefault,
            'npl_policy_checked_at' => now(),
        ])->save();

        return $loan->fresh();
    }

    public function accrueDefaultInterest(Loan $loan, int $requestedMinor): Loan
    {
        if ($requestedMinor < 0) {
            throw new InvalidArgumentException('Default interest cannot be negative.');
        }

        $loan = $this->evaluate($loan);
        if (! $loan->non_performing_at) {
            throw new InvalidArgumentException('Default interest can only be accrued after the loan becomes non-performing.');
        }

        $cap = (int) $loan->default_interest_cap_minor;
        $current = (int) $loan->default_interest_accrued_minor;
        $next = $current + $requestedMinor;

        if ($loan->umra_npl_cap_enforcement_enabled && $next > $cap) {
            throw new InvalidArgumentException('UMRA default-interest ceiling would be exceeded.');
        }

        $loan->update([
            'default_interest_accrued_minor' => $loan->umra_npl_cap_enforcement_enabled ? min($next, $cap) : $next,
            'npl_policy_checked_at' => now(),
        ]);

        return $this->evaluate($loan->fresh());
    }

    public function scan(): array
    {
        $count = 0;
        Loan::withoutGlobalScopes()
            ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])
            ->whereNotNull('credit_offer_id')
            ->orderBy('id')
            ->chunkById(100, function ($loans) use (&$count) {
                foreach ($loans as $loan) {
                    $before = $loan->non_performing_at;
                    $evaluated = $this->evaluate($loan);
                    if ($before === null && $evaluated->non_performing_at !== null) {
                        app(CreditReferenceReportingService::class)->queueLoanEvent($evaluated, 'non_performing');
                    }
                    $count++;
                }
            });

        return [
            'checked' => $count,
            'non_performing' => DB::table('loans')->whereNotNull('non_performing_at')->count(),
            'enforcement_enabled' => (bool) config('opfin.regulatory.enforce_umra_npl_cap', true),
        ];
    }
}
