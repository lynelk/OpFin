<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Support\ExactAllocation;
use Illuminate\Support\Facades\DB;

/** Read historical positions from immutable, approved business-date evidence. */
class ClubHistoricalState
{
    public function capitalCalls(ClubBook $book, string $through, ?int $memberUserId = null): array
    {
        $paid = [];
        $movements = DB::table('club_member_movements as movement')
            ->join('club_instructions as instruction', 'instruction.id', '=', 'movement.instruction_id')
            ->where('movement.book_id', $book->id)->where('movement.type', 'contribution')
            ->where('movement.business_date', '<=', $through)
            ->get(['movement.capital_delta_minor', 'instruction.payload']);
        foreach ($movements as $movement) {
            $payload = json_decode($movement->payload, true, 64, JSON_THROW_ON_ERROR);
            $id = isset($payload['capital_call_id']) ? (int) $payload['capital_call_id'] : null;
            if ($id !== null) {
                $paid[$id] = ExactAllocation::add($paid[$id] ?? 0, (int) $movement->capital_delta_minor);
            }
        }
        $cancelled = [];
        foreach (DB::table('club_instructions')->where('book_id', $book->id)->where('status', 'approved')
            ->where('type', 'capital_call_cancel')->where('business_date', '<=', $through)->get(['payload']) as $item) {
            $payload = json_decode($item->payload, true, 64, JSON_THROW_ON_ERROR);
            $cancelled[(int) $payload['capital_call_id']] = true;
        }
        $rows = DB::table('club_capital_calls as call')
            ->leftJoin('club_contribution_plans as plan', 'plan.id', '=', 'call.plan_id')
            ->leftJoin('club_instructions as original', function ($join): void {
                $join->on('original.id', '=', DB::raw('COALESCE(call.instruction_id, plan.instruction_id)'));
            })
            ->where('call.book_id', $book->id)->where('call.due_date', '<=', $through)
            ->where('original.business_date', '<=', $through)->where('original.status', 'approved')
            ->when($memberUserId !== null, static fn ($query) => $query->where('call.member_user_id', $memberUserId))
            ->orderBy('call.due_date')->orderBy('call.id')->get(['call.*']);
        $result = [];
        foreach ($rows as $row) {
            $amount = (int) $row->amount_minor;
            $settled = $paid[$row->id] ?? 0;
            $state = isset($cancelled[$row->id]) ? 'cancelled' : ($settled === $amount ? 'paid' : 'open');
            $result[] = ['id' => (int) $row->id, 'reference' => $row->reference,
                'member_user_id' => (int) $row->member_user_id, 'due_date' => $row->due_date,
                'amount_minor' => $amount, 'paid_minor' => $settled,
                'remaining_minor' => $state === 'cancelled' ? 0 : max(0, $amount - $settled),
                'status' => $state, 'as_of_date' => $through, 'is_receivable_or_loan' => false];
        }

        return $result;
    }

    public function distributions(ClubBook $book, string $from, string $through, ?int $memberUserId = null): array
    {
        $paid = [];
        foreach (DB::table('club_member_movements as movement')->join('club_members as member', 'member.id', '=', 'movement.member_id')
            ->where('movement.book_id', $book->id)->where('movement.type', 'distribution_paid')
            ->where('movement.business_date', '<=', $through)
            ->when($memberUserId !== null, static fn ($query) => $query->where('member.user_id', $memberUserId))
            ->get(['movement.evidence', 'movement.member_id']) as $row) {
            $evidence = json_decode($row->evidence, true, 64, JSON_THROW_ON_ERROR);
            $key = (int) $evidence['distribution_id'].':'.(int) $row->member_id;
            $paid[$key] = ExactAllocation::add($paid[$key] ?? 0, (int) $evidence['gross_minor']);
        }
        return DB::table('club_distribution_allocations as allocation')
            ->join('club_distributions as distribution', 'distribution.id', '=', 'allocation.distribution_id')
            ->join('club_members as member', 'member.id', '=', 'allocation.member_id')
            ->where('distribution.book_id', $book->id)->whereBetween('distribution.record_date', [$from, $through])
            ->when($memberUserId !== null, static fn ($query) => $query->where('member.user_id', $memberUserId))
            ->orderBy('distribution.record_date')->orderBy('allocation.id')
            ->get(['allocation.id', 'allocation.distribution_id', 'allocation.member_id', 'allocation.amount_minor',
                'allocation.weight', 'distribution.reference', 'distribution.record_date', 'distribution.allocation_basis', 'member.user_id'])
            ->map(static function ($row) use ($paid, $through): array {
                $amount = (int) $row->amount_minor;
                $settled = $paid[$row->distribution_id.':'.$row->member_id] ?? 0;
                return ['distribution_id' => (int) $row->distribution_id, 'reference' => $row->reference,
                    'record_date' => $row->record_date, 'member_user_id' => (int) $row->user_id,
                    'amount_minor' => $amount, 'paid_to_date_minor' => $settled, 'outstanding_minor' => $amount - $settled,
                    'as_of_date' => $through, 'allocation_basis' => $row->allocation_basis, 'weight_at_record_date' => (int) $row->weight];
            })->all();
    }
}
