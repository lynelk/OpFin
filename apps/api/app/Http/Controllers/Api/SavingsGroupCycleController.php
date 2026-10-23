<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\MemberSettlementJournalService;
use App\Support\FinancialSpaceRolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SavingsGroupCycleController extends Controller
{
    public function __construct(private readonly MemberSettlementJournalService $journals) {}

    private function role(Request $request, FinancialSpace $space): string
    {
        abort_unless(
            $space->type === 'savings_group' && $space->status === 'active' && $space->deleted_at === null,
            404
        );
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('role');
        abort_unless(is_string($role) && $role !== '', 403);

        return $role;
    }

    private function officer(Request $request, FinancialSpace $space): void
    {
        abort_unless(
            FinancialSpaceRolePolicy::canManageFinancialRecords($space->type, $this->role($request, $space)),
            403
        );
    }

    private function cycle(FinancialSpace $space, int $cycle): object
    {
        return DB::table('savings_group_cycles')
            ->where('financial_space_id', $space->id)
            ->where('id', $cycle)
            ->first() ?? abort(404);
    }

    private function activeMember(FinancialSpace $space, int $member): void
    {
        abort_unless(
            DB::table('financial_space_memberships')
                ->where('financial_space_id', $space->id)
                ->where('user_id', $member)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->exists(),
            422,
            'The named person must be an active member of this Savings Group.'
        );
    }

    private function matchedTransfer(
        FinancialSpace $space,
        int $transactionId,
        string $direction,
        int $amount
    ): void {
        abort_unless(
            DB::table('financial_space_transactions')
                ->where('id', $transactionId)
                ->where('financial_space_id', $space->id)
                ->where('currency', $space->currency)
                ->where('direction', $direction)
                ->where('reconciliation_status', 'matched')
                ->where('amount_minor', $amount)
                ->exists(),
            422,
            'An exact-amount, statement-matched transaction in this Space is required.'
        );
    }

    public function index(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->role($request, $space);

        return response()->json(['data' => [
            'cycles' => DB::table('savings_group_cycles')
                ->where('financial_space_id', $space->id)
                ->orderByDesc('id')
                ->get(),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function createCycle(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->officer($request, $space);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
        ]);
        $id = DB::table('savings_group_cycles')->insertGetId([
            'financial_space_id' => $space->id,
            ...$data,
            'status' => 'draft',
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['cycle' => $this->cycle($space, $id)]], 201);
    }

    public function activate(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $this->officer($request, $space);
        $result = DB::transaction(function () use ($request, $space, $cycle) {
            $record = DB::table('savings_group_cycles')
                ->where('financial_space_id', $space->id)
                ->where('id', $cycle)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->status === 'draft', 409, 'Only a draft cycle can be activated.');
            abort_unless($record->created_by_user_id !== $request->user()->id, 403, 'Independent checker required.');
            abort_if(
                DB::table('savings_group_cycles')
                    ->where('financial_space_id', $space->id)
                    ->where('status', 'open')
                    ->exists(),
                409,
                'Close the existing cycle before activating another.'
            );
            DB::table('savings_group_cycles')->where('id', $cycle)->update([
                'status' => 'open',
                'activated_by_user_id' => $request->user()->id,
                'activated_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->cycle($space, $cycle);
        });

        return response()->json(['data' => ['cycle' => $result]]);
    }

    public function close(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $this->officer($request, $space);
        $record = $this->cycle($space, $cycle);
        abort_unless($record->status === 'open', 409);
        abort_if(today()->lessThan($record->ends_on), 409, 'Cycle end date has not yet passed.');
        DB::table('savings_group_cycles')->where('id', $cycle)->update([
            'status' => 'closed',
            'closed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['cycle' => $this->cycle($space, $cycle)]]);
    }

    public function contributions(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $this->role($request, $space);
        $this->cycle($space, $cycle);

        return response()->json(['data' => [
            'contributions' => DB::table('savings_group_contributions')
                ->where('cycle_id', $cycle)->where('financial_space_id', $space->id)
                ->orderByDesc('id')->paginate(50),
        ]]);
    }

    public function pledge(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $role = $this->role($request, $space);
        abort_unless($this->cycle($space, $cycle)->status === 'open', 409);
        $data = $request->validate([
            'member_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'kind' => ['required', Rule::in(['savings', 'social_fund', 'fine'])],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:120'],
        ]);
        $memberId = (int) ($data['member_user_id'] ?? $request->user()->id);
        if ($memberId !== $request->user()->id) {
            abort_unless(FinancialSpaceRolePolicy::canManageFinancialRecords($space->type, $role), 403);
        }
        $this->activeMember($space, $memberId);

        $id = DB::transaction(function () use ($space, $cycle, $data, $request, $memberId) {
            $existing = DB::table('savings_group_contributions')->where('cycle_id', $cycle)
                ->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless(
                    (int) $existing->member_user_id === $memberId
                    && $existing->kind === $data['kind']
                    && (int) $existing->amount_minor === (int) $data['amount_minor'],
                    409,
                    'Idempotency key is already used for a different contribution.'
                );

                return $existing->id;
            }

            return DB::table('savings_group_contributions')->insertGetId([
                'financial_space_id' => $space->id,
                'cycle_id' => $cycle,
                'member_user_id' => $memberId,
                'recorded_by_user_id' => $request->user()->id,
                'kind' => $data['kind'],
                'amount_minor' => $data['amount_minor'],
                'currency' => $space->currency,
                'status' => 'pledged',
                'idempotency_key' => $data['idempotency_key'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['data' => [
            'contribution' => DB::table('savings_group_contributions')->find($id),
        ]], 201);
    }

    public function verifyContribution(Request $request, FinancialSpace $space, int $cycle, int $contribution): JsonResponse
    {
        $this->officer($request, $space);
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'min:1'],
        ]);
        $record = DB::transaction(function () use ($space, $cycle, $contribution, $data, $request) {
            $this->cycle($space, $cycle);
            $row = DB::table('savings_group_contributions')
                ->where('financial_space_id', $space->id)
                ->where('cycle_id', $cycle)
                ->where('id', $contribution)
                ->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'pledged', 409);
            abort_unless($row->recorded_by_user_id !== $request->user()->id, 403, 'Independent verification required.');
            $this->matchedTransfer($space, (int) $data['transaction_id'], 'credit', (int) $row->amount_minor);
            abort_if(
                DB::table('savings_group_contributions')
                    ->where('statement_transaction_id', $data['transaction_id'])->exists(),
                409,
                'This statement transaction is already allocated.'
            );
            $credit = match ($row->kind) {
                'savings' => ['code' => 'member_savings_liability', 'member' => (int) $row->member_user_id],
                'social_fund' => ['code' => 'group_social_fund_liability', 'member' => null],
                'fine' => ['code' => 'group_fines_income', 'member' => null],
                default => abort(422, 'Invalid contribution accounting category.'),
            };
            $this->journals->postStatementMatched(
                $space,
                (int) $data['transaction_id'],
                'savings_group_contribution:'.$contribution,
                'group_'.$row->kind.'_contribution',
                'credit',
                (int) $row->amount_minor,
                ['code' => 'group_treasury_cash', 'member' => null],
                $credit,
                $request->user()->id,
            );
            DB::table('savings_group_contributions')->where('id', $contribution)->update([
                'statement_transaction_id' => $data['transaction_id'],
                'status' => 'verified',
                'verified_by_user_id' => $request->user()->id,
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('savings_group_contributions')->find($contribution);
        });

        return response()->json(['data' => ['contribution' => $record]]);
    }

    public function createMeeting(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $this->officer($request, $space);
        $this->cycle($space, $cycle);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'scheduled_at' => ['required', 'date'],
            'quorum_required' => ['required', 'integer', 'min:1'],
        ]);
        $id = DB::table('savings_group_meetings')->insertGetId([
            'financial_space_id' => $space->id,
            'cycle_id' => $cycle,
            ...$data,
            'recorded_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['meeting' => DB::table('savings_group_meetings')->find($id)]], 201);
    }

    public function recordMeeting(Request $request, FinancialSpace $space, int $cycle, int $meeting): JsonResponse
    {
        $this->officer($request, $space);
        $this->cycle($space, $cycle);
        $data = $request->validate([
            'minutes' => ['required', 'string', 'min:10', 'max:20000'],
            'declared_present_count' => ['required', 'integer', 'min:0'],
        ]);
        $query = DB::table('savings_group_meetings')
            ->where('financial_space_id', $space->id)
            ->where('cycle_id', $cycle)->where('id', $meeting);
        $record = $query->first();
        abort_unless($record, 404);
        abort_unless($record->status === 'planned', 409);
        // Quorum is explicitly declared, not a verified vote or electronic attendance.
        $query->update([
            ...$data,
            'status' => 'held',
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['meeting' => $query->first()]]);
    }

    public function loanRequest(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $this->role($request, $space);
        abort_unless($this->cycle($space, $cycle)->status === 'open', 409);
        $data = $request->validate([
            'principal_minor' => ['required', 'integer', 'min:1'],
            'term_days' => ['required', 'integer', 'min:1', 'max:730'],
            'interest_rate_bps' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        $id = DB::table('savings_group_member_loans')->insertGetId([
            'financial_space_id' => $space->id,
            'cycle_id' => $cycle,
            'borrower_user_id' => $request->user()->id,
            'requested_by_user_id' => $request->user()->id,
            ...$data,
            'currency' => $space->currency,
            'status' => 'requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => [
            'loan_request' => DB::table('savings_group_member_loans')->find($id),
            'disbursement_enabled' => false,
        ]], 201);
    }

    public function approveLoanRequest(Request $request, FinancialSpace $space, int $cycle, int $loan): JsonResponse
    {
        $this->officer($request, $space);
        $this->cycle($space, $cycle);
        $query = DB::table('savings_group_member_loans')
            ->where('financial_space_id', $space->id)
            ->where('cycle_id', $cycle)->where('id', $loan);
        $record = $query->first();
        abort_unless($record, 404);
        abort_unless($record->status === 'requested', 409);
        abort_unless($record->requested_by_user_id !== $request->user()->id, 403);
        $query->update([
            'status' => 'approved_not_disbursed',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => [
            'loan_request' => DB::table('savings_group_member_loans')
                ->where('financial_space_id', $space->id)
                ->where('id', $loan)->first(),
            'disbursement_enabled' => false,
            'notice' => 'Approval is internal only. Funding, eligibility, loan agreement and provider settlement remain separate.',
        ]]);
    }

    public function socialClaim(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $this->role($request, $space);
        $this->cycle($space, $cycle);
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $id = DB::table('savings_group_social_claims')->insertGetId([
            'financial_space_id' => $space->id,
            'cycle_id' => $cycle,
            'member_user_id' => $request->user()->id,
            ...$data,
            'currency' => $space->currency,
            'status' => 'requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['claim' => DB::table('savings_group_social_claims')->find($id)]], 201);
    }

    public function approveSocialClaim(Request $request, FinancialSpace $space, int $cycle, int $claim): JsonResponse
    {
        $this->officer($request, $space);
        $this->cycle($space, $cycle);
        $query = DB::table('savings_group_social_claims')
            ->where('financial_space_id', $space->id)
            ->where('cycle_id', $cycle)->where('id', $claim);
        $record = $query->first();
        abort_unless($record, 404);
        abort_unless($record->status === 'requested', 409);
        abort_unless($record->member_user_id !== $request->user()->id, 403);
        $query->update([
            'status' => 'approved_not_paid',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => [
            'claim' => DB::table('savings_group_social_claims')
                ->where('financial_space_id', $space->id)
                ->where('id', $claim)->first(),
            'payout_enabled' => false,
        ]]);
    }

    public function shareouts(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $this->role($request, $space);
        $this->cycle($space, $cycle);

        return response()->json(['data' => [
            'allocations' => DB::table('savings_group_shareout_allocations')
                ->where('cycle_id', $cycle)
                ->where('financial_space_id', $space->id)
                ->orderBy('member_user_id')->get(),
        ]]);
    }

    public function prepareShareout(Request $request, FinancialSpace $space, int $cycle): JsonResponse
    {
        $this->officer($request, $space);
        abort_unless($this->cycle($space, $cycle)->status === 'closed', 409);
        $count = DB::transaction(function () use ($space, $cycle, $request) {
            abort_if(
                DB::table('savings_group_shareout_allocations')->where('cycle_id', $cycle)->exists(),
                409,
                'Share-out has already been prepared.'
            );
            $amounts = DB::table('savings_group_contributions')
                ->where('financial_space_id', $space->id)
                ->where('cycle_id', $cycle)
                ->where('kind', 'savings')
                ->where('status', 'verified')
                ->groupBy('member_user_id')
                ->selectRaw('member_user_id, SUM(amount_minor) as saved_minor')
                ->get();
            foreach ($amounts as $amount) {
                DB::table('savings_group_shareout_allocations')->insert([
                    'financial_space_id' => $space->id,
                    'cycle_id' => $cycle,
                    'member_user_id' => $amount->member_user_id,
                    'principal_minor' => $amount->saved_minor,
                    'currency' => $space->currency,
                    'status' => 'proposed',
                    'proposed_by_user_id' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $amounts->count();
        });

        return response()->json(['data' => [
            'allocation_count' => $count,
            'status' => 'proposed_only',
            'notice' => 'Proposals cover verified savings principal only. Interest, gains, debt offsets and actual payouts require separate reconciled approval.',
        ]], 201);
    }

    public function approveShareout(Request $request, FinancialSpace $space, int $cycle, int $allocation): JsonResponse
    {
        $this->officer($request, $space);
        $this->cycle($space, $cycle);
        $query = DB::table('savings_group_shareout_allocations')
            ->where('financial_space_id', $space->id)
            ->where('cycle_id', $cycle)->where('id', $allocation);
        $record = $query->first();
        abort_unless($record, 404);
        abort_unless($record->status === 'proposed', 409);
        abort_unless($record->proposed_by_user_id !== $request->user()->id, 403, 'Independent checker required.');
        $query->update([
            'status' => 'approved_not_paid',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = DB::table('savings_group_shareout_allocations')
            ->where('financial_space_id', $space->id)
            ->where('id', $allocation)->first();

        return response()->json(['data' => ['allocation' => $updated]]);
    }
}
