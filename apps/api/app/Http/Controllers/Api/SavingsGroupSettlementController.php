<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\MemberSettlementJournalService;
use App\Support\FinancialSpaceRolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavingsGroupSettlementController extends Controller
{
    public function __construct(private readonly MemberSettlementJournalService $journals) {}

    public function loanDisbursement(Request $request, FinancialSpace $space, int $cycle, int $loan): JsonResponse
    {
        $this->officer($request, $space);
        $data = $this->evidence($request);

        $result = DB::transaction(function () use ($request, $space, $cycle, $loan, $data) {
            $record = $this->record($space, $cycle, 'savings_group_member_loans', $loan, true);
            abort_unless($record->status === 'approved_not_disbursed', 409);
            abort_unless($record->approved_by_user_id !== $request->user()->id, 403, 'Independent allocation required.');
            $this->amountAndSpace($space, $record, (int) $data['action_id']);
            $journal = $this->journals->post(
                $space,
                (int) $data['action_id'],
                (int) $data['treasury_transaction_id'],
                'savings_group_loan',
                'loan:'.$loan,
                'group_loan_disbursement',
                ['code' => 'member_loans_receivable', 'member' => (int) $record->borrower_user_id],
                ['code' => 'group_treasury_cash', 'member' => null],
                $request->user()->id,
            );

            DB::table('savings_group_member_loans')->where('id', $loan)->update([
                'status' => 'disbursed',
                'disbursement_transaction_id' => $data['treasury_transaction_id'],
                'disbursed_at' => now(),
                'updated_at' => now(),
            ]);

            return $journal;
        });

        return response()->json(['data' => [
            'journal' => $result,
            'loan_status' => 'disbursed',
            'interest_posted' => false,
            'note' => 'Principal only. Contract interest, term disclosures and collections remain independently governed.',
        ]]);
    }

    public function loanRepayment(Request $request, FinancialSpace $space, int $cycle, int $loan): JsonResponse
    {
        $this->officer($request, $space);
        $data = $this->evidence($request);

        $journal = DB::transaction(function () use ($request, $space, $cycle, $loan, $data) {
            $record = $this->record($space, $cycle, 'savings_group_member_loans', $loan, true);
            abort_unless($record->status === 'disbursed', 409);

            $action = $this->action($space, (int) $data['action_id']);
            abort_unless((int) $action->initiated_by_user_id === (int) $record->borrower_user_id, 403);
            $amount = (int) $action->principal_amount_minor;
            $already = (int) DB::table('savings_group_loan_repayments')
                ->where('financial_space_id', $space->id)
                ->where('loan_id', $loan)->sum('principal_paid_minor');
            abort_unless($amount > 0 && $amount <= (int) $record->principal_minor - $already, 409, 'Repayment exceeds the remaining principal.');

            $journal = $this->journals->post(
                $space,
                (int) $data['action_id'],
                (int) $data['treasury_transaction_id'],
                'savings_group_loan_repayment',
                'loan:'.$loan,
                'group_loan_principal_repayment',
                ['code' => 'group_treasury_cash', 'member' => null],
                ['code' => 'member_loans_receivable', 'member' => (int) $record->borrower_user_id],
                $request->user()->id,
            );
            DB::table('savings_group_loan_repayments')->insert([
                'financial_space_id' => $space->id,
                'cycle_id' => $cycle,
                'loan_id' => $loan,
                'action_intent_id' => $data['action_id'],
                'treasury_transaction_id' => $data['treasury_transaction_id'],
                'principal_paid_minor' => $amount,
                'currency' => $space->currency,
                'allocated_by_user_id' => $request->user()->id,
                'allocated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($already + $amount === (int) $record->principal_minor) {
                DB::table('savings_group_member_loans')->where('id', $loan)
                    ->where('financial_space_id', $space->id)->update([
                        'status' => 'principal_repaid',
                        'updated_at' => now(),
                    ]);
            }

            return $journal;
        });

        return response()->json(['data' => [
            'journal' => $journal,
            'scope' => 'principal_only',
        ]]);
    }

    public function socialFundPayout(Request $request, FinancialSpace $space, int $cycle, int $claim): JsonResponse
    {
        $this->officer($request, $space);
        $data = $this->evidence($request);

        $journal = DB::transaction(function () use ($request, $space, $cycle, $claim, $data) {
            $row = $this->record($space, $cycle, 'savings_group_social_claims', $claim, true);
            abort_unless($row->status === 'approved_not_paid', 409);
            abort_unless($row->approved_by_user_id !== $request->user()->id, 403);
            $this->amountAndSpace($space, $row, (int) $data['action_id']);
            $journal = $this->journals->post(
                $space,
                (int) $data['action_id'],
                (int) $data['treasury_transaction_id'],
                'savings_group_social_payout',
                'claim:'.$claim,
                'group_social_claim_payout',
                ['code' => 'group_social_fund_liability', 'member' => null],
                ['code' => 'group_treasury_cash', 'member' => null],
                $request->user()->id,
            );
            DB::table('savings_group_social_claims')->where('id', $claim)->update([
                'status' => 'paid',
                'payout_transaction_id' => $data['treasury_transaction_id'],
                'updated_at' => now(),
            ]);

            return $journal;
        });

        return response()->json(['data' => ['journal' => $journal, 'claim_status' => 'paid']]);
    }

    public function shareoutPayout(Request $request, FinancialSpace $space, int $cycle, int $allocation): JsonResponse
    {
        $this->officer($request, $space);
        $data = $this->evidence($request);

        $journal = DB::transaction(function () use ($request, $space, $cycle, $allocation, $data) {
            $row = $this->record($space, $cycle, 'savings_group_shareout_allocations', $allocation, true);
            abort_unless($row->status === 'approved_not_paid', 409);
            abort_unless($row->approved_by_user_id !== $request->user()->id, 403);
            $action = $this->amountAndSpace($space, $row, (int) $data['action_id'], 'principal_minor');
            abort_unless((int) $row->member_user_id !== 0, 409);
            $journal = $this->journals->post(
                $space,
                (int) $data['action_id'],
                (int) $data['treasury_transaction_id'],
                'savings_group_shareout',
                'allocation:'.$allocation,
                'group_shareout_principal_payout',
                ['code' => 'member_savings_liability', 'member' => (int) $row->member_user_id],
                ['code' => 'group_treasury_cash', 'member' => null],
                $request->user()->id,
            );
            DB::table('savings_group_shareout_allocations')->where('id', $allocation)->update([
                'status' => 'paid',
                'payout_transaction_id' => $data['treasury_transaction_id'],
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

            return $journal;
        });

        return response()->json(['data' => ['journal' => $journal, 'allocation_status' => 'paid']]);
    }

    private function officer(Request $request, FinancialSpace $space): void
    {
        abort_unless($space->type === 'savings_group' && $space->status === 'active', 404);
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')->value('role');
        abort_unless(
            is_string($role) && FinancialSpaceRolePolicy::canManageFinancialRecords($space->type, $role),
            403
        );
    }

    private function evidence(Request $request): array
    {
        return $request->validate([
            'action_id' => ['required', 'integer', 'min:1'],
            'treasury_transaction_id' => ['required', 'integer', 'min:1'],
        ]);
    }

    private function record(
        FinancialSpace $space,
        int $cycle,
        string $table,
        int $id,
        bool $lock
    ): object {
        $query = DB::table($table)
            ->where('financial_space_id', $space->id)
            ->where('cycle_id', $cycle)
            ->where('id', $id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? abort(404);
    }

    private function action(FinancialSpace $space, int $actionId): object
    {
        return DB::table('financial_space_action_intents')
            ->where('financial_space_id', $space->id)
            ->where('id', $actionId)->first() ?? abort(404);
    }

    private function amountAndSpace(
        FinancialSpace $space,
        object $record,
        int $actionId,
        string $recordField = 'amount_minor'
    ): object {
        $action = $this->action($space, $actionId);
        $amount = $record->$recordField ?? $record->principal_minor ?? null;
        abort_unless(
            $amount !== null
            && (int) $action->principal_amount_minor === (int) $amount
            && (string) $action->currency === (string) $space->currency
            && $action->direction === 'disbursement',
            409,
            'Financial instruction does not match the approved benefit.'
        );

        return $action;
    }
}
