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
            $paymentExceptions = MobileMoneyTransaction::query()
                ->where(function ($query) {
                    $query->where('reconciliation_status', MobileMoneyTransaction::RECONCILIATION_EXCEPTION)
                        ->orWhere(function ($query) {
                            $query->whereIn('status', [
                                MobileMoneyTransaction::STATUS_SUCCESSFUL,
                                MobileMoneyTransaction::STATUS_FAILED,
                                MobileMoneyTransaction::STATUS_REVERSED,
                            ])->where('reconciliation_status', '!=', MobileMoneyTransaction::RECONCILIATION_MATCHED);
                        })
                        ->orWhere(function ($query) {
                            $query->where('reconciliation_status', MobileMoneyTransaction::RECONCILIATION_MATCHED)
                                ->whereNull('provider_reconciled_at');
                        });
                })->count();

            if ($paymentExceptions > 0) {
                $findings[] = $this->alert($runId, 'high', 'payment_reconciliation_exception', null,
                    'Terminal or explicitly excepted payment records are not fully supported by independent provider reconciliation evidence.', ['count' => $paymentExceptions]);
            }

            $falseMatches = MobileMoneyTransaction::query()
                ->where('reconciliation_status', MobileMoneyTransaction::RECONCILIATION_MATCHED)
                ->whereNull('provider_reconciled_at')
                ->count();
            if ($falseMatches > 0) {
                $findings[] = $this->alert($runId, 'critical', 'payment_false_reconciliation_match', null,
                    'Payment records are marked reconciled without independent provider-reconciliation evidence.', ['count' => $falseMatches]);
            }

            $creditAccountingExceptions = MobileMoneyTransaction::query()
                ->where(function ($query) {
                    $query->whereNotNull('credit_offer_id')->orWhereNotNull('loan_id');
                })
                ->whereIn('status', [
                    MobileMoneyTransaction::STATUS_SUCCESSFUL,
                    MobileMoneyTransaction::STATUS_FAILED,
                    MobileMoneyTransaction::STATUS_REVERSED,
                ])
                ->where('product_accounting_status', '!=', MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED)
                ->count();
            if ($creditAccountingExceptions > 0) {
                $findings[] = $this->alert($runId, 'critical', 'credit_product_accounting_incomplete', null,
                    'Terminal credit money movements have not completed their required product-accounting transition.', ['count' => $creditAccountingExceptions]);
            }

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
            'funds_integrity_rule' => 'Every governed financial event must have exactly the expected economic posting. Any missing posting, imbalance, false settlement, funding mismatch, reward-ledger mismatch, duplicate provider reference, currency mismatch, invalid entry shape, or unreconciled successful/reversed payment is an exception and cannot be silently written off or auto-balanced away. Account retirement blocks new postings but does not invalidate immutable historical entries.',
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
            $this->expect($expected, 'income.credit_fees.product_'.$loanProductId, $reversal ? 'debit' : 'credit', $fees);
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
            ->whereNull('l.deleted_at')
            ->whereNotIn('l.status', ['Reversed'])
            ->select('l.loan_product_id', 'o.currency')
            ->selectRaw('SUM(s.principal_outstanding_minor) AS principal_outstanding_minor')
            ->selectRaw('SUM(s.interest_minor - s.interest_outstanding_minor) AS interest_realised_minor')
            ->selectRaw('SUM(s.fees_outstanding_minor) AS financed_fee_outstanding_minor')
            ->selectRaw('SUM(s.fees_minor - s.fees_outstanding_minor) AS financed_fee_realised_minor')
            ->groupBy('l.loan_product_id', 'o.currency')
            ->get();

        foreach ($production as $row) {
            $key = (int) $row->loan_product_id.'|'.strtoupper((string) $row->currency);
            $expected[$key] = [
                'product_id' => (int) $row->loan_product_id,
                'currency' => strtoupper((string) $row->currency),
                'principal_outstanding_minor' => (int) $row->principal_outstanding_minor,
                'interest_realised_minor' => (int) $row->interest_realised_minor,
                'financed_fee_outstanding_minor' => (int) $row->financed_fee_outstanding_minor,
                'fee_income_minor' => (int) $row->financed_fee_realised_minor,
            ];
        }

        $deductedFees = DB::table('loans as l')
            ->join('credit_offers as o', 'o.id', '=', 'l.credit_offer_id')
            ->whereNull('l.deleted_at')
            ->whereNotIn('l.status', ['Reversed'])
            ->where('o.fee_treatment', 'deducted')
            ->select('l.loan_product_id', 'o.currency')
            ->selectRaw('SUM(o.fees_minor) AS deducted_fee_income_minor')
            ->groupBy('l.loan_product_id', 'o.currency')
            ->get();

        foreach ($deductedFees as $row) {
            $key = (int) $row->loan_product_id.'|'.strtoupper((string) $row->currency);
            $expected[$key] ??= [
                'product_id' => (int) $row->loan_product_id,
                'currency' => strtoupper((string) $row->currency),
                'principal_outstanding_minor' => 0,
                'interest_realised_minor' => 0,
                'financed_fee_outstanding_minor' => 0,
                'fee_income_minor' => 0,
            ];
            $expected[$key]['fee_income_minor'] += (int) $row->deducted_fee_income_minor;
        }

        if (Schema::hasTable('loan_schedules')) {
            $legacyCurrency = strtoupper((string) config('services.mobile_money.currency', 'UGX'));
            $legacy = DB::table('loans as l')
                ->join('loan_schedules as s', 's.loan_id', '=', 'l.id')
                ->whereNull('l.credit_offer_id')
                ->whereNull('l.deleted_at')
                ->whereNotIn('l.status', ['Reversed'])
                ->select('l.loan_product_id')
                ->selectRaw('ROUND(SUM(s.principal_outstanding)) AS principal_outstanding_minor')
                ->selectRaw('ROUND(SUM(s.interest - s.interest_outstanding)) AS interest_realised_minor')
                ->groupBy('l.loan_product_id')
                ->get();

            foreach ($legacy as $row) {
                $key = (int) $row->loan_product_id.'|'.$legacyCurrency;
                $expected[$key] ??= [
                    'product_id' => (int) $row->loan_product_id,
                    'currency' => $legacyCurrency,
                    'principal_outstanding_minor' => 0,
                    'interest_realised_minor' => 0,
                    'financed_fee_outstanding_minor' => 0,
                    'fee_income_minor' => 0,
                ];
                $expected[$key]['principal_outstanding_minor'] += (int) $row->principal_outstanding_minor;
                $expected[$key]['interest_realised_minor'] += (int) $row->interest_realised_minor;
            }
        }

        $financialAccounts = DB::table('ledger_accounts')
            ->where(function ($query) {
                $query->where('code', 'like', 'asset.loan_receivable.product_%')
                    ->orWhere('code', 'like', 'asset.credit_fee_receivable.product_%')
                    ->orWhere('code', 'like', 'liability.credit_fee_clearing.product_%')
                    ->orWhere('code', 'like', 'income.interest.product_%')
                    ->orWhere('code', 'like', 'income.credit_fees.product_%');
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
                'principal_outstanding_minor' => 0,
                'interest_realised_minor' => 0,
                'financed_fee_outstanding_minor' => 0,
                'fee_income_minor' => 0,
            ];
        }

        foreach ($expected as $row) {
            $productId = $row['product_id'];
            $currency = $row['currency'];
            $checks = [
                [
                    'type' => 'credit_principal_subledger_mismatch',
                    'code' => 'asset.loan_receivable.product_'.$productId,
                    'normal' => 'debit',
                    'expected' => $row['principal_outstanding_minor'],
                    'description' => 'Loan-principal general-ledger balance does not reconcile to the loan subledger.',
                ],
                [
                    'type' => 'credit_fee_receivable_subledger_mismatch',
                    'code' => 'asset.credit_fee_receivable.product_'.$productId,
                    'normal' => 'debit',
                    'expected' => $row['financed_fee_outstanding_minor'],
                    'description' => 'Financed-fee receivable does not reconcile to outstanding financed fees.',
                ],
                [
                    'type' => 'credit_fee_clearing_subledger_mismatch',
                    'code' => 'liability.credit_fee_clearing.product_'.$productId,
                    'normal' => 'credit',
                    'expected' => $row['financed_fee_outstanding_minor'],
                    'description' => 'Deferred financed-fee clearing does not reconcile to outstanding financed fees.',
                ],
                [
                    'type' => 'interest_income_subledger_mismatch',
                    'code' => 'income.interest.product_'.$productId,
                    'normal' => 'credit',
                    'expected' => $row['interest_realised_minor'],
                    'description' => 'Realised interest income does not reconcile to customer repayment allocations.',
                ],
                [
                    'type' => 'credit_fee_income_subledger_mismatch',
                    'code' => 'income.credit_fees.product_'.$productId,
                    'normal' => 'credit',
                    'expected' => $row['fee_income_minor'],
                    'description' => 'Realised credit-fee income does not reconcile to deducted and repaid financed fees.',
                ],
            ];

            foreach ($checks as $check) {
                $actual = $this->ledgerAccountBalance($check['code'], $currency, $check['normal']);
                if ($actual === (int) $check['expected']) {
                    continue;
                }

                $findings[] = $this->alert(
                    $runId,
                    'critical',
                    $check['type'],
                    $check['code'].'|'.$currency,
                    $check['description'],
                    [
                        'product_id' => $productId,
                        'currency' => $currency,
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
        if (
            ! Schema::hasTable('loan_impairment_assessments')
            || ! Schema::hasTable('loans')
            || ! Schema::hasTable('ledger_accounts')
            || ! Schema::hasTable('ledger_entries')
        ) {
            return;
        }

        $productionExposure = Schema::hasTable('credit_repayment_schedule_items')
            ? DB::table('credit_repayment_schedule_items')
                ->select('loan_id')
                ->selectRaw('SUM(principal_outstanding_minor + fees_outstanding_minor) AS exposure_minor')
                ->groupBy('loan_id')
                ->pluck('exposure_minor', 'loan_id')
            : collect();

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

        $maxAgeDays = max(1, (int) config('opfin.accounting.impairment_max_age_days', 31));
        $allowanceExpected = [];
        $loans = DB::table('loans')
            ->whereNull('deleted_at')
            ->get(['id', 'loan_product_id', 'credit_offer_id', 'status']);

        foreach ($loans as $loan) {
            $currentExposure = $loan->credit_offer_id
                ? (int) ($productionExposure[$loan->id] ?? 0)
                : (int) ($legacyExposure[$loan->id] ?? 0);
            $assessment = $latestByLoan[$loan->id] ?? null;

            if ($currentExposure > 0 && ! $assessment) {
                $findings[] = $this->alert(
                    $runId,
                    'high',
                    'credit_impairment_assessment_missing',
                    (string) $loan->id,
                    'Outstanding credit exposure has no approved impairment assessment.',
                    [
                        'loan_id' => $loan->id,
                        'loan_product_id' => $loan->loan_product_id,
                        'current_recorded_exposure_minor' => $currentExposure,
                    ],
                );
                continue;
            }

            if (! $assessment) {
                continue;
            }

            $assessmentAgeDays = now()->startOfDay()->diffInDays(
                \Carbon\Carbon::parse($assessment->as_of_date)->startOfDay(),
                false,
            );
            if ($currentExposure > 0 && $assessmentAgeDays < -$maxAgeDays) {
                $findings[] = $this->alert(
                    $runId,
                    'high',
                    'credit_impairment_assessment_stale',
                    (string) $loan->id,
                    'Outstanding credit exposure has a stale impairment assessment.',
                    [
                        'loan_id' => $loan->id,
                        'as_of_date' => $assessment->as_of_date,
                        'maximum_age_days' => $maxAgeDays,
                        'current_recorded_exposure_minor' => $currentExposure,
                    ],
                );
            }

            if ((int) $assessment->gross_exposure_minor !== $currentExposure) {
                $findings[] = $this->alert(
                    $runId,
                    'high',
                    'credit_impairment_exposure_changed',
                    (string) $loan->id,
                    'Recorded credit exposure changed after the latest impairment assessment; reassessment is required.',
                    [
                        'loan_id' => $loan->id,
                        'assessment_exposure_minor' => (int) $assessment->gross_exposure_minor,
                        'current_recorded_exposure_minor' => $currentExposure,
                        'as_of_date' => $assessment->as_of_date,
                    ],
                );
            }

            if ((int) $assessment->expected_credit_loss_minor > $currentExposure) {
                $findings[] = $this->alert(
                    $runId,
                    'critical',
                    'credit_impairment_exceeds_exposure',
                    (string) $loan->id,
                    'Expected credit-loss allowance exceeds current recorded credit exposure.',
                    [
                        'loan_id' => $loan->id,
                        'expected_credit_loss_minor' => (int) $assessment->expected_credit_loss_minor,
                        'current_recorded_exposure_minor' => $currentExposure,
                    ],
                );
            }

            if (in_array((string) $loan->status, ['Cleared', 'Reversed'], true)
                && (int) $assessment->expected_credit_loss_minor !== 0) {
                $findings[] = $this->alert(
                    $runId,
                    'high',
                    'credit_impairment_not_released',
                    (string) $loan->id,
                    'Cleared or reversed loan retains a non-zero expected credit-loss allowance.',
                    [
                        'loan_id' => $loan->id,
                        'loan_status' => $loan->status,
                        'expected_credit_loss_minor' => (int) $assessment->expected_credit_loss_minor,
                    ],
                );
            }

            $key = (int) $loan->loan_product_id.'|'.strtoupper((string) $assessment->currency);
            $allowanceExpected[$key] ??= [
                'product_id' => (int) $loan->loan_product_id,
                'currency' => strtoupper((string) $assessment->currency),
                'expected_minor' => 0,
            ];
            $allowanceExpected[$key]['expected_minor'] += (int) $assessment->expected_credit_loss_minor;
        }

        $allowanceAccounts = DB::table('ledger_accounts')
            ->where('code', 'like', 'contra_asset.credit_loss_allowance.product_%')
            ->get(['code', 'currency']);
        foreach ($allowanceAccounts as $account) {
            if (! preg_match('/product_(\\d+)$/', (string) $account->code, $matches)) {
                continue;
            }
            $productId = (int) $matches[1];
            $currency = strtoupper((string) $account->currency);
            $key = $productId.'|'.$currency;
            $allowanceExpected[$key] ??= [
                'product_id' => $productId,
                'currency' => $currency,
                'expected_minor' => 0,
            ];
        }

        foreach ($allowanceExpected as $row) {
            $code = 'contra_asset.credit_loss_allowance.product_'.$row['product_id'];
            $actual = $this->ledgerAccountBalance($code, $row['currency'], 'credit');
            if ($actual === (int) $row['expected_minor']) {
                continue;
            }

            $findings[] = $this->alert(
                $runId,
                'critical',
                'credit_loss_allowance_ledger_mismatch',
                $code.'|'.$row['currency'],
                'Credit-loss allowance general-ledger balance does not reconcile to latest approved loan impairment assessments.',
                [
                    'product_id' => $row['product_id'],
                    'currency' => $row['currency'],
                    'expected_minor' => (int) $row['expected_minor'],
                    'actual_minor' => $actual,
                    'difference_minor' => $actual - (int) $row['expected_minor'],
                ],
            );
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

    private function resolveAbsentAlerts(int $runId, array $findings, string $scope): void
    {
        if ($scope !== 'platform' || ! Schema::hasTable('financial_integrity_alerts')) {
            return;
        }

        $observed = collect($findings)
            ->mapWithKeys(fn (array $finding) => [
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
