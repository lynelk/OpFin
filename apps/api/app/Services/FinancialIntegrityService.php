<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\LedgerTransaction;
use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialIntegrityService
{
    public function __construct(private readonly LongRangeIntegrityService $longRangeIntegrity) {}

    public function run(string $scope = 'platform'): object
    {
        $runId = DB::table('financial_integrity_runs')->insertGetId([
            'status' => 'running',
            'scope' => $scope,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $findings = [];
        $ledgerChecked = 0;
        $unbalanced = 0;
        $netImbalance = 0;
        $orphanEntries = 0;
        $duplicateReferences = 0;
        $paymentExceptions = 0;
        $paymentAccountingExceptions = 0;
        $paymentStatementExceptions = 0;

        if (Schema::hasTable('ledger_transactions') && Schema::hasTable('ledger_entries')) {
            $balances = DB::table('ledger_transactions as t')
                ->leftJoin('ledger_entries as e', 'e.ledger_transaction_id', '=', 't.id')
                ->select('t.id', 't.reference', 't.currency')
                ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'debit' THEN e.amount_minor ELSE 0 END), 0) AS debit_minor")
                ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'credit' THEN e.amount_minor ELSE 0 END), 0) AS credit_minor")
                ->groupBy('t.id', 't.reference', 't.currency')
                ->get();

            $ledgerChecked = $balances->count();
            foreach ($balances as $balance) {
                $difference = (int) $balance->debit_minor - (int) $balance->credit_minor;
                if ($difference === 0) {
                    continue;
                }
                $unbalanced++;
                $netImbalance += $difference;
                $findings[] = $this->alert($runId, 'critical', 'ledger_unbalanced', $balance->reference,
                    'Ledger transaction debits and credits do not balance.', [
                        'debit_minor' => (int) $balance->debit_minor,
                        'credit_minor' => (int) $balance->credit_minor,
                        'difference_minor' => $difference,
                        'currency' => $balance->currency,
                    ]);
            }

            $invalidEntries = DB::table('ledger_entries as e')
                ->join('ledger_transactions as t', 't.id', '=', 'e.ledger_transaction_id')
                ->join('ledger_accounts as a', 'a.id', '=', 'e.ledger_account_id')
                ->where(function ($query) {
                    $query->whereNotIn('e.direction', ['debit', 'credit'])
                        ->orWhere('e.amount_minor', '<=', 0)
                        ->orWhereColumn('e.currency', '!=', 't.currency')
                        ->orWhereColumn('a.currency', '!=', 't.currency');
                })
                ->select('e.id', 't.reference', 'e.direction', 'e.amount_minor', 'e.currency as entry_currency', 't.currency as transaction_currency', 'a.code as account_code', 'a.currency as account_currency')
                ->limit(250)
                ->get();
            foreach ($invalidEntries as $entry) {
                $findings[] = $this->alert($runId, 'critical', 'invalid_ledger_entry', (string) $entry->id,
                    'Ledger entry violates amount, direction or currency invariants.', (array) $entry);
            }

            $orphanEntries = DB::table('ledger_entries as e')
                ->leftJoin('ledger_transactions as t', 't.id', '=', 'e.ledger_transaction_id')
                ->whereNull('t.id')->count();
            if ($orphanEntries > 0) {
                $findings[] = $this->alert($runId, 'critical', 'orphan_ledger_entries', null,
                    'Ledger entries exist without a parent transaction.', ['count' => $orphanEntries]);
            }

            $duplicateReferences = DB::table('ledger_transactions')
                ->select('reference')->groupBy('reference')->havingRaw('count(*) > 1')->count();
            if ($duplicateReferences > 0) {
                $findings[] = $this->alert($runId, 'critical', 'duplicate_ledger_reference', null,
                    'Duplicate immutable ledger references were detected.', ['count' => $duplicateReferences]);
            }
        }

        if (Schema::hasTable('mobile_money_transactions')) {
            $paymentAccountingExceptions = MobileMoneyTransaction::query()
                ->whereIn('status', [MobileMoneyTransaction::STATUS_SUCCESSFUL, MobileMoneyTransaction::STATUS_REVERSED])
                ->where(function ($query) {
                    $query->whereNotIn('accounting_status', [
                        MobileMoneyTransaction::ACCOUNTING_POSTED,
                        MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED,
                    ])->orWhere(function ($query) {
                        $query->where('accounting_status', MobileMoneyTransaction::ACCOUNTING_POSTED)
                            ->whereNull('accounting_posted_at');
                    });
                })
                ->count();

            if ($paymentAccountingExceptions > 0) {
                $findings[] = $this->alert($runId, 'critical', 'payment_accounting_exception', null,
                    'Provider-final money movements exist without completed internal accounting.', ['count' => $paymentAccountingExceptions]);
            }

            $paymentStatementExceptions = MobileMoneyTransaction::query()
                ->whereIn('status', [MobileMoneyTransaction::STATUS_SUCCESSFUL, MobileMoneyTransaction::STATUS_REVERSED])
                ->where(function ($query) {
                    $query->where('statement_reconciliation_status', '!=', MobileMoneyTransaction::STATEMENT_MATCHED)
                        ->orWhereNull('statement_reconciled_at');
                })
                ->count();

            if ($paymentStatementExceptions > 0) {
                $findings[] = $this->alert($runId, 'high', 'payment_statement_reconciliation_exception', null,
                    'Provider-final money movements have not been independently matched to provider statement evidence.', ['count' => $paymentStatementExceptions]);
            }

            $paymentExceptions = $paymentAccountingExceptions + $paymentStatementExceptions;

            $duplicateProviderRefs = MobileMoneyTransaction::query()
                ->whereNotNull('provider_reference')->select('provider', 'provider_reference')
                ->groupBy('provider', 'provider_reference')->havingRaw('count(*) > 1')->get();
            foreach ($duplicateProviderRefs as $duplicate) {
                $reference = strtolower((string) $duplicate->provider).':'.$duplicate->provider_reference;
                $findings[] = $this->alert($runId, 'critical', 'duplicate_provider_reference', $reference,
                    'The same provider reference appears on multiple money-movement records for one provider.', [
                        'provider' => $duplicate->provider,
                        'provider_reference' => $duplicate->provider_reference,
                    ]);
            }
        }

        $this->scanExpectedCreditPostings($runId, $findings);
        $this->scanCreditSubledgerReconciliation($runId, $findings);
        $this->scanImpairmentCoverage($runId, $findings);
        $this->scanProviderSettlementEvidence($runId, $findings);
        $this->scanRevenueTaxAndPartnerCompleteness($runId, $findings);
        $this->scanAssetEconomics($runId, $findings);
        $this->scanLongRangePaymentReconciliation($runId, $findings);

        foreach ($this->longRangeIntegrity->scan() as $finding) {
            $findings[] = $this->alert(
                $runId,
                $finding['severity'],
                $finding['type'],
                $finding['reference'],
                $finding['description'],
                $finding['evidence'],
            );
        }

        $this->resolveAbsentAlerts($runId, $findings, $scope);

        $canonical = json_encode($findings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $status = collect($findings)->contains(fn ($finding) => ($finding['severity'] ?? null) === 'critical')
            ? 'critical'
            : (count($findings) > 0 ? 'exceptions' : 'balanced');

        DB::table('financial_integrity_runs')->where('id', $runId)->update([
            'status' => $status,
            'ledger_transactions_checked' => $ledgerChecked,
            'unbalanced_transactions' => $unbalanced,
            'payment_exceptions' => $paymentExceptions,
            'duplicate_references' => $duplicateReferences,
            'orphan_entries' => $orphanEntries,
            'net_ledger_imbalance_minor' => $netImbalance,
            'findings' => $canonical,
            'evidence_hash' => hash('sha256', $canonical),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('financial_integrity_runs')->find($runId);
    }

    public function summary(): array
    {
        $latest = Schema::hasTable('financial_integrity_runs')
            ? DB::table('financial_integrity_runs')->latest('id')->first()
            : null;

        return [
            'latest_run' => $latest,
            'open_critical_alerts' => Schema::hasTable('financial_integrity_alerts')
                ? DB::table('financial_integrity_alerts')->where('status', 'open')->where('severity', 'critical')->count()
                : 0,
            'open_high_alerts' => Schema::hasTable('financial_integrity_alerts')
                ? DB::table('financial_integrity_alerts')->where('status', 'open')->where('severity', 'high')->count()
                : 0,
            'platform_balanced' => $latest?->status === 'balanced',
            'funds_integrity_rule' => 'Every governed financial event must have the expected posting, subledger balance, impairment evidence, provider-statement match and bank-settlement evidence. Missing postings, imbalance, false settlement, balance drift, stale impairment, funding mismatch, duplicate provider reference, currency mismatch or unresolved partner/revenue/tax exceptions remain explicit and can never be silently balanced away.',
        ];
    }

    private function scanExpectedCreditPostings(int $runId, array &$findings): void
    {
        if (! Schema::hasTable('credit_offers') || ! Schema::hasTable('mobile_money_transactions') || ! Schema::hasTable('ledger_transactions')) {
            return;
        }

        $transactions = MobileMoneyTransaction::query()
            ->where('direction', MobileMoneyTransaction::DIRECTION_DISBURSEMENT)
            ->whereNotNull('credit_offer_id')
            ->whereIn('status', [MobileMoneyTransaction::STATUS_SUCCESSFUL, MobileMoneyTransaction::STATUS_REVERSED])
            ->get(['id', 'credit_offer_id', 'loan_id', 'provider', 'amount_minor', 'currency', 'status', 'provider_reference']);

        foreach ($transactions as $transaction) {
            $offer = CreditOffer::query()->find($transaction->credit_offer_id);
            if (! $offer) {
                $findings[] = $this->alert($runId, 'critical', 'credit_disbursement_missing_offer', (string) $transaction->id,
                    'Production disbursement references a missing credit offer.', ['mobile_money_transaction_id' => $transaction->id]);

                continue;
            }

            if ((int) $transaction->amount_minor !== (int) $offer->net_disbursement_minor || strtoupper((string) $transaction->currency) !== strtoupper((string) $offer->currency)) {
                $findings[] = $this->alert($runId, 'critical', 'credit_disbursement_provider_amount_mismatch', $offer->offer_reference,
                    'Provider-confirmed disbursement amount or currency does not match the immutable credit offer.', [
                        'mobile_money_transaction_id' => $transaction->id,
                        'provider_amount_minor' => (int) $transaction->amount_minor,
                        'offer_net_disbursement_minor' => (int) $offer->net_disbursement_minor,
                        'provider_currency' => $transaction->currency,
                        'offer_currency' => $offer->currency,
                    ]);
            }

            $originalReference = 'loan.disbursement:credit-offer:'.$offer->offer_reference;
            $original = LedgerTransaction::query()->where('reference', $originalReference)->first();
            if (! $original) {
                if ($transaction->status === MobileMoneyTransaction::STATUS_SUCCESSFUL || $transaction->loan_id) {
                    $findings[] = $this->alert($runId, 'critical', 'credit_disbursement_missing_ledger_posting', $offer->offer_reference,
                        'Production credit disbursement has no required immutable ledger posting.', [
                            'mobile_money_transaction_id' => $transaction->id,
                            'credit_offer_id' => $offer->id,
                            'loan_id' => $transaction->loan_id,
                            'expected_ledger_reference' => $originalReference,
                        ]);
                }
            } else {
                $this->scanCreditPostingArithmetic($runId, $findings, $transaction, $offer, $originalReference, false);
            }

            if ($transaction->status === MobileMoneyTransaction::STATUS_REVERSED && $transaction->loan_id) {
                $reversalReference = 'loan.disbursement.reversal:credit-offer:'.$offer->offer_reference;
                $loanStatus = DB::table('loans')->where('id', $transaction->loan_id)->value('status');
                if ($loanStatus !== 'Exception' && ! LedgerTransaction::query()->where('reference', $reversalReference)->exists()) {
                    $findings[] = $this->alert($runId, 'critical', 'credit_disbursement_missing_reversal_posting', $offer->offer_reference,
                        'Provider-reversed production disbursement still lacks its append-only economic reversal posting.', [
                            'mobile_money_transaction_id' => $transaction->id,
                            'credit_offer_id' => $offer->id,
                            'loan_id' => $transaction->loan_id,
                            'expected_reversal_reference' => $reversalReference,
                        ]);
                } elseif ($loanStatus !== 'Exception') {
                    $this->scanCreditPostingArithmetic($runId, $findings, $transaction, $offer, $reversalReference, true);
                }
            }
        }
    }

    private function scanCreditPostingArithmetic(int $runId, array &$findings, MobileMoneyTransaction $transaction, CreditOffer $offer, string $ledgerReference, bool $reversal): void
    {
        if (! $transaction->loan_id) {
            $findings[] = $this->alert($runId, 'critical', 'credit_disbursement_missing_loan', $offer->offer_reference,
                'Provider-final production credit movement is not linked to a loan.', ['mobile_money_transaction_id' => $transaction->id]);

            return;
        }

        $loanProductId = DB::table('loans')->where('id', $transaction->loan_id)->value('loan_product_id');
        if (! $loanProductId) {
            return;
        }

        $principal = (int) $offer->principal_amount_minor;
        $cash = (int) $offer->net_disbursement_minor;
        $fees = (int) $offer->fees_minor;
        $expected = [];
        $this->expect($expected, 'asset.loan_receivable.product_'.$loanProductId, $reversal ? 'credit' : 'debit', $principal);
        $this->expect($expected, 'cash.'.strtolower((string) $transaction->provider).'.disbursement', $reversal ? 'debit' : 'credit', $cash);

        if ($offer->fee_treatment === 'deducted' && $fees > 0) {
            $this->expect($expected, 'liability.credit_fee_clearing.product_'.$loanProductId, $reversal ? 'debit' : 'credit', $fees);
        }
        if ($offer->fee_treatment === 'financed' && $fees > 0) {
            $this->expect($expected, 'asset.credit_fee_receivable.product_'.$loanProductId, $reversal ? 'credit' : 'debit', $fees);
            $this->expect($expected, 'liability.credit_fee_clearing.product_'.$loanProductId, $reversal ? 'debit' : 'credit', $fees);
        }

        $actual = DB::table('ledger_transactions as t')
            ->join('ledger_entries as e', 'e.ledger_transaction_id', '=', 't.id')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.ledger_account_id')
            ->where('t.reference', $ledgerReference)
            ->select('a.code', 'e.direction')
            ->selectRaw('SUM(e.amount_minor) as amount_minor')
            ->groupBy('a.code', 'e.direction')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->code.'|'.$row->direction => (int) $row->amount_minor])
            ->all();

        ksort($expected);
        ksort($actual);
        if ($expected !== $actual) {
            $findings[] = $this->alert($runId, 'critical', 'credit_ledger_economic_mismatch', $ledgerReference,
                'Credit ledger transaction is balanced but does not match the expected economic posting.', [
                    'expected' => $expected,
                    'actual' => $actual,
                    'fee_treatment' => $offer->fee_treatment,
                    'principal_minor' => $principal,
                    'cash_minor' => $cash,
                    'fees_minor' => $fees,
                    'reversal' => $reversal,
                ]);
        }
    }

    private function expect(array &$expected, string $code, string $direction, int $amountMinor): void
    {
        if ($amountMinor <= 0) {
            return;
        }
        $expected[$code.'|'.$direction] = ($expected[$code.'|'.$direction] ?? 0) + $amountMinor;
    }

    private function scanCreditSubledgerReconciliation(int $runId, array &$findings): void
    {
        if (
            ! Schema::hasTable('loans')
            || ! Schema::hasTable('ledger_accounts')
            || ! Schema::hasTable('ledger_entries')
            || ! Schema::hasTable('credit_repayment_schedule_items')
            || ! Schema::hasTable('credit_offers')
        ) {
            return;
        }

        $expected = [];
        $production = DB::table('loans as l')
            ->join('credit_offers as o', 'o.id', '=', 'l.credit_offer_id')
            ->join('credit_repayment_schedule_items as s', 's.loan_id', '=', 'l.id')
            ->leftJoin('credit_write_offs as w', 'w.loan_id', '=', 'l.id')
            ->whereNull('l.deleted_at')
            ->whereNotIn('l.status', ['Reversed'])
            ->select('l.loan_product_id', 'o.currency')
            ->selectRaw('SUM(CASE WHEN w.id IS NULL THEN s.principal_outstanding_minor ELSE 0 END) AS principal_outstanding_minor')
            ->selectRaw('SUM(CASE WHEN w.id IS NULL THEN s.fees_outstanding_minor ELSE 0 END) AS fee_receivable_minor')
            ->groupBy('l.loan_product_id', 'o.currency')
            ->get();

        foreach ($production as $row) {
            $key = (int) $row->loan_product_id.'|'.strtoupper((string) $row->currency);
            $expected[$key] = [
                'product_id' => (int) $row->loan_product_id,
                'currency' => strtoupper((string) $row->currency),
                'principal_minor' => (int) $row->principal_outstanding_minor,
                'fee_receivable_minor' => (int) $row->fee_receivable_minor,
                'default_interest_receivable_minor' => 0,
                'fee_clearing_minor' => 0,
            ];
        }

        if (Schema::hasTable('loan_schedules')) {
            $legacyCurrency = strtoupper((string) config('services.mobile_money.currency', 'UGX'));
            $legacy = DB::table('loans as l')
                ->join('loan_schedules as s', 's.loan_id', '=', 'l.id')
                ->leftJoin('credit_write_offs as w', 'w.loan_id', '=', 'l.id')
                ->whereNull('l.credit_offer_id')
                ->whereNull('l.deleted_at')
                ->whereNotIn('l.status', ['Reversed'])
                ->select('l.loan_product_id')
                ->selectRaw('ROUND(SUM(CASE WHEN w.id IS NULL THEN s.principal_outstanding ELSE 0 END)) AS principal_minor')
                ->groupBy('l.loan_product_id')
                ->get();

            foreach ($legacy as $row) {
                $key = (int) $row->loan_product_id.'|'.$legacyCurrency;
                $expected[$key] ??= [
                    'product_id' => (int) $row->loan_product_id,
                    'currency' => $legacyCurrency,
                    'principal_minor' => 0,
                    'fee_receivable_minor' => 0,
                    'default_interest_receivable_minor' => 0,
                    'fee_clearing_minor' => 0,
                ];
                $expected[$key]['principal_minor'] += (int) $row->principal_minor;
            }
        }

        $defaultInterest = DB::table('loans as l')
            ->join('credit_offers as o', 'o.id', '=', 'l.credit_offer_id')
            ->leftJoin('credit_write_offs as w', 'w.loan_id', '=', 'l.id')
            ->whereNull('l.deleted_at')
            ->whereNull('w.id')
            ->whereNotIn('l.status', ['Reversed'])
            ->select('l.loan_product_id', 'o.currency')
            ->selectRaw('SUM(CASE WHEN l.default_interest_accrued_minor > l.default_interest_paid_minor THEN l.default_interest_accrued_minor - l.default_interest_paid_minor ELSE 0 END) AS outstanding_minor')
            ->groupBy('l.loan_product_id', 'o.currency')
            ->get();

        foreach ($defaultInterest as $row) {
            $key = (int) $row->loan_product_id.'|'.strtoupper((string) $row->currency);
            $expected[$key] ??= [
                'product_id' => (int) $row->loan_product_id,
                'currency' => strtoupper((string) $row->currency),
                'principal_minor' => 0,
                'fee_receivable_minor' => 0,
                'default_interest_receivable_minor' => 0,
                'fee_clearing_minor' => 0,
            ];
            $expected[$key]['default_interest_receivable_minor'] = (int) $row->outstanding_minor;
        }

        $initialFees = DB::table('loans as l')
            ->join('credit_offers as o', 'o.id', '=', 'l.credit_offer_id')
            ->whereNull('l.deleted_at')
            ->whereNotIn('l.status', ['Reversed'])
            ->select('l.loan_product_id', 'o.currency')
            ->selectRaw('SUM(o.fees_minor) AS fees_minor')
            ->groupBy('l.loan_product_id', 'o.currency')
            ->get();

        foreach ($initialFees as $row) {
            $key = (int) $row->loan_product_id.'|'.strtoupper((string) $row->currency);
            $expected[$key] ??= [
                'product_id' => (int) $row->loan_product_id,
                'currency' => strtoupper((string) $row->currency),
                'principal_minor' => 0,
                'fee_receivable_minor' => 0,
                'default_interest_receivable_minor' => 0,
                'fee_clearing_minor' => 0,
            ];
            $expected[$key]['fee_clearing_minor'] += (int) $row->fees_minor;
        }

        if (Schema::hasTable('credit_fee_recognition_events')) {
            $recognised = DB::table('credit_fee_recognition_events as r')
                ->join('loans as l', 'l.id', '=', 'r.loan_id')
                ->select('l.loan_product_id', 'r.currency')
                ->selectRaw('SUM(r.amount_minor) AS amount_minor')
                ->groupBy('l.loan_product_id', 'r.currency')
                ->get();
            foreach ($recognised as $row) {
                $key = (int) $row->loan_product_id.'|'.strtoupper((string) $row->currency);
                $expected[$key] ??= [
                    'product_id' => (int) $row->loan_product_id,
                    'currency' => strtoupper((string) $row->currency),
                    'principal_minor' => 0,
                    'fee_receivable_minor' => 0,
                    'default_interest_receivable_minor' => 0,
                    'fee_clearing_minor' => 0,
                ];
                $expected[$key]['fee_clearing_minor'] -= (int) $row->amount_minor;
            }
        }

        if (Schema::hasTable('early_settlement_quotes')) {
            $rebates = DB::table('early_settlement_quotes as q')
                ->join('loans as l', 'l.id', '=', 'q.loan_id')
                ->where('q.status', 'settled')
                ->select('l.loan_product_id', 'q.currency')
                ->selectRaw('SUM(q.unearned_fee_rebate_minor) AS amount_minor')
                ->groupBy('l.loan_product_id', 'q.currency')
                ->get();
            foreach ($rebates as $row) {
                $key = (int) $row->loan_product_id.'|'.strtoupper((string) $row->currency);
                if (isset($expected[$key])) {
                    $expected[$key]['fee_clearing_minor'] -= (int) $row->amount_minor;
                }
            }
        }

        if (Schema::hasTable('credit_write_offs')) {
            $released = DB::table('credit_write_offs as w')
                ->join('loans as l', 'l.id', '=', 'w.loan_id')
                ->select('l.loan_product_id', 'w.currency')
                ->selectRaw('SUM(w.deferred_fee_released_minor) AS amount_minor')
                ->groupBy('l.loan_product_id', 'w.currency')
                ->get();
            foreach ($released as $row) {
                $key = (int) $row->loan_product_id.'|'.strtoupper((string) $row->currency);
                if (isset($expected[$key])) {
                    $expected[$key]['fee_clearing_minor'] -= (int) $row->amount_minor;
                }
            }

            $writeOffs = DB::table('credit_write_offs')->get();
            foreach ($writeOffs as $writeOff) {
                $ledgerOk = $writeOff->ledger_transaction_id
                    && DB::table('ledger_transactions')
                        ->where('id', $writeOff->ledger_transaction_id)
                        ->where('reference', 'credit.write_off:loan:'.$writeOff->loan_id)
                        ->exists();
                if (! $ledgerOk || ! (bool) $writeOff->legal_obligation_preserved) {
                    $findings[] = $this->alert(
                        $runId,
                        'critical',
                        'credit_write_off_evidence_invalid',
                        (string) $writeOff->loan_id,
                        'Credit write-off lacks its immutable ledger posting or legal-obligation preservation evidence.',
                        [
                            'write_off_id' => $writeOff->id,
                            'ledger_transaction_id' => $writeOff->ledger_transaction_id,
                            'legal_obligation_preserved' => (bool) $writeOff->legal_obligation_preserved,
                        ],
                    );
                }
            }
        }

        $financialAccounts = DB::table('ledger_accounts')
            ->where(function ($query) {
                $query->where('code', 'like', 'asset.loan_receivable.product_%')
                    ->orWhere('code', 'like', 'asset.credit_fee_receivable.product_%')
                    ->orWhere('code', 'like', 'asset.default_interest_receivable.product_%')
                    ->orWhere('code', 'like', 'liability.credit_fee_clearing.product_%');
            })
            ->get(['code', 'currency']);

        foreach ($financialAccounts as $account) {
            if (! preg_match('/product_(\\d+)$/', (string) $account->code, $matches)) {
                continue;
            }
            $productId = (int) $matches[1];
            $currency = strtoupper((string) $account->currency);
            $key = $productId.'|'.$currency;
            $expected[$key] ??= [
                'product_id' => $productId,
                'currency' => $currency,
                'principal_minor' => 0,
                'fee_receivable_minor' => 0,
                'default_interest_receivable_minor' => 0,
                'fee_clearing_minor' => 0,
            ];
        }

        foreach ($expected as $row) {
            $checks = [
                ['type' => 'credit_principal_subledger_mismatch', 'code' => 'asset.loan_receivable.product_'.$row['product_id'], 'normal' => 'debit', 'expected' => $row['principal_minor']],
                ['type' => 'credit_fee_receivable_subledger_mismatch', 'code' => 'asset.credit_fee_receivable.product_'.$row['product_id'], 'normal' => 'debit', 'expected' => $row['fee_receivable_minor']],
                ['type' => 'default_interest_receivable_subledger_mismatch', 'code' => 'asset.default_interest_receivable.product_'.$row['product_id'], 'normal' => 'debit', 'expected' => $row['default_interest_receivable_minor']],
                ['type' => 'credit_fee_clearing_subledger_mismatch', 'code' => 'liability.credit_fee_clearing.product_'.$row['product_id'], 'normal' => 'credit', 'expected' => max(0, $row['fee_clearing_minor'])],
            ];

            foreach ($checks as $check) {
                $actual = $this->ledgerAccountBalance($check['code'], $row['currency'], $check['normal']);
                if ($actual === (int) $check['expected']) {
                    continue;
                }
                $findings[] = $this->alert(
                    $runId,
                    'critical',
                    $check['type'],
                    $check['code'].'|'.$row['currency'],
                    'General-ledger balance does not reconcile to the governed credit subledger.',
                    [
                        'product_id' => $row['product_id'],
                        'currency' => $row['currency'],
                        'account_code' => $check['code'],
                        'expected_minor' => (int) $check['expected'],
                        'actual_minor' => $actual,
                        'difference_minor' => $actual - (int) $check['expected'],
                    ],
                );
            }
        }
    }

    private function ledgerAccountBalance(string $code, string $currency, string $normalDirection): int
    {
        $totals = DB::table('ledger_entries as e')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.ledger_account_id')
            ->where('a.code', $code)
            ->where('a.currency', strtoupper($currency))
            ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'debit' THEN e.amount_minor ELSE 0 END), 0) AS debits")
            ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'credit' THEN e.amount_minor ELSE 0 END), 0) AS credits")
            ->first();

        $debits = (int) ($totals->debits ?? 0);
        $credits = (int) ($totals->credits ?? 0);

        return $normalDirection === 'debit' ? $debits - $credits : $credits - $debits;
    }

    private function scanImpairmentCoverage(int $runId, array &$findings): void
    {
        if (! Schema::hasTable('loan_impairment_assessments')) {
            return;
        }

        $productionExposure = DB::table('credit_repayment_schedule_items')
            ->select('loan_id')
            ->selectRaw('SUM(principal_outstanding_minor) AS exposure_minor')
            ->groupBy('loan_id')
            ->pluck('exposure_minor', 'loan_id');

        $legacyExposure = Schema::hasTable('loan_schedules')
            ? DB::table('loan_schedules')
                ->select('loan_id')
                ->selectRaw('ROUND(SUM(principal_outstanding)) AS exposure_minor')
                ->groupBy('loan_id')
                ->pluck('exposure_minor', 'loan_id')
            : collect();

        $assessments = DB::table('loan_impairment_assessments')
            ->orderBy('loan_id')
            ->orderByDesc('as_of_date')
            ->orderByDesc('id')
            ->get();
        $latestByLoan = [];
        foreach ($assessments as $assessment) {
            $latestByLoan[$assessment->loan_id] ??= $assessment;
        }

        $writeOffs = Schema::hasTable('credit_write_offs')
            ? DB::table('credit_write_offs')->get()->keyBy('loan_id')
            : collect();
        $maxAgeDays = max(1, (int) config('opfin.accounting.impairment_max_age_days', 31));
        $allowanceExpected = [];

        $loans = DB::table('loans')->whereNull('deleted_at')->get(['id', 'loan_product_id', 'credit_offer_id', 'status']);
        foreach ($loans as $loan) {
            $legalExposure = $loan->credit_offer_id
                ? (int) ($productionExposure[$loan->id] ?? 0)
                : (int) ($legacyExposure[$loan->id] ?? 0);
            $writeOff = $writeOffs->get($loan->id);
            $assessment = $latestByLoan[$loan->id] ?? null;

            if ($writeOff) {
                if (! $assessment
                    || (int) $writeOff->impairment_assessment_id !== (int) $assessment->id
                    || (string) $assessment->stage !== 'stage_3'
                    || (int) $assessment->gross_exposure_minor !== (int) $writeOff->principal_written_off_minor
                    || (int) $assessment->expected_credit_loss_minor !== (int) $writeOff->principal_written_off_minor) {
                    $findings[] = $this->alert($runId, 'critical', 'credit_write_off_impairment_basis_invalid', (string) $loan->id,
                        'Written-off credit is not supported by its immutable 100% stage-3 impairment basis.', [
                            'loan_id' => $loan->id,
                            'write_off_id' => $writeOff->id,
                            'impairment_assessment_id' => $assessment?->id,
                        ]);
                }
                continue;
            }

            if ($legalExposure > 0 && ! $assessment) {
                $findings[] = $this->alert($runId, 'high', 'credit_impairment_assessment_missing', (string) $loan->id,
                    'Outstanding credit exposure has no approved impairment assessment.', [
                        'loan_id' => $loan->id,
                        'current_principal_exposure_minor' => $legalExposure,
                    ]);
                continue;
            }

            if (! $assessment) {
                continue;
            }

            $ageDays = \Carbon\Carbon::parse($assessment->as_of_date)->startOfDay()->diffInDays(now()->startOfDay());
            if ($legalExposure > 0 && $ageDays > $maxAgeDays) {
                $findings[] = $this->alert($runId, 'high', 'credit_impairment_assessment_stale', (string) $loan->id,
                    'Outstanding credit exposure has a stale impairment assessment.', [
                        'loan_id' => $loan->id,
                        'as_of_date' => $assessment->as_of_date,
                        'age_days' => $ageDays,
                        'maximum_age_days' => $maxAgeDays,
                    ]);
            }

            if ((int) $assessment->expected_credit_loss_minor > $legalExposure) {
                $findings[] = $this->alert($runId, 'critical', 'credit_impairment_exceeds_exposure', (string) $loan->id,
                    'Expected credit loss exceeds current recorded principal exposure.', [
                        'loan_id' => $loan->id,
                        'expected_credit_loss_minor' => (int) $assessment->expected_credit_loss_minor,
                        'current_principal_exposure_minor' => $legalExposure,
                    ]);
            }

            if (in_array((string) $loan->status, ['Cleared', 'Reversed'], true)
                && (int) $assessment->expected_credit_loss_minor !== 0) {
                $findings[] = $this->alert($runId, 'high', 'credit_impairment_not_released', (string) $loan->id,
                    'Cleared or reversed loan retains a non-zero expected credit-loss allowance.', [
                        'loan_id' => $loan->id,
                        'loan_status' => $loan->status,
                        'expected_credit_loss_minor' => (int) $assessment->expected_credit_loss_minor,
                    ]);
            }

            $key = (int) $loan->loan_product_id.'|'.strtoupper((string) $assessment->currency);
            $allowanceExpected[$key] ??= ['product_id' => (int) $loan->loan_product_id, 'currency' => strtoupper((string) $assessment->currency), 'expected_minor' => 0];
            $allowanceExpected[$key]['expected_minor'] += (int) $assessment->expected_credit_loss_minor;
        }

        $accounts = DB::table('ledger_accounts')->where('code', 'like', 'contra_asset.credit_loss_allowance.product_%')->get(['code', 'currency']);
        foreach ($accounts as $account) {
            if (! preg_match('/product_(\\d+)$/', (string) $account->code, $matches)) {
                continue;
            }
            $key = (int) $matches[1].'|'.strtoupper((string) $account->currency);
            $allowanceExpected[$key] ??= ['product_id' => (int) $matches[1], 'currency' => strtoupper((string) $account->currency), 'expected_minor' => 0];
        }

        foreach ($allowanceExpected as $row) {
            $code = 'contra_asset.credit_loss_allowance.product_'.$row['product_id'];
            $actual = $this->ledgerAccountBalance($code, $row['currency'], 'credit');
            if ($actual !== (int) $row['expected_minor']) {
                $findings[] = $this->alert($runId, 'critical', 'credit_loss_allowance_ledger_mismatch', $code.'|'.$row['currency'],
                    'Credit-loss allowance does not reconcile to latest approved impairment assessments.', [
                        'expected_minor' => (int) $row['expected_minor'],
                        'actual_minor' => $actual,
                        'difference_minor' => $actual - (int) $row['expected_minor'],
                    ]);
            }
        }
    }

    private function scanProviderSettlementEvidence(int $runId, array &$findings): void
    {
        if (! Schema::hasTable('provider_settlement_batches') || ! Schema::hasTable('reconciliation_runs')) {
            return;
        }

        $runs = DB::table('reconciliation_runs')->where('status', 'completed')->get(['id', 'provider', 'business_date', 'summary']);
        foreach ($runs as $run) {
            $summary = (array) json_decode((string) ($run->summary ?? '{}'), true);
            if ((int) ($summary['exception_count'] ?? 0) !== 0 || (int) ($summary['pending_provider_match_count'] ?? 0) !== 0) {
                continue;
            }

            $movements = DB::table('reconciliation_items as i')
                ->join('mobile_money_transactions as m', 'm.id', '=', 'i.mobile_money_transaction_id')
                ->where('i.reconciliation_run_id', $run->id)
                ->where('i.status', 'matched')
                ->where('m.status', MobileMoneyTransaction::STATUS_SUCCESSFUL)
                ->where('m.statement_reconciliation_status', MobileMoneyTransaction::STATEMENT_MATCHED)
                ->whereIn('m.accounting_status', [MobileMoneyTransaction::ACCOUNTING_POSTED, MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED])
                ->select('m.id', 'm.direction', 'm.currency', 'm.amount_minor')
                ->get()
                ->groupBy(fn ($row) => strtoupper((string) $row->currency));

            foreach ($movements as $currency => $currencyMovements) {
                $collections = (int) $currencyMovements->where('direction', MobileMoneyTransaction::DIRECTION_COLLECTION)->sum('amount_minor');
                $disbursements = (int) $currencyMovements->where('direction', MobileMoneyTransaction::DIRECTION_DISBURSEMENT)->sum('amount_minor');
                if ($collections === 0 && $disbursements === 0) {
                    continue;
                }

                $batch = DB::table('provider_settlement_batches')->where('reconciliation_run_id', $run->id)->where('currency', $currency)->first();
                if (! $batch) {
                    $findings[] = $this->alert($runId, 'high', 'provider_settlement_missing', $run->id.'|'.$currency,
                        'Completed exception-free provider reconciliation has successful movements but no evidenced bank settlement.', [
                            'reconciliation_run_id' => $run->id,
                            'provider' => $run->provider,
                            'currency' => $currency,
                            'collections_minor' => $collections,
                            'disbursements_minor' => $disbursements,
                        ]);
                    continue;
                }

                $expectedNet = $collections - $disbursements - (int) $batch->provider_fee_minor;
                $evidence = json_decode((string) $batch->evidence, true) ?? [];
                $evidenceHashValid = hash('sha256', json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === $batch->evidence_hash;
                $ledgerExists = $batch->ledger_transaction_id
                    && DB::table('ledger_transactions')->where('id', $batch->ledger_transaction_id)->where('reference', 'provider.settlement:'.$batch->id)->exists();

                if (
                    (int) $batch->collections_minor !== $collections
                    || (int) $batch->disbursements_minor !== $disbursements
                    || (int) $batch->bank_net_settlement_minor !== $expectedNet
                    || ! $evidenceHashValid
                    || ! $ledgerExists
                ) {
                    $findings[] = $this->alert($runId, 'critical', 'provider_settlement_evidence_mismatch', (string) $batch->id,
                        'Provider settlement does not reconcile to matched movements, evidence hash and immutable ledger posting.', [
                            'settlement_batch_id' => $batch->id,
                            'expected_bank_net_settlement_minor' => $expectedNet,
                            'recorded_bank_net_settlement_minor' => (int) $batch->bank_net_settlement_minor,
                            'evidence_hash_valid' => $evidenceHashValid,
                            'ledger_posting_present' => $ledgerExists,
                        ]);
                }
            }
        }
    }

    private function scanRevenueTaxAndPartnerCompleteness(int $runId, array &$findings): void
    {
        if (Schema::hasTable('revenue_events')) {
            $missingRevenueAccounting = DB::table('revenue_events')->where('status', 'settled')->where('accounting_status', '!=', 'posted')->count();
            if ($missingRevenueAccounting > 0) {
                $findings[] = $this->alert($runId, 'critical', 'revenue_accounting_incomplete', null,
                    'Settled revenue events exist without completed accounting.', ['count' => $missingRevenueAccounting]);
            }

            $missingRevenueStatement = DB::table('revenue_events')->where('status', 'settled')->where('statement_reconciliation_status', '!=', 'matched')->count();
            if ($missingRevenueStatement > 0) {
                $findings[] = $this->alert($runId, 'high', 'revenue_statement_reconciliation_incomplete', null,
                    'Settled revenue events are not fully supported by matched provider statements.', ['count' => $missingRevenueStatement]);
            }
        }

        if (Schema::hasTable('tax_events')) {
            $missingTaxLedger = DB::table('tax_events')->where('tax_amount_minor', '>', 0)->whereNull('ledger_transaction_id')->count();
            if ($missingTaxLedger > 0) {
                $findings[] = $this->alert($runId, 'critical', 'tax_accounting_incomplete', null,
                    'Tax events with non-zero tax exist without immutable ledger postings.', ['count' => $missingTaxLedger]);
            }
        }

        if (Schema::hasTable('savings_movements')) {
            $exceptions = DB::table('savings_movements')->where('status', 'reversal_exception')->count();
            if ($exceptions > 0) {
                $findings[] = $this->alert($runId, 'high', 'savings_reversal_exception', null,
                    'Savings movements contain unresolved reversal exceptions.', ['count' => $exceptions]);
            }
        }

        if (Schema::hasTable('protection_premium_payments')) {
            $exceptions = DB::table('protection_premium_payments')->where('status', 'reversal_exception')->count();
            if ($exceptions > 0) {
                $findings[] = $this->alert($runId, 'high', 'protection_reversal_exception', null,
                    'Protection premium movements contain unresolved reversal exceptions.', ['count' => $exceptions]);
            }
        }
    }

    private function resolveAbsentAlerts(int $runId, array $findings, string $scope): void
    {
        if ($scope !== 'platform' || ! Schema::hasTable('financial_integrity_alerts')) {
            return;
        }

        $observed = collect($findings)->mapWithKeys(fn (array $finding) => [
            ($finding['type'] ?? '').'|'.($finding['reference'] ?? '') => true,
        ]);

        $open = DB::table('financial_integrity_alerts')->where('status', 'open')->get(['id', 'type', 'reference']);
        foreach ($open as $alert) {
            $key = (string) $alert->type.'|'.(string) ($alert->reference ?? '');
            if ($observed->has($key)) {
                continue;
            }
            DB::table('financial_integrity_alerts')->where('id', $alert->id)->update([
                'status' => 'resolved',
                'resolution_evidence' => json_encode([
                    'resolved_by_integrity_run_id' => $runId,
                    'reason' => 'The finding was not reproduced by the subsequent complete platform integrity audit.',
                ]),
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function scanAssetEconomics(int $runId, array &$findings): void
    {
        if (! Schema::hasTable('asset_finance_requests')) {
            return;
        }

        $records = DB::table('asset_finance_requests')->get(['reference', 'asset_price_minor', 'deposit_minor', 'status', 'decision_evidence']);
        foreach ($records as $record) {
            $assetPrice = (int) $record->asset_price_minor;
            $deposit = (int) $record->deposit_minor;
            if ($assetPrice <= 0 || $deposit < 0 || $deposit >= $assetPrice) {
                $findings[] = $this->alert($runId, 'critical', 'asset_finance_invalid_economics', $record->reference,
                    'Asset-finance request has an invalid asset-price/deposit relationship.', [
                        'asset_price_minor' => $assetPrice,
                        'deposit_minor' => $deposit,
                    ]);

                continue;
            }

            if (in_array($record->status, ['approved', 'deposit_settled'], true)) {
                $evidence = (array) json_decode((string) ($record->decision_evidence ?? '{}'), true);
                $approved = isset($evidence['approved_amount_minor']) ? (int) $evidence['approved_amount_minor'] : 0;
                $maximum = $assetPrice - $deposit;
                if ($approved <= 0 || $approved > $maximum) {
                    $findings[] = $this->alert($runId, 'critical', 'asset_finance_approval_mismatch', $record->reference,
                        'Approved asset-finance amount does not reconcile to asset price less deposit.', [
                            'approved_amount_minor' => $approved,
                            'maximum_finance_minor' => $maximum,
                            'asset_price_minor' => $assetPrice,
                            'deposit_minor' => $deposit,
                        ]);
                }
            }
        }
    }

    private function scanLongRangePaymentReconciliation(int $runId, array &$findings): void
    {
        if (! Schema::hasTable('financial_action_intents')) {
            return;
        }

        $settled = DB::table('financial_action_intents')->where('status', 'settled')->get();
        foreach ($settled as $intent) {
            $payment = MobileMoneyTransaction::query()->where('internal_reference', $intent->reference)->latest('id')->first();
            if (! $payment || $payment->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
                $findings[] = $this->alert($runId, 'critical', 'long_range_false_settlement', $intent->reference,
                    'Long-range financial intent is marked settled without a currently successful provider collection.', [
                        'intent_id' => $intent->id,
                        'source_type' => $intent->source_type,
                        'source_id' => $intent->source_id,
                        'provider_status' => $payment?->status,
                        'provider_transaction_id' => $payment?->id,
                    ]);
            }
        }
    }

    private function alert(int $runId, string $severity, string $type, ?string $reference, string $description, array $evidence): array
    {
        $query = DB::table('financial_integrity_alerts')->where('status', 'open')->where('type', $type);
        $reference === null ? $query->whereNull('reference') : $query->where('reference', $reference);
        $existing = $query->first();

        if ($existing) {
            DB::table('financial_integrity_alerts')->where('id', $existing->id)->update([
                'run_id' => $runId,
                'severity' => $severity,
                'description' => $description,
                'evidence' => json_encode($evidence),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('financial_integrity_alerts')->insert([
                'run_id' => $runId,
                'severity' => $severity,
                'type' => $type,
                'reference' => $reference,
                'description' => $description,
                'status' => 'open',
                'evidence' => json_encode($evidence),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('autopilot_work_items')) {
            DB::table('autopilot_work_items')->updateOrInsert([
                'domain' => 'financial_integrity',
                'type' => $type,
                'subject_type' => 'financial_integrity_alert',
                'subject_reference' => $reference ?? $type,
                'status' => 'open',
            ], [
                'severity' => $severity,
                'title' => 'Financial integrity exception',
                'description' => $description,
                'recommended_action' => 'Investigate source records and provider evidence. Use append-only corrections; never create a balancing entry solely to make the exception disappear.',
                'confidence' => 1,
                'automation_tier' => 'A5',
                'requires_human' => true,
                'context' => json_encode($evidence),
                'due_at' => now()->addHour(),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        return compact('severity', 'type', 'reference', 'description', 'evidence');
    }
}
