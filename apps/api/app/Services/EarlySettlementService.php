<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\CreditRepaymentScheduleItem;
use App\Models\CustomerWallet;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Loan;
use App\Models\MobileMoneyTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EarlySettlementService
{
    public function __construct(
        private readonly FinancialPolicyService $policies,
        private readonly DefaultInterestService $defaultInterest,
        private readonly CreditFeeRecognitionService $feeRecognition,
        private readonly ProductionLedgerService $ledger,
        private readonly MobileMoneyService $mobileMoney,
        private readonly AuditLogger $auditLogger,
        private readonly CreditReferenceReportingService $creditReporting,
        private readonly TransactionReceiptService $receipts,
    ) {}

    public function quote(Loan $loan, User $user, ?Carbon $asOf = null): object
    {
        $this->assertOwned($loan, $user);
        if (! $loan->credit_offer_id) {
            throw new InvalidArgumentException('Early settlement is available only on governed production credit.');
        }

        $asOf ??= now();
        $loan = $this->defaultInterest->accrue($loan, $asOf);
        $offer = CreditOffer::query()->findOrFail($loan->credit_offer_id);
        $policy = $this->policies->active('regulatory_pricing', (string) $loan->loan_product_id, null, null, $asOf);
        $rules = $this->policies->rules($policy);
        $earlyRules = (array) ($rules['early_settlement'] ?? []);
        if ($earlyRules === []) {
            throw new InvalidArgumentException('The active regulatory pricing policy does not define early-settlement treatment.');
        }

        $schedule = CreditRepaymentScheduleItem::query()
            ->where('loan_id', $loan->id)
            ->where('total_outstanding_minor', '>', 0)
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $principal = (int) $schedule->sum('principal_outstanding_minor');
        $totalInterestOutstanding = (int) $schedule->sum('interest_outstanding_minor');
        $totalFeesOutstanding = (int) $schedule->sum('fees_outstanding_minor');
        $interestDue = $this->earnedInterestMinor($loan, $schedule, $asOf, $earlyRules, $rules);
        $interestDue = min($interestDue, $totalInterestOutstanding);
        $unearnedInterest = max(0, $totalInterestOutstanding - $interestDue);

        $feesDue = $totalFeesOutstanding;
        $unearnedFee = 0;
        if ((bool) ($earlyRules['rebate_unearned_financed_fees'] ?? false)) {
            $feesDue = (int) $schedule
                ->filter(fn (CreditRepaymentScheduleItem $item) => $item->due_date->lte($asOf->copy()->endOfDay()))
                ->sum('fees_outstanding_minor');
            $unearnedFee = max(0, $totalFeesOutstanding - $feesDue);
        }

        $defaultDue = $this->defaultInterest->outstandingMinor($loan);
        $settlementFee = $this->settlementFeeMinor($principal, $earlyRules);
        $total = $principal + $interestDue + $feesDue + $defaultDue + $settlementFee;
        if ($total <= 0) {
            throw new InvalidArgumentException('This loan has no settlement amount outstanding.');
        }

        $reference = 'OPF-EST-'.Str::upper(Str::random(18));
        $id = DB::table('early_settlement_quotes')->insertGetId([
            'loan_id' => $loan->id,
            'quote_reference' => $reference,
            'as_of_date' => $asOf->toDateString(),
            'principal_minor' => $principal,
            'contractual_interest_due_minor' => $interestDue,
            'default_interest_due_minor' => $defaultDue,
            'fees_due_minor' => $feesDue,
            'settlement_fee_minor' => $settlementFee,
            'unearned_interest_rebate_minor' => $unearnedInterest,
            'unearned_fee_rebate_minor' => $unearnedFee,
            'total_settlement_minor' => $total,
            'currency' => $offer->currency,
            'policy_snapshot' => json_encode([
                'policy_id' => $policy->id,
                'code' => $policy->code,
                'version' => $policy->version,
                'licence_class' => $policy->licence_class,
                'early_settlement' => $earlyRules,
            ], JSON_THROW_ON_ERROR),
            'status' => 'quoted',
            'expires_at' => now()->addMinutes((int) ($earlyRules['quote_valid_minutes'] ?? 60)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quote = DB::table('early_settlement_quotes')->find($id);
        $this->auditLogger->record('credit.early_settlement.quoted', $user, $loan, [
            'quote_reference' => $reference,
            'total_settlement_minor' => $total,
            'unearned_interest_rebate_minor' => $unearnedInterest,
            'unearned_fee_rebate_minor' => $unearnedFee,
        ]);

        return $quote;
    }

    public function initiate(
        object $quote,
        User $user,
        string $idempotencyKey,
        ?int $walletId = null,
    ): MobileMoneyTransaction {
        $loan = Loan::query()->findOrFail($quote->loan_id);
        $this->assertOwned($loan, $user);
        if ($quote->status !== 'quoted' || Carbon::parse($quote->expires_at)->isPast()) {
            throw new InvalidArgumentException('Early-settlement quote is no longer valid.');
        }

        $walletQuery = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('verified_at');
        $wallet = $walletId
            ? (clone $walletQuery)->whereKey($walletId)->first()
            : (clone $walletQuery)->where('is_default_repayment', true)->first();
        if ($walletId && ! $wallet) {
            throw new InvalidArgumentException('Choose a verified repayment wallet that belongs to your OpFin profile.');
        }
        $wallet ??= (clone $walletQuery)->orderByDesc('is_default_repayment')->first();
        $phone = $wallet?->msisdn ?? $user->phone;

        $reference = 'OPF-SET-'.strtoupper(substr(hash('sha256', trim($idempotencyKey)), 0, 32));
        $transaction = Transaction::query()->firstOrCreate(
            ['reference' => $reference],
            [
                'user_id' => $loan->user_id,
                'institution_id' => $loan->institution_id,
                'loan_application_id' => $loan->loan_application_id,
                'loan_id' => $loan->id,
                'type' => 'Repayment',
                'amount' => (int) $quote->total_settlement_minor,
                'phone' => $phone,
                'status' => 'Pending',
            ],
        );

        $money = $this->mobileMoney->collect([
            'transaction_id' => $transaction->id,
            'loan_id' => $loan->id,
            'user_id' => $loan->user_id,
            'institution_id' => $loan->institution_id,
            'amount_minor' => (int) $quote->total_settlement_minor,
            'currency' => (string) $quote->currency,
            'phone' => $phone,
            'idempotency_key' => trim($idempotencyKey),
            'internal_reference' => $reference,
            'description' => 'OpFin early loan settlement',
            'purpose' => 'early_settlement',
            'early_settlement_quote_id' => (int) $quote->id,
        ]);

        DB::table('early_settlement_quotes')->where('id', $quote->id)->update([
            'status' => 'collection_pending',
            'accepted_at' => now(),
            'updated_at' => now(),
        ]);
        $this->sync($money);

        return $money->fresh();
    }

    public function sync(MobileMoneyTransaction $money): ?Loan
    {
        $quoteId = (int) ($money->metadata['early_settlement_quote_id'] ?? 0);
        if ($quoteId <= 0 || ($money->metadata['purpose'] ?? null) !== 'early_settlement') {
            return null;
        }

        $quote = DB::table('early_settlement_quotes')->where('id', $quoteId)->first();
        if (! $quote) {
            return null;
        }
        $loan = Loan::query()->findOrFail($quote->loan_id);
        $transaction = $money->transaction_id ? Transaction::query()->find($money->transaction_id) : null;

        if ($money->status === MobileMoneyTransaction::STATUS_FAILED) {
            DB::table('early_settlement_quotes')->where('id', $quoteId)->update(['status' => 'payment_failed', 'updated_at' => now()]);
            $transaction?->update(['status' => 'FAILED']);
            $money->update(['accounting_status' => MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED]);

            return $loan;
        }

        if ($money->status === MobileMoneyTransaction::STATUS_REVERSED) {
            if ($quote->status === 'settled') {
                $money->update([
                    'accounting_status' => MobileMoneyTransaction::ACCOUNTING_EXCEPTION,
                    'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
                    'failure_reason' => 'A completed early settlement was reversed by the provider and requires controlled recovery.',
                ]);
                $loan->update(['status' => 'Exception']);
                $transaction?->update(['status' => 'Exception']);
            }

            return $loan->fresh();
        }

        if ($money->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            return $loan;
        }

        if ($quote->status === 'settled') {
            return $loan;
        }

        return DB::transaction(function () use ($money, $quote, $loan, $transaction) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $lockedQuote = DB::table('early_settlement_quotes')->where('id', $quote->id)->lockForUpdate()->first();
            if (! $lockedQuote || $lockedQuote->status === 'settled') {
                return $lockedLoan;
            }
            if ((int) $money->amount_minor !== (int) $lockedQuote->total_settlement_minor) {
                throw new InvalidArgumentException('Provider collection does not match the frozen early-settlement quote.');
            }

            $offer = CreditOffer::query()->findOrFail($lockedLoan->credit_offer_id);
            $this->feeRecognition->recognise(
                $lockedLoan,
                Carbon::parse($lockedQuote->as_of_date)->endOfDay(),
                true,
                (int) $lockedQuote->unearned_fee_rebate_minor,
            );

            $entries = [[
                'account_id' => $this->account(
                    'cash.'.strtolower((string) $money->provider).'.collection',
                    ucfirst((string) $money->provider).' collection cash',
                    'asset',
                    (string) $lockedQuote->currency,
                )->id,
                'direction' => LedgerEntry::DIRECTION_DEBIT,
                'amount_minor' => (int) $lockedQuote->total_settlement_minor,
                'memo' => 'Cash collected for early credit settlement',
            ]];

            $this->credit($entries, $this->account(
                'asset.loan_receivable.product_'.$lockedLoan->loan_product_id,
                'Loan receivable product '.$lockedLoan->loan_product_id,
                'asset',
                (string) $lockedQuote->currency,
            ), (int) $lockedQuote->principal_minor, 'Principal settled early');

            $this->credit($entries, $this->account(
                'income.interest.product_'.$lockedLoan->loan_product_id,
                'Interest income product '.$lockedLoan->loan_product_id,
                'income',
                (string) $lockedQuote->currency,
            ), (int) $lockedQuote->contractual_interest_due_minor, 'Earned contractual interest settled early');

            if ((int) $lockedQuote->default_interest_due_minor > 0) {
                $this->credit($entries, $this->account(
                    'asset.default_interest_receivable.product_'.$lockedLoan->loan_product_id,
                    'Default interest receivable product '.$lockedLoan->loan_product_id,
                    'asset',
                    (string) $lockedQuote->currency,
                ), (int) $lockedQuote->default_interest_due_minor, 'Accrued default interest settled');
            }

            if ((int) $lockedQuote->fees_due_minor > 0 && $offer->fee_treatment === 'financed') {
                $this->credit($entries, $this->account(
                    'asset.credit_fee_receivable.product_'.$lockedLoan->loan_product_id,
                    'Credit fee receivable product '.$lockedLoan->loan_product_id,
                    'asset',
                    (string) $lockedQuote->currency,
                ), (int) $lockedQuote->fees_due_minor, 'Financed fees settled early');
            }

            if ((int) $lockedQuote->settlement_fee_minor > 0) {
                $this->credit($entries, $this->account(
                    'income.early_settlement_fee.product_'.$lockedLoan->loan_product_id,
                    'Early settlement fee income product '.$lockedLoan->loan_product_id,
                    'income',
                    (string) $lockedQuote->currency,
                ), (int) $lockedQuote->settlement_fee_minor, 'Policy-authorised early settlement fee');
            }

            if ((int) $lockedQuote->unearned_fee_rebate_minor > 0 && $offer->fee_treatment === 'financed') {
                $entries[] = [
                    'account_id' => $this->account(
                        'liability.credit_fee_clearing.product_'.$lockedLoan->loan_product_id,
                        'Credit fee clearing product '.$lockedLoan->loan_product_id,
                        'liability',
                        (string) $lockedQuote->currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => (int) $lockedQuote->unearned_fee_rebate_minor,
                    'memo' => 'Unearned financed fee released on early settlement',
                ];
                $this->credit($entries, $this->account(
                    'asset.credit_fee_receivable.product_'.$lockedLoan->loan_product_id,
                    'Credit fee receivable product '.$lockedLoan->loan_product_id,
                    'asset',
                    (string) $lockedQuote->currency,
                ), (int) $lockedQuote->unearned_fee_rebate_minor, 'Unearned financed fee receivable cancelled');
            }

            $ledgerReference = 'loan.early_settlement:'.$lockedQuote->quote_reference;
            $this->ledger->post(
                $ledgerReference,
                'loan.early_settlement',
                $money,
                $entries,
                null,
                (string) $lockedQuote->currency,
                [
                    'loan_id' => $lockedLoan->id,
                    'early_settlement_quote_id' => $lockedQuote->id,
                    'unearned_interest_rebate_minor' => (int) $lockedQuote->unearned_interest_rebate_minor,
                    'unearned_fee_rebate_minor' => (int) $lockedQuote->unearned_fee_rebate_minor,
                ],
            );

            $asOf = Carbon::parse($lockedQuote->as_of_date)->endOfDay();
            $items = CreditRepaymentScheduleItem::query()->where('loan_id', $lockedLoan->id)->lockForUpdate()->get();
            foreach ($items as $item) {
                $future = $item->due_date->gt($asOf);
                $item->update([
                    'principal_outstanding_minor' => 0,
                    'interest_outstanding_minor' => 0,
                    'fees_outstanding_minor' => 0,
                    'total_outstanding_minor' => 0,
                    'status' => $future ? CreditRepaymentScheduleItem::STATUS_VOIDED : CreditRepaymentScheduleItem::STATUS_PAID,
                    'paid_at' => $future ? null : now(),
                ]);
            }

            if ((int) $lockedQuote->default_interest_due_minor > 0) {
                $lockedLoan->increment('default_interest_paid_minor', (int) $lockedQuote->default_interest_due_minor);
            }
            $lockedLoan->update(['status' => 'Cleared']);
            DB::table('early_settlement_quotes')->where('id', $lockedQuote->id)->update([
                'status' => 'settled',
                'settled_at' => now(),
                'updated_at' => now(),
            ]);
            $money->update([
                'accounting_status' => MobileMoneyTransaction::ACCOUNTING_POSTED,
                'accounting_posted_at' => now(),
                'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
            ]);
            $transaction?->update(['status' => 'SUCCESSFUL']);

            $this->auditLogger->record('credit.early_settlement.settled', null, $lockedLoan, [
                'quote_reference' => $lockedQuote->quote_reference,
                'ledger_reference' => $ledgerReference,
                'provider_reference' => $money->provider_reference,
            ]);

            DB::afterCommit(function () use ($money, $lockedLoan) {
                $this->receipts->issue(MobileMoneyTransaction::findOrFail($money->id), 'loan_repayment');
                $this->creditReporting->queueLoanEvent(Loan::findOrFail($lockedLoan->id), 'closure');
            });

            return $lockedLoan->fresh();
        });
    }

    private function earnedInterestMinor(Loan $loan, $schedule, Carbon $asOf, array $earlyRules, array $pricingRules): int
    {
        $treatment = strtolower((string) ($earlyRules['interest_treatment'] ?? ''));
        $due = (int) $schedule
            ->filter(fn (CreditRepaymentScheduleItem $item) => $item->due_date->lte($asOf->copy()->endOfDay()))
            ->sum('interest_outstanding_minor');

        if ($treatment === 'scheduled_due_only') {
            return $due;
        }
        if ($treatment !== 'pro_rata_to_date') {
            throw new InvalidArgumentException('Early-settlement policy must define interest_treatment scheduled_due_only or pro_rata_to_date.');
        }

        $lastDue = $schedule
            ->filter(fn (CreditRepaymentScheduleItem $item) => $item->due_date->lte($asOf->copy()->endOfDay()))
            ->max('due_date');
        $start = $lastDue ? Carbon::parse($lastDue) : Carbon::parse($loan->disbursed_at);
        $days = max(0, $start->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay()));
        if ($days === 0) {
            return $due;
        }

        $cycle = strtolower((string) $loan->loanProductTerm->interest_cycle);
        $cycleDays = (array) ($pricingRules['cycle_days'] ?? []);
        $denominator = isset($cycleDays[$cycle])
            ? max(1, (int) $cycleDays[$cycle])
            : match ($cycle) {
                'daily' => 1,
                'weekly' => 7,
                'monthly' => 30,
                'annual', 'annually', 'yearly' => 365,
                default => throw new InvalidArgumentException('Unsupported interest cycle for early settlement.'),
            };
        $rate = ((float) $loan->loanProductTerm->interest_rate / 100) / $denominator;
        $principal = (int) $schedule->sum('principal_outstanding_minor');
        $proRata = (int) round($principal * $rate * $days);

        return $due + $proRata;
    }

    private function settlementFeeMinor(int $principal, array $rules): int
    {
        $feeRules = (array) ($rules['settlement_fee'] ?? []);
        $fee = (int) ($feeRules['fixed_minor'] ?? 0)
            + (int) floor($principal * ((float) ($feeRules['percent_of_principal'] ?? 0) / 100));
        if (isset($feeRules['cap_minor'])) {
            $fee = min($fee, (int) $feeRules['cap_minor']);
        }

        return max(0, $fee);
    }

    private function assertOwned(Loan $loan, User $user): void
    {
        if ((int) $loan->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('This loan does not belong to the authenticated customer.');
        }
    }

    private function credit(array &$entries, LedgerAccount $account, int $amount, string $memo): void
    {
        if ($amount <= 0) {
            return;
        }
        $entries[] = [
            'account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_CREDIT,
            'amount_minor' => $amount,
            'memo' => $memo,
        ];
    }

    private function account(string $code, string $name, string $type, string $currency): LedgerAccount
    {
        $currency = strtoupper($currency);
        $account = LedgerAccount::query()->where('code', $code)->first();
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
