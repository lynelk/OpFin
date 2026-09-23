<?php

namespace App\Services;

use App\Models\CreditRepaymentScheduleItem;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UmraNplCapService
{
    public function __construct(
        private readonly FinancialPolicyService $policies,
        private readonly DefaultInterestService $defaultInterest,
    ) {}

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

        $policy = $this->policies->active('regulatory_pricing', (string) $loan->loan_product_id);
        $rules = $this->policies->rules($policy);
        $defaultRules = (array) ($rules['default_interest'] ?? []);

        CreditRepaymentScheduleItem::query()
            ->where('loan_id', $loan->id)
            ->where('due_date', '<', now()->toDateString())
            ->where('total_outstanding_minor', '>', 0)
            ->whereNotIn('status', [CreditRepaymentScheduleItem::STATUS_PAID, CreditRepaymentScheduleItem::STATUS_VOIDED])
            ->update(['status' => CreditRepaymentScheduleItem::STATUS_OVERDUE, 'updated_at' => now()]);

        $principalAtNpl = (int) ($loan->principal_at_npl_minor ?? $principalOutstanding);
        $initialInterest = (int) $offer->interest_amount_minor;
        $defaultCap = null;
        if (isset($defaultRules['cap_percent_of_initial_interest'])) {
            $defaultCap = (int) floor(
                $initialInterest * ((float) $defaultRules['cap_percent_of_initial_interest'] / 100)
            );
        }

        $recoveryCap = null;
        if (isset($defaultRules['recovery_cap_percent_of_principal_at_npl'])) {
            $recoveryCap = (int) floor(
                $principalAtNpl * ((float) $defaultRules['recovery_cap_percent_of_principal_at_npl'] / 100)
            );
        }

        $loan->forceFill([
            'status' => strcasecmp((string) $loan->status, 'Active') === 0 ? 'Non-Performing' : $loan->status,
            'non_performing_at' => $loan->non_performing_at ?? now(),
            'principal_at_npl_minor' => $principalAtNpl,
            'initial_interest_minor' => $loan->initial_interest_minor ?? $initialInterest,
            'default_interest_cap_minor' => $defaultCap,
            'npl_recovery_cap_minor' => $recoveryCap,
            'default_interest_policy_snapshot' => [
                'policy_id' => $policy->id,
                'code' => $policy->code,
                'version' => $policy->version,
                'licence_class' => $policy->licence_class,
                'rules' => $defaultRules,
            ],
            'npl_policy_checked_at' => now(),
        ])->save();

        return $loan->fresh();
    }

    public function accrueDefaultInterest(Loan $loan, ?Carbon $asOf = null): Loan
    {
        return $this->defaultInterest->accrue($this->evaluate($loan), $asOf);
    }

    public function setEnforcement(Loan $loan, bool $enabled, User $actor, ?int $overrideId = null): Loan
    {
        if (! $enabled) {
            if (! $overrideId) {
                throw new InvalidArgumentException('Disabling a regulatory financial control requires an approved maker-checker override.');
            }

            $override = DB::table('financial_control_overrides')->where('id', $overrideId)->first();
            if (! $override
                || $override->status !== 'approved'
                || $override->control_code !== 'credit.default_interest_cap'
                || $override->subject_type !== Loan::class
                || (int) $override->subject_id !== (int) $loan->id
                || ($override->expires_at && Carbon::parse($override->expires_at)->isPast())) {
                throw new InvalidArgumentException('The supplied financial-control override is not valid for this loan.');
            }
        }

        $loan->update([
            'umra_npl_cap_enforcement_enabled' => $enabled,
            'npl_policy_checked_at' => now(),
        ]);

        app(AuditLogger::class)->record('credit.default_interest.enforcement_changed', $actor, $loan, [
            'enabled' => $enabled,
            'override_id' => $overrideId,
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
            'enforcement_enabled_by_default' => (bool) config('opfin.regulatory.enforce_umra_npl_cap', true),
        ];
    }
}
