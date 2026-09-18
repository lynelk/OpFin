<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditReferenceSubmission;
use App\Models\CreditTermVariation;
use App\Models\Loan;
use App\Models\LoanNplControl;
use App\Services\CreditReferenceReportingService;
use App\Services\CreditTermVariationService;
use App\Services\NplRecoveryPolicyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UmraOperationsController extends Controller
{
    public function __construct(
        private readonly CreditReferenceReportingService $creditReporting,
        private readonly NplRecoveryPolicyService $nplPolicy,
        private readonly CreditTermVariationService $variations,
    ) {}

    public function creditReferenceSubmissions(Request $request): JsonResponse
    {
        $query = CreditReferenceSubmission::query()->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('information_type')) {
            $query->where('information_type', $request->input('information_type'));
        }

        return ApiResponse::success('Credit-reference reporting queue loaded.', [
            'submissions' => $query->limit(200)->get(),
            'summary' => [
                'pending' => CreditReferenceSubmission::where('status', 'pending')->count(),
                'failed' => CreditReferenceSubmission::where('status', 'failed')->count(),
                'submitted' => CreditReferenceSubmission::where('status', 'submitted')->count(),
                'positive' => CreditReferenceSubmission::where('information_type', 'positive')->count(),
                'negative' => CreditReferenceSubmission::where('information_type', 'negative')->count(),
            ],
        ]);
    }

    public function retryCreditReferenceSubmission(CreditReferenceSubmission $submission): JsonResponse
    {
        $submission->update([
            'status' => CreditReferenceSubmission::STATUS_PENDING,
            'due_at' => now(),
            'error_message' => null,
        ]);

        try {
            $submission = $this->creditReporting->submit($submission);
        } catch (\Throwable $exception) {
            return ApiResponse::error('Credit-reference submission failed: '.$exception->getMessage(), 502);
        }

        return ApiResponse::success('Credit-reference submission sent.', ['submission' => $submission]);
    }

    public function nplControls(): JsonResponse
    {
        return ApiResponse::success('UMRA NPL recovery controls loaded.', [
            'controls' => LoanNplControl::query()->with('loan')->latest('last_evaluated_at')->limit(200)->get(),
            'enforcement_mode' => config('opfin.compliance.umra_npl_cap_mode', 'enforce'),
        ]);
    }

    public function evaluateNpl(Loan $loan): JsonResponse
    {
        return ApiResponse::success('NPL recovery control evaluated.', [
            'control' => $this->nplPolicy->evaluate($loan),
        ]);
    }

    public function variations(): JsonResponse
    {
        return ApiResponse::success('Credit-term variations loaded.', [
            'variations' => CreditTermVariation::query()->latest()->limit(200)->get(),
        ]);
    }

    public function proposeVariation(Loan $loan, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proposed_changes' => 'required|array|min:1',
            'reason' => 'required|string|max:2000',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $variation = $this->variations->propose(
                $loan,
                $request->user(),
                $request->input('proposed_changes'),
                (string) $request->input('reason'),
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success(
            $variation->requires_umra_approval
                ? 'Variation recorded. Prior UMRA approval must be recorded before customer acceptance.'
                : 'Variation recorded. Customer consent is required before it can take effect.',
            ['variation' => $variation],
            201,
        );
    }

    public function recordUmraApproval(CreditTermVariation $variation, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'approval_reference' => 'required|string|max:255',
            'approval_document_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $variation = $this->variations->recordUmraApproval(
                $variation,
                (string) $request->input('approval_reference'),
                (string) $request->input('approval_document_hash'),
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Prior UMRA approval evidence recorded.', ['variation' => $variation]);
    }

    public function applyVariation(CreditTermVariation $variation): JsonResponse
    {
        return ApiResponse::error(
            'Automatic mutation of an accepted live-loan contract is disabled. The variation may be recorded and consented, but changing the economic schedule requires a separately controlled, versioned amendment executor.',
            409,
            [
                'variation_status' => [$variation->status],
                'customer_consented_at' => [$variation->customer_consented_at?->toIso8601String()],
                'umra_approved_at' => [$variation->umra_approved_at?->toIso8601String()],
            ],
        );
    }
}
