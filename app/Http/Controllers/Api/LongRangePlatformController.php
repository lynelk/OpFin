<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LongRangeFinancialActionService;
use App\Services\LongRangePlatformService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LongRangePlatformController extends Controller
{
    public function __construct(
        private readonly LongRangePlatformService $service,
        private readonly LongRangeFinancialActionService $financialActions,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        return ApiResponse::success('Long-range platform loaded.', $this->service->overview($request->user()));
    }

    public function marketplace(Request $request): JsonResponse
    {
        $listings = DB::table('participatory_finance_listings')
            ->where('status', 'funding')
            ->where('borrower_user_id', '!=', $request->user()->id)
            ->whereColumn('funded_amount_minor', '<', 'target_amount_minor')
            ->latest('approved_at')
            ->limit(100)
            ->get(['id', 'reference', 'purpose', 'target_amount_minor', 'funded_amount_minor', 'term_days', 'lender_of_record', 'disclosures', 'approved_at']);

        return ApiResponse::success('Participatory-finance marketplace loaded.', ['listings' => $listings]);
    }

    public function linkAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_type' => 'required|in:bank,mobile_money,sacco,investment,savings,other',
            'provider' => 'required|string|max:80',
            'identifier' => 'required|string|max:160',
            'consent_reference' => 'nullable|string|max:160',
            'link_method' => 'nullable|string|max:80',
        ]);

        return ApiResponse::success('Account link recorded pending provider verification.', ['linked_account' => $this->service->linkAccount($request->user(), $data)], 201);
    }

    public function household(Request $request): JsonResponse
    {
        $data = $request->validate([
            'household_size' => 'required|integer|min:1|max:50',
            'monthly_income_minor' => 'nullable|integer|min:0',
            'monthly_fixed_costs_minor' => 'nullable|integer|min:0',
            'emergency_target_minor' => 'nullable|integer|min:0',
            'shared_goals' => 'nullable|array|max:30',
            'dependants' => 'nullable|array|max:30',
        ]);

        return ApiResponse::success('Household finance profile saved.', ['household' => $this->service->saveHousehold($request->user(), $data)]);
    }

    public function microbusiness(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:160',
            'business_type' => 'required|string|max:80',
            'registration_reference' => 'nullable|string|max:160',
            'monthly_revenue_minor' => 'nullable|integer|min:0',
            'monthly_expense_minor' => 'nullable|integer|min:0',
            'operating_data' => 'nullable|array',
        ]);

        return ApiResponse::success('Microbusiness profile saved.', ['microbusiness' => $this->service->saveMicrobusiness($request->user(), $data)]);
    }

    public function assetFinance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_category' => 'required|string|max:80',
            'asset_description' => 'required|string|max:255',
            'asset_price_minor' => 'required|integer|min:1',
            'deposit_minor' => 'nullable|integer|min:0',
            'requested_term_months' => 'required|integer|min:1|max:84',
            'geolocation_consent' => 'nullable|boolean',
        ]);

        return ApiResponse::success('Asset-finance request submitted for assessment.', ['asset_finance_request' => $this->service->requestAssetFinance($request->user(), $data)], 201);
    }

    public function community(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institution_type' => 'required|in:sacco,vsla,cooperative,association,employer_group',
            'institution_name' => 'required|string|max:160',
            'member_reference' => 'nullable|string|max:160',
            'permissions' => 'nullable|array|max:20',
        ]);

        return ApiResponse::success('Community membership recorded pending verification.', ['membership' => $this->service->joinCommunity($request->user(), $data)], 201);
    }

    public function participatoryListing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => 'required|string|max:160',
            'target_amount_minor' => 'required|integer|min:1000',
            'term_days' => 'required|integer|min:1|max:730',
            'lender_of_record' => 'nullable|string|max:160',
            'loss_allocation' => 'nullable|string|max:500',
            'fees' => 'nullable|string|max:500',
            'guarantee' => 'nullable|string|max:500',
            'custody' => 'nullable|string|max:500',
        ]);

        return ApiResponse::success('Participatory-finance listing created for compliance review.', ['listing' => $this->service->createParticipatoryListing($request->user(), $data)], 201);
    }

    public function participatoryCommitment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_id' => 'required|integer|min:1',
            'amount_minor' => 'required|integer|min:1000',
        ]);

        return ApiResponse::success('Commitment recorded. Step-up authentication and CPay settlement are required before funds move.', ['commitment' => $this->service->commitParticipatory($request->user(), $data)], 201);
    }

    public function referral(Request $request): JsonResponse
    {
        $data = $request->validate([
            'referred_user_id' => 'nullable|integer|exists:users,id',
            'event_type' => 'nullable|in:invited,registered,verified,eligible',
        ]);
        if (($data['referred_user_id'] ?? null) === $request->user()->id) {
            return ApiResponse::error('Self-referral is not allowed.', 422);
        }

        return ApiResponse::success('Referral event recorded. Rewards remain zero until eligibility and anti-abuse checks pass.', ['referral' => $this->service->createReferral($request->user(), $data)], 201);
    }

    public function offlineSync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'batch_reference' => 'required|uuid',
            'device_reference' => 'required|string|max:160',
            'events' => 'required|array|max:250',
            'events.*.event_id' => 'nullable|string|max:160',
            'events.*.occurred_at' => 'nullable|date',
            'events.*.type' => 'nullable|string|max:80',
            'events.*.payload' => 'nullable|array',
        ]);

        return ApiResponse::success('Offline batch processed idempotently.', ['batch' => $this->service->syncOffline($request->user(), $data)]);
    }

    public function financialIntent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_type' => 'required|in:participatory_commitment,asset_finance_deposit',
            'source_id' => 'required|integer|min:1',
            'amount_minor' => 'required|integer|min:1',
            'idempotency_key' => 'required|string|min:12|max:160',
        ]);

        return ApiResponse::success('Financial instruction prepared. Fresh OTP step-up is required before CPay is called.', [
            'financial_intent' => $this->financialActions->createForSource($request->user(), $data['source_type'], $data['source_id'], $data['amount_minor'], $data['idempotency_key']),
        ], 201);
    }

    public function confirmFinancialIntent(Request $request, string $reference): JsonResponse
    {
        $data = $request->validate(['verification_token' => 'required|string|size:64']);

        return ApiResponse::success('Financial instruction confirmed and submitted to the governed CPay rail.', [
            'financial_intent' => $this->financialActions->confirm($request->user(), $reference, $data['verification_token']),
        ]);
    }

    public function capital(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mandate_type' => 'required|in:private_loan_book,managed_capital,co_lending,warehouse_line',
            'name' => 'required|string|max:160',
            'committed_capital_minor' => 'nullable|integer|min:0',
            'investment_policy' => 'nullable|array',
        ]);

        return ApiResponse::success('Capital mandate created for compliance review.', ['capital_mandate' => $this->service->createCapitalMandate($request->user(), $data)], 201);
    }

    public function partner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'partner_name' => 'required|string|max:160',
            'partner_type' => 'required|in:employer,sacco,merchant,lender,insurer,investment_provider,agent,aggregator',
            'allowed_products' => 'nullable|array|max:100',
            'commercial_terms' => 'nullable|array',
        ]);

        return ApiResponse::success('Partner distribution account created pending due diligence.', ['partner' => $this->service->createPartner($request->user(), $data)], 201);
    }

    public function ussd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => 'required|string|max:160',
            'phone' => 'required|string|max:32',
            'text' => 'nullable|string|max:500',
        ]);

        return ApiResponse::success('USSD response generated.', $this->service->ussd($data['session_id'], $data['phone'], $data['text'] ?? ''));
    }
}
