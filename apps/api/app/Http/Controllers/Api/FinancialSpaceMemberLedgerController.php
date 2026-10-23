<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\FinancialSpaceRolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialSpaceMemberLedgerController extends Controller
{
    public function integrity(Request $request, FinancialSpace $space): JsonResponse
    {
        abort_unless($space->status === 'active' && $space->deleted_at === null, 403);
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('role');
        abort_unless(
            is_string($role) && FinancialSpaceRolePolicy::canManageFinancialRecords($space->type, $role),
            403
        );

        // Bound review size rather than silently claiming an unbounded
        // historical book has been verified.
        $count = DB::table('financial_space_member_journals')
            ->where('financial_space_id', $space->id)->count();
        $capped = $count > 5000;
        $journals = DB::table('financial_space_member_journals')
            ->where('financial_space_id', $space->id)
            ->orderBy('id')->limit(5000)->get();
        $issues = [];
        $totalDebits = 0;
        $totalCredits = 0;
        foreach ($journals as $journal) {
            $entries = DB::table('financial_space_member_journal_entries')
                ->where('journal_id', $journal->id)->orderBy('id')->get();
            $debit = 0;
            $credit = 0;
            foreach ($entries as $entry) {
                $badCurrency = $entry->currency !== $journal->currency;
                $amount = (int) $entry->amount_minor;
                if ($badCurrency || $amount <= 0) {
                    $issues[] = ['journal_id' => $journal->id, 'reason' => 'invalid_entry_currency_or_amount'];
                }
                if ($entry->side === 'debit') {
                    $debit += $amount;
                } elseif ($entry->side === 'credit') {
                    $credit += $amount;
                } else {
                    $issues[] = ['journal_id' => $journal->id, 'reason' => 'invalid_entry_side'];
                }
            }
            if (count($entries) !== 2 || $debit !== $credit || $debit !== (int) $journal->amount_minor) {
                $issues[] = [
                    'journal_id' => $journal->id,
                    'reason' => 'unbalanced_or_unexpected_journal_entries',
                ];
            }
            if ($journal->currency !== $space->currency) {
                $issues[] = ['journal_id' => $journal->id, 'reason' => 'wrong_space_currency'];
            }
            $cash = DB::table('financial_space_transactions')
                ->where('financial_space_id', $space->id)
                ->where('id', $journal->treasury_transaction_id)->first();
            if (! $cash || $cash->reconciliation_status !== 'matched'
                || (int) $cash->amount_minor !== (int) $journal->amount_minor) {
                $issues[] = ['journal_id' => $journal->id, 'reason' => 'missing_or_unmatched_treasury_evidence'];
            }
            if ($journal->action_intent_id !== null) {
                $action = DB::table('financial_space_action_intents')
                    ->where('financial_space_id', $space->id)
                    ->where('id', $journal->action_intent_id)->first();
                if (! $action || ! in_array($action->status, ['book_allocated', 'reversed_requires_book_correction'], true)) {
                    $issues[] = ['journal_id' => $journal->id, 'reason' => 'missing_or_unresolved_provider_allocation'];
                }
            }
            $totalDebits += $debit;
            $totalCredits += $credit;
        }

        return response()->json(['data' => [
            'financial_space_public_id' => $space->public_id,
            'currency' => $space->currency,
            'journal_count' => $count,
            'journal_count_checked' => $journals->count(),
            'debits_checked_minor' => $totalDebits,
            'credits_checked_minor' => $totalCredits,
            'issues' => array_slice($issues, 0, 100),
            'issue_count' => count($issues),
            'complete_book_review' => ! $capped,
            'integrity_status' => $capped ? 'incomplete_review' : (count($issues) ? 'requires_review' : 'passed_internal_check'),
            'provider_licence_certification' => false,
            'independent_external_balance_confirmation' => false,
        ]])->header('Cache-Control', 'private, no-store');
    }
}
