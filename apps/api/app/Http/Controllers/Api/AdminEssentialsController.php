<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EssentialsAccount;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsBiller;
use App\Models\EssentialsRepayment;
use App\Services\EssentialsOrchestrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AdminEssentialsController extends Controller
{
    public function __construct(private readonly EssentialsOrchestrationService $essentials) {}

    public function portfolio(): JsonResponse
    {
        return ApiResponse::success('Essentials portfolio loaded.', [
            'active_advances' => EssentialsAdvance::query()->whereIn('status', ['lender_funding_pending', 'lender_reversal_pending', 'funding_reserved', 'fulfilment_pending', 'active', 'overdue'])->count(),
            'principal_outstanding_minor' => (int) EssentialsAdvance::query()->whereIn('status', ['active', 'overdue'])->sum('principal_outstanding_minor'),
            'total_outstanding_minor' => (int) EssentialsAdvance::query()->whereIn('status', ['active', 'overdue'])->sum('outstanding_minor'),
            'pending_account_verification' => EssentialsAccount::query()->whereIn('verification_status', ['pending', 'pending_manual_review'])->count(),
            'by_category' => DB::table('essentials_advances as a')
                ->join('essentials_accounts as ea', 'ea.id', '=', 'a.essentials_account_id')
                ->join('essentials_billers as b', 'b.id', '=', 'ea.biller_id')
                ->whereIn('a.status', ['active', 'overdue'])
                ->groupBy('b.category')
                ->selectRaw('b.category, COUNT(*) as advances, SUM(a.outstanding_minor) as outstanding_minor')
                ->get(),
        ]);
    }

    public function workQueue(): JsonResponse
    {
        $accounts = EssentialsAccount::query()
            ->with('biller:id,code,name,category,account_label,status')
            ->whereIn('verification_status', ['pending', 'pending_manual_review'])
            ->oldest()
            ->limit(100)
            ->get()
            ->map(fn (EssentialsAccount $account) => [
                'id' => $account->id,
                'public_id' => $account->public_id,
                'user_id' => $account->user_id,
                'financial_space_id' => $account->financial_space_id,
                'nickname' => $account->nickname,
                'reference_last4' => substr((string) $account->account_reference, -4),
                'verification_status' => $account->verification_status,
                'biller' => $account->biller,
                'metadata' => $account->metadata,
                'created_at' => $account->created_at?->toISOString(),
            ]);

        $advances = EssentialsAdvance::query()
            ->whereIn('status', ['funding_reserved', 'lender_funding_pending', 'lender_reversal_pending', 'fulfilment_pending', 'overdue'])
            ->oldest()
            ->limit(100)
            ->get();

        $repayments = EssentialsRepayment::query()
            ->whereIn('status', ['pending', 'pending_provider_confirmation'])
            ->oldest()
            ->limit(100)
            ->get();

        $lenders = DB::table('partners as p')
            ->join('partner_products as pp', 'pp.partner_id', '=', 'p.id')
            ->whereIn('pp.product_type', ['essentials_credit', 'utility_credit', 'rent_credit', 'sme_essentials_credit'])
            ->select(
                'p.id as partner_id',
                'p.code as partner_code',
                'p.name as partner_name',
                'p.partner_type',
                'p.status as partner_status',
                'pp.id as product_id',
                'pp.code as product_code',
                'pp.name as product_name',
                'pp.product_type',
                'pp.status as product_status',
                'pp.integration_config',
                'pp.pricing',
                'pp.eligibility_rules',
            )
            ->orderBy('p.name')
            ->get();

        $pendingFundingPools = DB::table('capital_mandates')
            ->where('status', 'awaiting_compliance_review')
            ->select('id', 'reference', 'partner_id', 'owner_user_id', 'name', 'mandate_type', 'committed_capital_minor', 'deployed_capital_minor', 'reserved_capital_minor', 'status', 'investment_policy', 'created_at')
            ->oldest()
            ->get();

        $fundingPools = DB::table('capital_mandates')
            ->whereIn('status', ['active', 'approved'])
            ->whereNotNull('approved_at')
            ->select('id', 'reference', 'partner_id', 'name', 'committed_capital_minor', 'deployed_capital_minor', 'reserved_capital_minor', 'status')
            ->orderBy('name')
            ->get();

        return ApiResponse::success('Essentials operations queue loaded.', [
            'pending_accounts' => $accounts,
            'exception_advances' => $advances,
            'pending_repayments' => $repayments,
            'lenders' => $lenders,
            'funding_pools' => $fundingPools,
            'pending_funding_pools' => $pendingFundingPools,
            'billers' => EssentialsBiller::query()->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function storeBiller(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:60'],
            'account_label' => ['required', 'string', 'max:100'],
            'route' => ['required', 'string', 'max:40'],
            'status' => ['nullable', 'in:active,inactive'],
            'metadata' => ['nullable', 'array'],
        ]);

        $biller = EssentialsBiller::query()->updateOrCreate(
            ['code' => strtoupper($validated['code'])],
            [...$validated, 'code' => strtoupper($validated['code'])],
        );

        return ApiResponse::success('Essentials biller saved.', ['biller' => $biller], 201);
    }

    public function updateBiller(Request $request, EssentialsBiller $biller): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'category' => ['sometimes', 'string', 'max:60'],
            'account_label' => ['sometimes', 'string', 'max:100'],
            'route' => ['sometimes', 'string', 'max:40'],
            'status' => ['sometimes', 'in:active,inactive'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $biller->update($validated);

        return ApiResponse::success('Essentials biller updated.', ['biller' => $biller->fresh()]);
    }

    public function verifyAccount(Request $request, EssentialsAccount $account): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,failed'],
            'provider_reference' => ['nullable', 'string', 'max:160'],
        ]);

        try {
            $verified = $this->essentials->adminVerifyAccount(
                $account,
                $request->user(),
                $validated['status'],
                $validated['provider_reference'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Account verification updated.', ['account' => $verified]);
    }

    public function storeLender(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_code' => ['required', 'string', 'max:80'],
            'partner_name' => ['required', 'string', 'max:160'],
            'partner_type' => ['required', 'in:lender,bank,mfi,sacco,credit_provider,financial_institution'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'regulatory_evidence' => ['required', 'array'],
            'status' => ['required', 'in:onboarding,active'],
            'product_code' => ['required', 'string', 'max:80'],
            'product_name' => ['required', 'string', 'max:160'],
            'product_type' => ['required', 'in:essentials_credit,utility_credit,rent_credit,sme_essentials_credit'],
            'eligibility_rules' => ['required', 'array'],
            'pricing' => ['required', 'array'],
            'decision_route' => ['required', 'in:capital_mandate,cito'],
            'funding_pool_id' => ['nullable', 'integer', 'exists:capital_mandates,id'],
        ]);

        if (strcasecmp($validated['partner_code'], 'OPFIN') === 0 || strcasecmp($validated['partner_name'], 'OpFin') === 0) {
            return ApiResponse::error('OpFin cannot be configured as the primary lender for Essentials.', 422);
        }
        if ($validated['status'] === 'active'
            && (empty($validated['regulatory_evidence']['licence_number']) || empty($validated['regulatory_evidence']['licence_authority']))) {
            return ApiResponse::error('Active lenders require licence number and licensing authority evidence.', 422);
        }
        if ($validated['status'] === 'active'
            && $validated['decision_route'] === 'capital_mandate'
            && empty($validated['funding_pool_id'])) {
            return ApiResponse::error('An approved third-party funding pool is required before a capital-mandate lender product can become active.', 422);
        }

        try {
            $result = DB::transaction(function () use ($validated) {
            $partner = DB::table('partners')->where('code', strtoupper($validated['partner_code']))->first();
            $partnerLicenceComplete = ! empty($validated['regulatory_evidence']['licence_number'])
                && ! empty($validated['regulatory_evidence']['licence_authority']);
            $partnerValues = [
                'institution_id' => $validated['institution_id'] ?? null,
                'code' => strtoupper($validated['partner_code']),
                'name' => $validated['partner_name'],
                'partner_type' => $validated['partner_type'],
                'country' => 'UG',
                'status' => $partnerLicenceComplete ? 'active' : 'onboarding',
                'adapter_key' => $validated['decision_route'],
                'regulatory_evidence' => json_encode($validated['regulatory_evidence'], JSON_THROW_ON_ERROR),
                'metadata' => json_encode(['essentials_enabled' => true], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ];
            if ($partner) {
                DB::table('partners')->where('id', $partner->id)->update($partnerValues);
                $partnerId = $partner->id;
            } else {
                $partnerId = DB::table('partners')->insertGetId([...$partnerValues, 'created_at' => now()]);
            }

            $integration = [
                'decision_route' => $validated['decision_route'],
                'funding_pool_id' => $validated['funding_pool_id'] ?? null,
            ];
            $product = DB::table('partner_products')
                ->where('partner_id', $partnerId)
                ->where('code', strtoupper($validated['product_code']))
                ->first();
            $productValues = [
                'partner_id' => $partnerId,
                'code' => strtoupper($validated['product_code']),
                'name' => $validated['product_name'],
                'product_type' => $validated['product_type'],
                'status' => $validated['status'] === 'active' ? 'active' : 'draft',
                'country' => 'UG',
                'currency' => 'UGX',
                'eligibility_rules' => json_encode($validated['eligibility_rules'], JSON_THROW_ON_ERROR),
                'pricing' => json_encode($validated['pricing'], JSON_THROW_ON_ERROR),
                'disclosures' => json_encode(['lender_of_record' => $validated['partner_name'], 'opfin_role' => 'orchestrator_and_servicer'], JSON_THROW_ON_ERROR),
                'integration_config' => json_encode($integration, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ];
            if ($product) {
                DB::table('partner_products')->where('id', $product->id)->update($productValues);
                $productId = $product->id;
            } else {
                $productId = DB::table('partner_products')->insertGetId([...$productValues, 'created_at' => now()]);
            }

            if (! empty($validated['funding_pool_id'])) {
                $pool = DB::table('capital_mandates')->where('id', $validated['funding_pool_id'])->lockForUpdate()->first();
                if (! $pool
                    || ! in_array(strtolower((string) $pool->status), ['active', 'approved'], true)
                    || ! $pool->approved_at
                    || (int) $pool->committed_capital_minor <= 0) {
                    throw new InvalidArgumentException('The selected funding pool must be approved, active and funded.');
                }
                if ($pool->partner_id !== null && (int) $pool->partner_id !== (int) $partnerId) {
                    throw new InvalidArgumentException('The selected funding pool is already assigned to another lender.');
                }

                DB::table('capital_mandates')->where('id', $pool->id)->update([
                    'partner_id' => $partnerId,
                    'updated_at' => now(),
                ]);
            }

                return ['partner_id' => $partnerId, 'partner_product_id' => $productId];
            });
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Third-party Essentials lender configured.', $result, 201);
    }

    public function reconcileRepayment(EssentialsRepayment $repayment): JsonResponse
    {
        try {
            $updated = $this->essentials->reconcileRepayment($repayment);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Essentials repayment reconciliation completed.', ['repayment' => $updated]);
    }

    public function reconcile(EssentialsAdvance $advance): JsonResponse
    {
        try {
            $updated = $this->essentials->reconcileAdvance($advance);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Essentials fulfilment reconciliation completed.', ['advance' => $updated]);
    }
}
