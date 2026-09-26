<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\CreditDecision;
use App\Models\CreditProfile;
use App\Models\KycCase;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Services\AuditLogger;
use App\Services\CreditDistributionService;
use App\Services\PlatformCreditRoutingService;
use App\Services\ProductionCreditDecisionService;
use App\Services\ProductionCreditOfferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ProductionLoanApplicationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ProductionCreditDecisionService $decisionService,
        private readonly ProductionCreditOfferService $offerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $applications = LoanApplication::query()
            ->where('user_id', $request->user()->id)
            ->with(['loanProduct', 'loanProductTerm', 'institution', 'creditDecision', 'creditOffers'])
            ->latest()
            ->limit(50)
            ->get();

        return ApiResponse::success('Credit applications loaded.', ['applications' => $applications]);
    }

    public function show(LoanApplication $application, Request $request): JsonResponse
    {
        if ($application->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return ApiResponse::success('Credit application loaded.', [
            'application' => $application->load(['loanProduct', 'loanProductTerm', 'institution', 'creditDecision', 'creditOffers', 'loan']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'loan_product_id' => 'nullable|integer|exists:loan_products,id',
            'loan_product_term_id' => 'nullable|integer|exists:loan_product_terms,id',
            'institution_id' => 'nullable|integer|exists:institutions,id',
            'amount_minor' => 'nullable|required_without:amount|integer|min:1',
            'amount' => 'nullable|required_without:amount_minor|integer|min:1',
            'reason' => 'required|string|max:255',
            'country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'distribution_channel' => ['nullable', Rule::in(app(CreditDistributionService::class)->channels())],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $user = $request->user();
        $validated = $validator->validated();
        $amountMinor = (int) ($validated['amount_minor'] ?? $validated['amount']);
        $distributionChannel = (string) ($validated['distribution_channel'] ?? 'web');

        $kyc = KycCase::query()
            ->where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('reviewed_at')
            ->first();

        if (! $kyc) {
            return ApiResponse::error('Verify your identity before requesting a loan.', 409, ['code' => ['KYC_VERIFICATION_REQUIRED']]);
        }

        $consent = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();

        if (! $consent) {
            return ApiResponse::error('Allow OpFin to check your credit information before requesting a loan.', 409, ['code' => ['CREDIT_CONSENT_REQUIRED']]);
        }

        $profile = CreditProfile::query()->where('user_id', $user->id)->first();
        if ($profile && $profile->available_to_borrow_minor > 0 && $amountMinor > $profile->available_to_borrow_minor) {
            return ApiResponse::error('Choose an amount within your available loan limit.', 422, [
                'amount_minor' => ['AMOUNT_EXCEEDS_AVAILABLE_LIMIT'],
                'available_to_borrow_minor' => [$profile->available_to_borrow_minor],
            ]);
        }

        $selection = collect([
            $validated['loan_product_id'] ?? null,
            $validated['loan_product_term_id'] ?? null,
            $validated['institution_id'] ?? null,
        ])->filter(fn ($value) => $value !== null);

        if ($selection->isNotEmpty() && $selection->count() !== 3) {
            return ApiResponse::error('Product routing is either fully selected or fully automatic; partial product configuration is not allowed.', 422, ['code' => ['PARTIAL_PRODUCT_SELECTION']]);
        }

        $routingMode = 'system_selected';
        if ($selection->count() === 3) {
            $routingMode = 'customer_selected_compatibility';
            $product = LoanProduct::findOrFail($validated['loan_product_id']);
            $term = LoanProductTerm::findOrFail($validated['loan_product_term_id']);
            $institutionId = (int) $validated['institution_id'];
        } else {
            $route = app(PlatformCreditRoutingService::class)->options(
                $distributionChannel, $validated['country'] ?? config('opfin.default_country', 'UG'), $amountMinor, $validated['reason'],
            )->first();
            if (! $route) {
                return ApiResponse::error('No eligible lender route is currently available for this request and channel.', 409, ['code' => ['NO_ELIGIBLE_CREDIT_ROUTE']]);
            }
            $product = $route['product'];
            $term = $route['term'];
            $institutionId = (int) $product->institution_id;
        }

        if ((int) $term->loan_product_id !== (int) $product->id) {
            return ApiResponse::error('The selected product term does not belong to the selected credit product.', 422, ['loan_product_term_id' => ['PRODUCT_TERM_MISMATCH']]);
        }

        if ($product->institution_id !== null && (int) $product->institution_id !== $institutionId) {
            return ApiResponse::error('The selected institution is not eligible for this credit product.', 422, ['institution_id' => ['PRODUCT_INSTITUTION_MISMATCH']]);
        }

        $routing = app(PlatformCreditRoutingService::class);
        $distribution = app(CreditDistributionService::class)->assess($product, $term, $distributionChannel);
        if (! $distribution['available']) {
            return ApiResponse::error($distribution['reason'], 422, ['code' => [$distribution['code']]]);
        }
        $candidate = new LoanApplication(['loan_product_id' => $product->id, 'loan_product_term_id' => $term->id, 'institution_id' => $institutionId, 'amount' => $amountMinor, 'reason' => $validated['reason'], 'distribution_channel' => $distributionChannel]);
        try {
            $routing->assertOrigination($candidate);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409, ['code' => ['CREDIT_ROUTE_UNAVAILABLE']]);
        }

        if (Loan::query()->where('user_id', $user->id)->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])->exists()) {
            return ApiResponse::error('Repay your active loan before requesting another one.', 409, ['code' => ['ACTIVE_LOAN_EXISTS']]);
        }

        if (LoanApplication::query()->where('user_id', $user->id)->whereIn('status', ['Pending', 'Under Review', 'Referred'])->exists()) {
            return ApiResponse::error('You already have a loan request being checked.', 409, ['code' => ['APPLICATION_ALREADY_IN_PROGRESS']]);
        }

        $application = DB::transaction(function () use ($user, $product, $term, $institutionId, $amountMinor, $distributionChannel, $validated) {
            return LoanApplication::create([
                'user_id' => $user->id,
                'loan_product_id' => $product->id,
                'loan_product_term_id' => $term->id,
                'institution_id' => $institutionId,
                'amount' => $amountMinor,
                'status' => 'Pending',
                'reason' => (string) $validated['reason'],
                'distribution_channel' => $distributionChannel,
                'routing_snapshot' => ['strategy' => app(PlatformCreditRoutingService::class)->strategy(), 'lender' => $product->institution->lenderDisclosure()],
            ]);
        });

        $this->auditLogger->record('credit.application.submitted', $user, $application, [
            'amount_minor' => $amountMinor,
            'currency' => config('services.mobile_money.currency', 'UGX'),
            'kyc_case_id' => $kyc->id,
            'consent_id' => $consent->id,
            'routing_mode' => $routingMode,
            'distribution_channel' => $distributionChannel,
        ], $request);

        $decision = null;
        $offer = null;
        $nextState = 'assessment';

        if ($profile) {
            $decision = $this->decisionService->decide($application, null);
            $application->update([
                'status' => match ($decision->status) {
                    CreditDecision::STATUS_APPROVED => 'Approved',
                    CreditDecision::STATUS_DECLINED => 'Declined',
                    default => 'Referred',
                },
                'approved_at' => $decision->status === CreditDecision::STATUS_APPROVED ? now() : null,
            ]);

            if ($decision->status === CreditDecision::STATUS_APPROVED) {
                $pricing = [
                    'funding_pool_id' => $product->funding_pool_id,
                    'access_fee_minor' => (int) round($amountMinor * ((float) config('opfin.credit.default_pricing.access_fee_percent', 0) / 100)),
                    'disbursement_fee_minor' => (int) config('opfin.credit.default_pricing.disbursement_fee_minor', 0),
                    'fee_treatment' => (string) config('opfin.credit.default_pricing.fee_treatment', 'financed'),
                    'expires_in_minutes' => (int) config('opfin.credit.default_pricing.expires_in_minutes', 1440),
                ];

                try {

                    $offer = $this->offerService->createOffer($application->fresh(), $user, $pricing);
                    $nextState = 'offer_ready';
                } catch (InvalidArgumentException $exception) {
                    $application->update(['status' => 'Referred']);
                    $nextState = 'assessment';
                    report($exception);
                }
            } elseif ($decision->status === CreditDecision::STATUS_DECLINED) {
                $nextState = 'declined';
            }
        }

        return ApiResponse::success('Loan request submitted.', [
            'application' => $application->fresh()->load(['loanProduct', 'loanProductTerm', 'institution']),
            'decision' => $decision,
            'offer' => $offer,
            'next_state' => $nextState,
            'routing_mode' => $routingMode,
        ], 201);
    }
}
