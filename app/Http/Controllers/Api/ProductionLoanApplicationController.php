<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\KycCase;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductionLoanApplicationController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $applications = LoanApplication::query()
            ->where('user_id', $request->user()->id)
            ->with(['loanProduct', 'loanProductTerm', 'institution'])
            ->latest()
            ->limit(50)
            ->get();

        return ApiResponse::success('Credit applications loaded.', [
            'applications' => $applications,
        ]);
    }

    public function show(LoanApplication $application, Request $request): JsonResponse
    {
        if ($application->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return ApiResponse::success('Credit application loaded.', [
            'application' => $application->load(['loanProduct', 'loanProductTerm', 'institution']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'loan_product_id' => 'required|integer|exists:loan_products,id',
            'loan_product_term_id' => 'required|integer|exists:loan_product_terms,id',
            'institution_id' => 'required|integer|exists:institutions,id',
            'amount_minor' => 'nullable|required_without:amount|integer|min:1',
            'amount' => 'nullable|required_without:amount_minor|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $user = $request->user();
        $validated = $validator->validated();
        $amountMinor = (int) ($validated['amount_minor'] ?? $validated['amount']);

        $kyc = KycCase::query()
            ->where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('reviewed_at')
            ->first();

        if (! $kyc) {
            return ApiResponse::error(
                'Verified KYC is required before a credit application can be submitted.',
                409,
                ['code' => ['KYC_VERIFICATION_REQUIRED']],
            );
        }

        $consent = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();

        if (! $consent) {
            return ApiResponse::error(
                'Active credit-processing consent is required before a credit application can be submitted.',
                409,
                ['code' => ['CREDIT_CONSENT_REQUIRED']],
            );
        }

        $product = LoanProduct::findOrFail($validated['loan_product_id']);
        $term = LoanProductTerm::findOrFail($validated['loan_product_term_id']);

        if ((int) $term->loan_product_id !== (int) $product->id) {
            return ApiResponse::error(
                'The selected product term does not belong to the selected credit product.',
                422,
                ['loan_product_term_id' => ['PRODUCT_TERM_MISMATCH']],
            );
        }

        if ($product->institution_id !== null && (int) $product->institution_id !== (int) $validated['institution_id']) {
            return ApiResponse::error(
                'The selected institution is not eligible for this credit product.',
                422,
                ['institution_id' => ['PRODUCT_INSTITUTION_MISMATCH']],
            );
        }

        $activeLoan = Loan::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected'])
            ->exists();

        if ($activeLoan) {
            return ApiResponse::error(
                'An active loan must be resolved before another credit application can be submitted.',
                409,
                ['code' => ['ACTIVE_LOAN_EXISTS']],
            );
        }

        $pendingApplication = LoanApplication::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['Pending', 'Under Review', 'Referred'])
            ->exists();

        if ($pendingApplication) {
            return ApiResponse::error(
                'You already have a credit application being assessed.',
                409,
                ['code' => ['APPLICATION_ALREADY_IN_PROGRESS']],
            );
        }

        $application = DB::transaction(function () use ($user, $validated, $amountMinor) {
            return LoanApplication::create([
                'user_id' => $user->id,
                'loan_product_id' => $validated['loan_product_id'],
                'loan_product_term_id' => $validated['loan_product_term_id'],
                'institution_id' => $validated['institution_id'],
                'amount' => $amountMinor,
                'status' => 'Pending',
                'reason' => $validated['reason'],
            ]);
        });

        $this->auditLogger->record(
            'credit.application.submitted',
            $user,
            $application,
            [
                'amount_minor' => $amountMinor,
                'currency' => config('services.mobile_money.currency', 'UGX'),
                'kyc_case_id' => $kyc->id,
                'consent_id' => $consent->id,
            ],
            $request,
        );

        return ApiResponse::success('Credit application submitted for assessment.', [
            'application' => $application->load(['loanProduct', 'loanProductTerm', 'institution']),
            'next_state' => 'assessment',
        ], 201);
    }
}
