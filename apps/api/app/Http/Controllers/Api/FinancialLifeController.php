<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinancialLifeController extends Controller
{
    public function summary(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->member($request, $space);
        $assets = (int) DB::table('financial_assets')->where('financial_space_id', $space->id)->where('status', 'active')->whereNull('deleted_at')->sum('value_minor');
        $owed = (int) DB::table('financial_obligations')->where('financial_space_id', $space->id)->where('direction', 'i_owe')->where('status', 'open')->whereNull('deleted_at')->sum('outstanding_amount_minor');
        $receivable = (int) DB::table('financial_obligations')->where('financial_space_id', $space->id)->where('direction', 'owed_to_me')->where('status', 'open')->whereNull('deleted_at')->sum('outstanding_amount_minor');
        $cash = (int) DB::table('financial_accounts')->where('financial_space_id', $space->id)->where('active', true)->sum('balance_minor');
        $upcoming = (int) DB::table('financial_calendar_events')->where('financial_space_id', $space->id)->where('status', 'upcoming')->where('direction', 'expense')->whereBetween('scheduled_for', [now(), now()->addDays(30)])->sum('amount_minor');

        return response()->json(['data' => ['cash_minor' => $cash, 'assets_minor' => $assets, 'debt_minor' => $owed, 'receivables_minor' => $receivable, 'net_worth_minor' => $cash + $assets + $receivable - $owed, 'upcoming_30d_minor' => $upcoming, 'safe_to_spend_minor' => max(0, $cash - $upcoming), 'currency' => $space->currency]]);
    }

    public function obligations(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->member($request, $space);

        return response()->json(['data' => ['obligations' => DB::table('financial_obligations')->where('financial_space_id', $space->id)->whereNull('deleted_at')->orderByRaw('due_date IS NULL, due_date')->get()]]);
    }

    public function storeObligation(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->member($request, $space);
        $v = $request->validate(['kind' => ['required', Rule::in(['loan', 'personal_debt', 'supplier_credit', 'receivable', 'payable', 'other'])], 'direction' => ['required', Rule::in(['i_owe', 'owed_to_me'])], 'counterparty_name' => ['nullable', 'string', 'max:160'], 'amount_minor' => ['required', 'integer', 'min:1'], 'currency' => ['sometimes', 'string', 'size:3'], 'due_date' => ['nullable', 'date']]);
        $id = DB::table('financial_obligations')->insertGetId(['financial_space_id' => $space->id, 'created_by_user_id' => $request->user()->id, 'kind' => $v['kind'], 'direction' => $v['direction'], 'counterparty_name' => $v['counterparty_name'] ?? null, 'original_amount_minor' => $v['amount_minor'], 'outstanding_amount_minor' => $v['amount_minor'], 'currency' => strtoupper($v['currency'] ?? $space->currency), 'due_date' => $v['due_date'] ?? null, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function settleObligation(Request $request, FinancialSpace $space, int $obligation): JsonResponse
    {
        $this->member($request, $space);
        $v = $request->validate(['amount_minor' => ['required', 'integer', 'min:1']]);
        $o = DB::table('financial_obligations')->where('financial_space_id', $space->id)->where('id', $obligation)->whereNull('deleted_at')->first();
        abort_if(! $o, 404);
        $remaining = max(0, (int) $o->outstanding_amount_minor - (int) $v['amount_minor']);
        DB::table('financial_obligations')->where('id', $o->id)->update(['outstanding_amount_minor' => $remaining, 'status' => $remaining === 0 ? 'settled' : 'open', 'updated_at' => now()]);

        return response()->json(['data' => ['outstanding_amount_minor' => $remaining, 'status' => $remaining === 0 ? 'settled' : 'open']]);
    }

    public function assets(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->member($request, $space);

        return response()->json(['data' => ['assets' => DB::table('financial_assets')->where('financial_space_id', $space->id)->whereNull('deleted_at')->orderBy('name')->get()]]);
    }

    public function storeAsset(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->member($request, $space);
        $v = $request->validate(['asset_type' => ['required', 'string', 'max:80'], 'name' => ['required', 'string', 'max:160'], 'value_minor' => ['required', 'integer', 'min:0'], 'currency' => ['sometimes', 'string', 'size:3'], 'valued_at' => ['nullable', 'date']]);
        $id = DB::table('financial_assets')->insertGetId(['financial_space_id' => $space->id, 'created_by_user_id' => $request->user()->id, 'asset_type' => $v['asset_type'], 'name' => $v['name'], 'value_minor' => $v['value_minor'], 'currency' => strtoupper($v['currency'] ?? $space->currency), 'valued_at' => $v['valued_at'] ?? now()->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    private function member(Request $request, FinancialSpace $space): void
    {
        abort_unless(DB::table('financial_space_memberships')->where('financial_space_id', $space->id)->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 403);
    }
}
