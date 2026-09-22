<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Models\MobileMoneyTransaction;
use App\Models\RevenueEvent;
use App\Services\RevenueAccountingService;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class PlatformCommerceController extends Controller
{
    public function __construct(
        private readonly SubscriptionBillingService $subscriptions,
        private readonly RevenueAccountingService $revenue,
    ) {}

    public function catalogue(Request $request): JsonResponse
    {
        $products = DB::table('partner_products as pp')
            ->join('partners as p', 'p.id', '=', 'pp.partner_id')
            ->where('pp.status', 'active')
            ->where('p.status', 'active')
            ->where('pp.country', strtoupper((string) $request->query('country', 'UG')))
            ->select(
                'pp.id', 'pp.code', 'pp.name', 'pp.product_type', 'pp.currency',
                'pp.eligibility_rules', 'pp.pricing', 'pp.disclosures',
                'p.name as provider_name', 'p.partner_type',
            )
            ->get();

        return response()->json(['data' => ['products' => $products]]);
    }

    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => ['plans' => DB::table('opfin_plans')->where('status', 'active')->orderBy('price_minor')->get()],
        ]);
    }

    public function subscribe(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->assertAdministrator($request, $space);
        $validated = $request->validate([
            'plan_code' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'wallet_id' => ['nullable', 'integer', 'exists:customer_wallets,id'],
        ]);

        $plan = DB::table('opfin_plans')
            ->where('code', $validated['plan_code'])
            ->where('status', 'active')
            ->first();
        abort_if(! $plan, 422, 'Plan is unavailable.');

        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: ($validated['idempotency_key'] ?? '')));
        if ((int) $plan->price_minor > 0 && $idempotencyKey === '') {
            return response()->json(['message' => 'A payment idempotency key is required for a paid subscription.'], 422);
        }
        $idempotencyKey = $idempotencyKey !== ''
            ? $idempotencyKey
            : "free-subscription:{$space->id}:{$plan->id}";

        try {
            $result = $this->subscriptions->subscribe(
                $space,
                $plan,
                $request->user(),
                $idempotencyKey,
                $request->integer('wallet_id') ?: null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => $result], 201);
    }

    public function recordRevenue(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['platform_admin', 'operations'], true), 403);
        $validated = $request->validate([
            'occurrence_key' => ['nullable', 'uuid'],
            'financial_space_id' => ['nullable', 'integer'],
            'partner_id' => ['nullable', 'integer'],
            'partner_product_id' => ['nullable', 'integer'],
            'commercial_agreement_id' => ['nullable', 'integer'],
            'event_type' => ['required', Rule::in(['subscription', 'commission', 'revenue_share', 'transaction', 'platform_fee', 'api_fee', 'servicing'])],
            'source_type' => ['required', 'string'],
            'source_reference' => ['required', 'string'],
            'gross_amount_minor' => ['required', 'integer', 'min:0'],
            'partner_amount_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $event = $this->revenue->accrue([
                ...$validated,
                'user_id' => $request->user()->id,
            ], $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['revenue_event' => $event]], 201);
    }

    public function reconcileRevenue(Request $request, int $event): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['platform_admin', 'operations'], true), 403);
        $validated = $request->validate([
            'mobile_money_transaction_id' => ['required', 'integer', 'exists:mobile_money_transactions,id'],
        ]);

        $revenueEvent = RevenueEvent::query()->findOrFail($event);
        $money = MobileMoneyTransaction::query()->findOrFail($validated['mobile_money_transaction_id']);

        try {
            $settled = $this->revenue->settleAccrued($revenueEvent, $money, $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => [
                'settled' => true,
                'revenue_event' => $settled,
                'statement_reconciliation_status' => $settled->statement_reconciliation_status,
            ],
        ]);
    }

    private function assertAdministrator(Request $request, FinancialSpace $space): void
    {
        abort_unless(
            DB::table('financial_space_memberships')
                ->where('financial_space_id', $space->id)
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'administrator', 'chairperson', 'treasurer', 'director'])
                ->exists(),
            403,
        );
    }
}
