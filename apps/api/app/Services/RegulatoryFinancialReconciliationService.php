<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegulatoryFinancialReconciliationService
{
    public function assess(Carbon $start, Carbon $end): array
    {
        $terminal = MobileMoneyTransaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [MobileMoneyTransaction::STATUS_SUCCESSFUL, MobileMoneyTransaction::STATUS_REVERSED]);

        $accountingMissing = (clone $terminal)
            ->whereNotIn('accounting_status', [
                MobileMoneyTransaction::ACCOUNTING_POSTED,
                MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED,
            ])
            ->count();

        $statementMissing = (clone $terminal)
            ->where('statement_reconciliation_status', '!=', MobileMoneyTransaction::STATEMENT_MATCHED)
            ->count();

        $ledgerImbalances = DB::table('ledger_transactions as t')
            ->leftJoin('ledger_entries as e', 'e.ledger_transaction_id', '=', 't.id')
            ->whereBetween('t.posted_at', [$start, $end])
            ->groupBy('t.id')
            ->havingRaw("SUM(CASE WHEN e.direction = 'debit' THEN e.amount_minor ELSE 0 END) <> SUM(CASE WHEN e.direction = 'credit' THEN e.amount_minor ELSE 0 END)")
            ->count();

        $scheduleMismatches = DB::table('loans as l')
            ->join('credit_offers as o', 'o.id', '=', 'l.credit_offer_id')
            ->leftJoin('credit_repayment_schedule_items as s', 's.loan_id', '=', 'l.id')
            ->whereBetween('l.created_at', [$start, $end])
            ->whereNotNull('l.credit_offer_id')
            ->groupBy('l.id', 'o.total_repayment_minor')
            ->havingRaw('COALESCE(SUM(s.total_due_minor), 0) <> o.total_repayment_minor')
            ->count();

        $revenueAccountingMissing = DB::table('revenue_events')
            ->whereBetween('occurred_at', [$start, $end])
            ->where('accounting_status', '!=', 'posted')
            ->count();

        $settledRevenueStatementMissing = DB::table('revenue_events')
            ->whereBetween('occurred_at', [$start, $end])
            ->where('status', 'settled')
            ->where('statement_reconciliation_status', '!=', 'matched')
            ->count();

        $savingsReversalExceptions = DB::table('savings_movements')
            ->whereBetween('updated_at', [$start, $end])
            ->where('status', 'reversal_exception')
            ->count();

        $protectionReversalExceptions = DB::table('protection_premium_payments')
            ->whereBetween('updated_at', [$start, $end])
            ->where('status', 'reversal_exception')
            ->count();

        $checks = [
            'provider_final_movements_missing_accounting' => $accountingMissing,
            'provider_final_movements_missing_statement_match' => $statementMissing,
            'ledger_imbalances' => $ledgerImbalances,
            'loan_offer_schedule_mismatches' => $scheduleMismatches,
            'revenue_events_missing_accounting' => $revenueAccountingMissing,
            'settled_revenue_missing_statement_match' => $settledRevenueStatementMissing,
            'savings_reversal_exceptions' => $savingsReversalExceptions,
            'protection_reversal_exceptions' => $protectionReversalExceptions,
        ];

        return [
            'passed' => collect($checks)->every(fn ($count) => (int) $count === 0),
            'checks' => $checks,
            'evidence_hash' => hash('sha256', json_encode($checks, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'period' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
        ];
    }
}
