<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\FinancialSpaceRolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialSpaceAdvisoryController extends Controller
{
    public function __invoke(Request $request, FinancialSpace $space): JsonResponse
    {
        abort_unless($space->status === 'active' && $space->deleted_at === null, 403);
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')->value('role');
        abort_unless(
            is_string($role) && FinancialSpaceRolePolicy::canViewFinancialRecords($space->type, $role),
            403
        );

        $cashbook = DB::table('financial_space_transactions')
            ->where('financial_space_id', $space->id)
            ->where('currency', $space->currency);
        $matchedDebits = (int) (clone $cashbook)
            ->where('direction', 'debit')
            ->where('reconciliation_status', 'matched')
            ->sum('amount_minor');
        $matchedCredits = (int) (clone $cashbook)
            ->where('direction', 'credit')
            ->where('reconciliation_status', 'matched')
            ->sum('amount_minor');
        $unreconciledCount = (int) (clone $cashbook)
            ->whereNotIn('reconciliation_status', ['matched'])
            ->count();

        $openDebt = DB::table('financial_obligations')
            ->where('financial_space_id', $space->id)
            ->where('currency', $space->currency)
            ->where('direction', 'i_owe')
            ->where('status', 'open')
            ->whereNull('deleted_at');
        $overdueAmount = (int) (clone $openDebt)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today()->toDateString())
            ->sum('outstanding_amount_minor');
        $upcomingAmount = (int) (clone $openDebt)
            ->whereBetween('due_date', [
                today()->toDateString(),
                today()->addDays(30)->toDateString(),
            ])->sum('outstanding_amount_minor');
        $undatedCount = (int) (clone $openDebt)->whereNull('due_date')->count();

        $actionStatuses = DB::table('financial_space_action_intents')
            ->where('financial_space_id', $space->id)
            ->where('currency', $space->currency)
            ->whereIn('status', [
                'submission_started', 'submitted', 'submission_unknown',
                'settlement_exception', 'reversed_requires_book_correction',
                'statement_matched_unallocated',
            ])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')->get()->pluck('count', 'status');

        $alerts = [];
        if ($overdueAmount > 0) {
            $alerts[] = [
                'code' => 'debt_overdue',
                'severity' => 'attention',
                'evidence_amount_minor' => $overdueAmount,
                'message' => 'Review overdue recorded debt, affordability and repayment options before taking on new borrowing.',
            ];
        }
        if ($unreconciledCount > 0) {
            $alerts[] = [
                'code' => 'unreconciled_cashbook',
                'severity' => 'review',
                'evidence_count' => $unreconciledCount,
                'message' => 'Import statement evidence and resolve cashbook differences before relying on balances.',
            ];
        }
        if (collect($actionStatuses)->sum() > 0) {
            $alerts[] = [
                'code' => 'financial_actions_pending',
                'severity' => 'review',
                'evidence_count' => (int) collect($actionStatuses)->sum(),
                'message' => 'Review pending, ambiguous or reversal-affected payments. Do not resubmit uncertain payments.',
            ];
        }
        if ($undatedCount > 0) {
            $alerts[] = [
                'code' => 'debts_without_due_dates',
                'severity' => 'review',
                'evidence_count' => $undatedCount,
                'message' => 'Add agreed due dates where evidence exists; avoid inventing contractual dates.',
            ];
        }

        return response()->json(['data' => [
            'space_public_id' => $space->public_id,
            'currency' => $space->currency,
            'period' => 'recorded_to_date',
            'observed_cashbook' => [
                'matched_receipts_minor' => $matchedCredits,
                'matched_payments_minor' => $matchedDebits,
                'unreconciled_count' => $unreconciledCount,
                'not_bank_balance' => true,
            ],
            'debt_tracker' => [
                'overdue_minor' => $overdueAmount,
                'due_next_30_days_minor' => $upcomingAmount,
                'open_undated_count' => $undatedCount,
            ],
            'payment_exceptions' => $actionStatuses,
            'advisories' => $alerts,
            'recommendation_basis' => 'reported_positions_only',
            'credit_decision_eligible' => false,
            'automatic_payment_execution' => false,
            'notice' => 'This is a rule-based exception checklist, not financial advice, lending eligibility, bank confirmation or statutory regulatory reporting.',
        ]])->header('Cache-Control', 'private, no-store');
    }
}
