<?php

namespace App\Services;

use App\Models\CreditRepaymentScheduleItem;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Loan;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class DefaultInterestService
{
    public function __construct(
        private readonly FinancialPolicyService $policies,
        private readonly ProductionLedgerService $ledger,
    ) {}

    public function accrue(Loan $loan, ?Carbon $asOf = null): Loan
    {
        if (! $loan->credit_offer_id) {
            throw new InvalidArgumentException('Default-interest accrual is only supported on governed production loans.');
        }
        if (strcasecmp((string) $loan->status, 'Written Off') === 0) {
            return $loan->fresh();
        }

        $asOf ??= now();
        if (! $loan->non_performing_at) {
            throw new InvalidArgumentException('Default interest cannot accrue before the loan becomes non-performing.');
        }

        $currency = strtoupper((string) ($loan->creditOffer?->currency ?? config('services.mobile_money.currency', 'UGX')));
        $lender = $loan->creditOffer?->disclosure_snapshot['lender_of_record'] ?? null;
        // Legacy offers retain their snapshotted class, or the pre-upgrade configured class.
        // A new lender disclosure with no class intentionally uses the generic policy.
        $licenceClass = $lender !== null ? ($lender['licence_class'] ?? '')
            : ($loan->creditOffer?->pricing_snapshot['regulatory_policy']['licence_class'] ?? null);
        $policy = $this->policies->active('regulatory_pricing', (string) $loan->loan_product_id, $licenceClass, $lender['country'] ?? null, $asOf);
        $rules = $this->policies->rules($policy);
        $defaultRules = (array) ($rules['default_interest'] ?? []);
        if ($defaultRules === []) {
            throw new InvalidArgumentException('The active regulatory pricing policy does not define default-interest rules.');
        }

        $basis = strtolower((string) ($defaultRules['basis'] ?? 'outstanding_principal'));
        if ($basis !== 'outstanding_principal') {
            throw new InvalidArgumentException('Unsupported governed default-interest basis.');
        }

        $ratePercent = (float) ($loan->loanProductTerm?->default_interest_rate ?? 0);
        if ($ratePercent < 0) {
            throw new InvalidArgumentException('Default-interest rate cannot be negative.');
        }
        if (isset($defaultRules['max_rate_percent'])) {
            $max = (float) $defaultRules['max_rate_percent'];
            if ($this->dailyRate($ratePercent, (string) ($loan->loanProductTerm?->default_interest_cycle ?? 'monthly'), $rules)
                > $this->dailyRate($max, (string) ($defaultRules['rate_cycle'] ?? 'monthly'), $rules) + 0.0000000001) {
                throw new InvalidArgumentException('Configured default-interest rate exceeds the active regulatory policy.');
            }
        }

        $from = $loan->default_interest_last_accrued_at
            ? Carbon::parse($loan->default_interest_last_accrued_at)
            : Carbon::parse($loan->non_performing_at);
        if ($asOf->lte($from)) {
            return $loan->fresh();
        }

        $days = max(0, $from->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay()));
        if ($days === 0 || $ratePercent === 0.0) {
            $loan->update(['default_interest_last_accrued_at' => $asOf]);

            return $loan->fresh();
        }

        $principalOutstanding = $this->principalOutstandingMinor($loan);
        $dailyRate = $this->dailyRate(
            $ratePercent,
            (string) ($loan->loanProductTerm?->default_interest_cycle ?? 'monthly'),
            $rules,
        );
        $calculated = (int) round($principalOutstanding * $dailyRate * $days);
        if ($calculated <= 0) {
            $loan->update(['default_interest_last_accrued_at' => $asOf]);

            return $loan->fresh();
        }

        $current = (int) $loan->default_interest_accrued_minor;
        $permittedTotal = $current + $calculated;

        if (isset($defaultRules['cap_percent_of_initial_interest'])) {
            $cap = (int) floor(
                (int) $loan->initial_interest_minor
                * ((float) $defaultRules['cap_percent_of_initial_interest'] / 100)
            );
            $permittedTotal = min($permittedTotal, $cap);
            $loan->default_interest_cap_minor = $cap;
        }

        if (isset($defaultRules['recovery_cap_percent_of_principal_at_npl'])) {
            $principalAtNpl = (int) ($loan->principal_at_npl_minor ?? $principalOutstanding);
            $recoveryCap = (int) floor(
                $principalAtNpl
                * ((float) $defaultRules['recovery_cap_percent_of_principal_at_npl'] / 100)
            );
            $loan->npl_recovery_cap_minor = $recoveryCap;

            $contractualOutstanding = $this->contractualInterestOutstandingMinor($loan);
            $maximumDefault = max(0, $recoveryCap - $contractualOutstanding);
            $permittedTotal = min($permittedTotal, $maximumDefault);
        }

        if (! $loan->umra_npl_cap_enforcement_enabled) {
            $override = $this->policies->approvedOverride(
                'credit.default_interest_cap',
                Loan::class,
                (int) $loan->id,
            );
            if (! $override) {
                throw new InvalidArgumentException('Default-interest enforcement cannot be disabled without a current maker-checker override.');
            }
            $permittedTotal = $current + $calculated;
        }

        $delta = max(0, $permittedTotal - $current);
        if ($delta > 0) {
            $reference = 'loan.default_interest_accrual:'.$loan->id.':'.$asOf->toDateString();
            if (! LedgerTransaction::where('reference', $reference)->exists()) {
                $this->ledger->post(
                    $reference,
                    'loan.default_interest_accrual',
                    $loan,
                    [
                        [
                            'account_id' => $this->account(
                                'asset.default_interest_receivable.product_'.$loan->loan_product_id,
                                'Default interest receivable product '.$loan->loan_product_id,
                                'asset',
                                $currency,
                            )->id,
                            'direction' => LedgerEntry::DIRECTION_DEBIT,
                            'amount_minor' => $delta,
                            'memo' => 'Governed default interest accrued on outstanding principal',
                        ],
                        [
                            'account_id' => $this->account(
                                'income.default_interest.product_'.$loan->loan_product_id,
                                'Default interest income product '.$loan->loan_product_id,
                                'income',
                                $currency,
                            )->id,
                            'direction' => LedgerEntry::DIRECTION_CREDIT,
                            'amount_minor' => $delta,
                            'memo' => 'Governed default interest income recognised',
                        ],
                    ],
                    null,
                    $currency,
                    [
                        'loan_id' => $loan->id,
                        'days' => $days,
                        'principal_outstanding_minor' => $principalOutstanding,
                        'rate_percent' => $ratePercent,
                        'policy_code' => $policy->code,
                        'policy_version' => $policy->version,
                    ],
                );
            }
        }

        $loan->forceFill([
            'default_interest_accrued_minor' => $permittedTotal,
            'default_interest_last_accrued_at' => $asOf,
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

    public function outstandingMinor(Loan $loan): int
    {
        return max(0, (int) $loan->default_interest_accrued_minor - (int) $loan->default_interest_paid_minor);
    }

    private function principalOutstandingMinor(Loan $loan): int
    {
        return (int) CreditRepaymentScheduleItem::query()
            ->where('loan_id', $loan->id)
            ->sum('principal_outstanding_minor');
    }

    private function contractualInterestOutstandingMinor(Loan $loan): int
    {
        return (int) CreditRepaymentScheduleItem::query()
            ->where('loan_id', $loan->id)
            ->sum('interest_outstanding_minor');
    }

    private function dailyRate(float $ratePercent, string $cycle, array $rules): float
    {
        $cycleDays = (array) ($rules['cycle_days'] ?? []);
        $days = isset($cycleDays[strtolower($cycle)])
            ? max(1, (int) $cycleDays[strtolower($cycle)])
            : match (strtolower($cycle)) {
                'daily' => 1,
                'weekly' => 7,
                'monthly' => 30,
                'annual', 'annually', 'yearly' => 365,
                default => throw new InvalidArgumentException('Unsupported default-interest cycle.'),
            };

        return ($ratePercent / 100) / $days;
    }

    private function account(string $code, string $name, string $type, string $currency): LedgerAccount
    {
        $currency = strtoupper($currency);
        $account = LedgerAccount::query()
            ->where('code', $code)
            ->where('currency', $currency)
            ->first();
        if ($account) {
            if (strtoupper((string) $account->currency) !== $currency || ! $account->is_active) {
                throw new InvalidArgumentException("Ledger account {$code} is unavailable for {$currency} postings.");
            }

            return $account;
        }

        return LedgerAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'currency' => $currency,
            'is_active' => true,
        ]);
    }
}
