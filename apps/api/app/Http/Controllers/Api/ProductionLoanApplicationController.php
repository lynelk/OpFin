<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\KycCase;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Services\AppStoreCreditPolicy;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductionLoanApplicationController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

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
            'distribution_channel' => ['nullable', Rule::in(['web', 'android', 'app_store'])],
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
            return ApiResponse::error('Verified KYC is required before a credit application can be submitted.', 409, ['code' => ['KYC_VERIFICATION_REQUIRED']]);
        }

        $consent = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();

        if (! $consent) {
            return ApiResponse::error('Active credit-processing consent is required before a credit application can be submitted.', 409, ['code' => ['CREDIT_CONSENT_REQUIRED']]);
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
            $minimumDuration = $distributionChannel === 'app_store' ? AppStoreCreditPolicy::MIN_FULL_REPAYMENT_DAYS : 1;
            $product = LoanProduct::query()
                ->where('status', 'Active')
                ->whereNotNull('institution_id')
                ->with(['terms' => fn ($query) => $query
                    ->where('status', 'Active')
                    ->where('duration', '>=', $minimumDuration)
                    ->orderBy('duration')
                    ->orderBy('id')])
                ->orderBy('id')
                ->get()
                ->first(fn (LoanProduct $candidate) => $candidate->terms->isNotEmpty());

            if (! $product) {
                return ApiResponse::error(
                    $distributionChannel === 'app_store'
                        ? 'No App Store-compliant credit route is currently available. No application or payout has been created.'
                        : 'No active credit route is currently available for this customer. Your request has not been submitted or paid out.',
                    409,
                    ['code' => [$distributionChannel === 'app_store' ? 'NO_APP_STORE_COMPLIANT_CREDIT_ROUTE' : 'NO_ELIGIBLE_CREDIT_ROUTE']],
                );
            }

            $term = $product->terms->first();
            $institutionId = (int) $product->institution_id;
        }

        if ($distributionChannel === 'app_store' && (int) $term->duration < AppStoreCreditPolicy::MIN_FULL_REPAYMENT_DAYS) {
            return ApiResponse::error('This credit term cannot be offered through the iOS App Store because full repayment would be due in 60 days or less.', 422, ['code' => ['APP_STORE_TERM_TOO_SHORT']]);
        }

        if ((int) $term->loan_product_id !== (int) $product->id) {
            return ApiResponse::error('The selected product term does not belong to the selected credit product.', 422, ['loan_product_term_id' => ['PRODUCT_TERM_MISMATCH']]);
        }

        if ($product->institution_id !== null && (int) $product->institution_id !== $institutionId) {
            return ApiResponse::error('The selected institution is not eligible for this credit product.', 422, ['institution_id' => ['PRODUCT_INSTITUTION_MISMATCH']]);
        }

        if (Loan::query()->where('user_id', $user->id)->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected'])->exists()) {
            return ApiResponse::error('An active loan must be resolved before another credit application can be submitted.', 409, ['code' => ['ACTIVE_LOAN_EXISTS']]);
        }

        if (LoanApplication::query()->where('user_id', $user->id)->whereIn('status', ['Pending', 'Under Review', 'Referred'])->exists()) {
            return ApiResponse::error('You already have a credit application being assessed.', 409, ['code' => ['APPLICATION_ALREADY_IN_PROGRESS']]);
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
            ]);
        });

        $this->auditLogger->record('credit.application.submitted', $user, $application, [
            'amount_minor' => $amountMinor,
            'currency' => config('services.mobile_money.currency', 'UGX'),
            'kyc_case_id' => $kyc->id,
            'consent_id' => $consent->id,
            'routing_mode' => $routingMode,
            'distribution_channel' => $distributionChannel,
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institutionId,
        ], $request);

        return ApiResponse::success('Credit application submitted for assessment.', [
            'application' => $application->load(['loanProduct', 'loanProductTerm', 'institution']),
            'next_state' => 'assessment',
            'routing_mode' => $routingMode,
        ], 201);
    }
}
