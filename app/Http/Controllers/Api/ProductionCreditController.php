<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrbReport;
use App\Models\CreditDecision;
use App\Models\LoanApplication;
use App\Services\AuditLogger;
use App\Services\ProductionCreditDecisionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductionCreditController extends Controller
{
    public function __construct(
        private readonly ProductionCreditDecisionService $decisionService,
        private readonly AuditLogger $auditLogger
    ) {}

    public function storeCrbReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'provider' => 'required|string|max:64',
            'provider_reference' => 'nullable|string|max:128',
            'status' => ['required', Rule::in([CrbReport::STATUS_CLEAR, CrbReport::STATUS_ADVERSE, CrbReport::STATUS_ERROR, CrbReport::STATUS_PENDING])],
            'score' => 'nullable|integer|min:0|max:999',
            'risk_flags' => 'nullable|array',
            'raw_response' => 'nullable|array',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $report = CrbReport::create([
            ...$validator->validated(),
            'requested_by' => $request->user()->id,
            'requested_at' => now(),
            'received_at' => $request->input('status') === CrbReport::STATUS_PENDING ? null : now(),
        ]);

        $this->auditLogger->record('crb.report.recorded', $request->user(), $report, ['status' => $report->status], $request);

        return ApiResponse::success('CRB report recorded.', ['crb_report' => $report], 201);
    }

    public function decide(LoanApplication $application, Request $request): JsonResponse
    {
        $decision = $this->decisionService->decide($application, $request->user());
        $this->auditLogger->record('credit.decision.created', $request->user(), $decision, ['status' => $decision->status], $request);

        return ApiResponse::success('Credit decision recorded.', ['decision' => $decision]);
    }

    public function approve(CreditDecision $decision, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'approved_amount_minor' => 'required|integer|min:1',
            'monthly_income_minor' => 'required|integer|min:1',
            'estimated_obligation_minor' => 'required|integer|min:0',
            'policy_version' => 'required|string|max:64',
            'reason_codes' => 'required|array|min:1',
            'reason_codes.*' => 'string|max:64',
            'decision_summary' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        if ($decision->status !== CreditDecision::STATUS_REFERRED) {
            return ApiResponse::error('Only referred decisions can be approved through the controlled review step.', 409);
        }

        $validated = $validator->validated();
        if ((int) $validated['approved_amount_minor'] > $decision->requested_amount_minor) {
            return ApiResponse::error(
                'Approved amount cannot exceed the customer requested amount.',
                422,
                ['approved_amount_minor' => ['APPROVED_AMOUNT_EXCEEDS_REQUEST']],
            );
        }

        $decision->update([
            'decided_by' => $request->user()->id,
            'status' => CreditDecision::STATUS_APPROVED,
            'approved_amount_minor' => (int) $validated['approved_amount_minor'],
            'monthly_income_minor' => (int) $validated['monthly_income_minor'],
            'estimated_obligation_minor' => (int) $validated['estimated_obligation_minor'],
            'policy_version' => $validated['policy_version'],
            'reason_codes' => $validated['reason_codes'],
            'decision_summary' => $validated['decision_summary'],
            'decided_at' => now(),
        ]);

        $decision->application()->update([
            'status' => 'Approved',
            'approved_at' => now(),
        ]);

        $this->auditLogger->record(
            'credit.decision.approved',
            $request->user(),
            $decision,
            [
                'policy_version' => $decision->policy_version,
                'approved_amount_minor' => $decision->approved_amount_minor,
                'reason_codes' => $decision->reason_codes,
            ],
            $request,
        );

        return ApiResponse::success('Credit decision approved for offer generation.', [
            'decision' => $decision->fresh(),
            'next_state' => 'offer_generation',
        ]);
    }
}
