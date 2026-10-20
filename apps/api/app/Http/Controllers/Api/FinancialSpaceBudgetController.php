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
        $this->role($request, $space);
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

        return response()->json(['data' => [
            'space_id' => $space->public_id,
            'month' => $month->format('Y-m'),
            'currency' => $space->currency,
            'budgets' => $budgets,
            // Planned limits are not reconciled provider balances or spending actuals.
            'actuals_status' => 'not_included',
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
