<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\FinancialSpaceActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class FinancialSpaceOperationsController extends Controller
{
    public function __construct(private readonly FinancialSpaceActionService $actions) {}

    public function index(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->assertMember($request, $space);
        $rows = DB::table('financial_space_action_intents')
            ->where('financial_space_id', $space->id)->latest('id')->paginate(50);
        return response()->json(['data' => ['actions' => $rows]]);
    }

    public function store(Request $request, FinancialSpace $space): JsonResponse
    {
        $v = $request->validate([
            'action_type' => ['required', Rule::in(['contribution','collection','withdrawal','disbursement','distribution','fee','investment_subscription','investment_redemption','loan_repayment'])],
            'direction' => ['required', Rule::in(['collection','disbursement'])],
            'amount_minor' => ['required','integer','min:1'],
            'counterparty_phone' => ['required','string','max:40'],
            'idempotency_key' => ['required','string','max:255'],
            'partner_id' => ['nullable','integer','exists:partners,id'],
            'partner_product_id' => ['nullable','integer','exists:partner_products,id'],
            'source_type' => ['nullable','string','max:100'],
            'source_reference' => ['nullable','string','max:255'],
        ]);
        try { $intent = $this->actions->create($space, $request->user(), $v); }
        catch (InvalidArgumentException $e) { return response()->json(['message'=>$e->getMessage()],409); }
        return response()->json(['data'=>['action'=>$intent]],201);
    }

    public function approve(Request $request, FinancialSpace $space, int $action): JsonResponse
    {
        $intent = DB::table('financial_space_action_intents')->where('financial_space_id',$space->id)->where('id',$action)->first();
        abort_if(! $intent,404);
        try { $intent = $this->actions->approveAndSubmit($space,$intent,$request->user()); }
        catch (InvalidArgumentException $e) { return response()->json(['message'=>$e->getMessage()],409); }
        return response()->json(['data'=>['action'=>$intent]]);
    }

    private function assertMember(Request $request, FinancialSpace $space): void
    {
        abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$space->id)->where('user_id',$request->user()->id)->where('status','active')->whereNull('deleted_at')->exists(),403);
    }
}
