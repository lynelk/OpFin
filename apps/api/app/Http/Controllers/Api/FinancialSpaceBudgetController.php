<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\FinancialSpaceRolePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialSpaceBudgetController extends Controller
{
    public function index(Request $request, FinancialSpace $space): JsonResponse
    {
        abort_unless(
            FinancialSpaceRolePolicy::canViewFinancialRecords($space->type, $this->role($request, $space)),
            403
        );
        $validated = $request->validate([
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);
        $month = CarbonImmutable::parse(($validated['month'] ?? now()->format('Y-m')).'-01');
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();

        $budgets = DB::table('financial_budgets')
            ->where('financial_space_id', $space->id)
            ->where('active', true)
            ->where('currency', $space->currency)
            ->whereDate('effective_from', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
            })
            ->orderBy('category')
            ->get(['id', 'category', 'monthly_limit_minor', 'currency', 'effective_from', 'effective_to', 'alert_threshold_percent', 'active']);

        // Only statement-matched cashbook debits qualify as observed actuals.
        // A manual entry, pending provider intent or accepted exception must
        // never be promoted into verified spending.
        $matchedByCategory = [];
        $unallocatedMinor = 0;
        $matchedTotalMinor = 0;
        $matchedCount = 0;
        $transactions = DB::table('financial_space_transactions')
            ->where('financial_space_id', $space->id)
            ->where('currency', $space->currency)
            ->where('direction', 'debit')
            ->where('reconciliation_status', 'matched')
            ->whereBetween('transaction_date', [$from, $to])
            ->cursor(['amount_minor', 'metadata']);
        foreach ($transactions as $transaction) {
            $amount = (int) $transaction->amount_minor;
            $matchedTotalMinor += $amount;
            $matchedCount++;
            $metadata = json_decode((string) ($transaction->metadata ?? '{}'), true);
            $category = is_array($metadata) ? ($metadata['budget_category'] ?? null) : null;
            if (! is_string($category) || trim($category) === '') {
                $unallocatedMinor += $amount;

                continue;
            }
            $matchedByCategory[$category] = ($matchedByCategory[$category] ?? 0) + $amount;
        }

        $budgetResults = $budgets->map(function ($budget) use ($matchedByCategory) {
            $row = (array) $budget;
            $actual = (int) ($matchedByCategory[$budget->category] ?? 0);
            $limit = (int) $budget->monthly_limit_minor;
            $threshold = (int) $budget->alert_threshold_percent;
            $row['statement_matched_actual_minor'] = $actual;
            $row['remaining_planned_minor'] = $limit - $actual;
            $row['alert_threshold_reached'] = $limit > 0 && $actual * 100 >= $limit * $threshold;
            $row['over_planned_limit'] = $actual > $limit;

            return $row;
        });

        return response()->json(['data' => [
            'space_id' => $space->public_id,
            'month' => $month->format('Y-m'),
            'currency' => $space->currency,
            'budgets' => $budgetResults,
            'actuals_status' => 'statement_matched_only',
            'matched_transaction_count' => $matchedCount,
            'matched_debits_minor' => $matchedTotalMinor,
            'unallocated_matched_debits_minor' => $unallocatedMinor,
            'evidence_note' => 'Matched to an imported statement; not independently confirmed bank or provider settlement.',
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->manager($request, $space);
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'monthly_limit_minor' => ['required', 'integer', 'min:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'alert_threshold_percent' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $id = DB::table('financial_budgets')->insertGetId([
            'user_id' => $request->user()->id,
            'financial_space_id' => $space->id,
            'institution_id' => $space->institution_id,
            'category' => trim($validated['category']),
            'monthly_limit_minor' => $validated['monthly_limit_minor'],
            'currency' => $space->currency,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'alert_threshold_percent' => $validated['alert_threshold_percent'] ?? 80,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => [
            'budget' => DB::table('financial_budgets')->where('id', $id)->first(),
        ]], 201);
    }

    public function update(Request $request, FinancialSpace $space, int $budget): JsonResponse
    {
        $this->manager($request, $space);
        $validated = $request->validate([
            'monthly_limit_minor' => ['sometimes', 'integer', 'min:0'],
            'alert_threshold_percent' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $query = DB::table('financial_budgets')
            ->where('id', $budget)
            ->where('financial_space_id', $space->id)
            ->where('currency', $space->currency);
        abort_unless($query->exists(), 404);
        abort_if($validated === [], 422, 'No budget changes supplied.');

        $query->update([...$validated, 'updated_at' => now()]);

        return response()->json(['data' => [
            'budget' => $query->first(),
        ]]);
    }

    private function role(Request $request, FinancialSpace $space): string
    {
        abort_unless($space->status === 'active' && $space->deleted_at === null, 403);
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('role');
        abort_unless($role !== null, 403);

        return (string) $role;
    }

    private function manager(Request $request, FinancialSpace $space): void
    {
        abort_unless(
            FinancialSpaceRolePolicy::canManageFinancialRecords($space->type, $this->role($request, $space)),
            403
        );
    }
}
