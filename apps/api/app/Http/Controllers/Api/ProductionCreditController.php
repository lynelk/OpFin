<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrbReport;
use App\Models\CreditDecision;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\AffordabilityService;
use App\Services\AuditLogger;
use App\Services\PlatformCreditRoutingService;
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
        private readonly AffordabilityService $affordability,
        private readonly AuditLogger $auditLogger,
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
            // Retained as optional reviewer cross-checks for older clients. They are never
            // authoritative inputs to the approval calculation.
            'monthly_income_minor' => 'nullable|integer|min:1',
            'estimated_obligation_minor' => 'nullable|integer|min:0',
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
        $approvedAmountMinor = (int) $validated['approved_amount_minor'];
        if ($approvedAmountMinor > $decision->requested_amount_minor) {
            return ApiResponse::error(
                'Approved amount cannot exceed the customer requested amount.',
                422,
                ['approved_amount_minor' => ['APPROVED_AMOUNT_EXCEEDS_REQUEST']],
            );
        }

        $application = LoanApplication::query()->with('loanProductTerm')->findOrFail($decision->loan_application_id);
        app(PlatformCreditRoutingService::class)->assertManager($request->user(), $application->institution);
        $customer = User::withoutGlobalScopes()->findOrFail($decision->user_id);
        $affordability = $this->affordability->assess($customer, $application, $approvedAmountMinor);

        if ($affordability['status'] === 'unavailable') {
            return ApiResponse::error(
                'Verified affordability evidence is required before manual approval.',
                422,
                ['estimated_obligation_minor' => [$affordability['reason_code']]],
            );
        }

        if ($affordability['status'] !== 'eligible') {
            return ApiResponse::error(
                'Debt-service ratio exceeds the configured production affordability limit.',
                422,
                ['estimated_obligation_minor' => [$affordability['reason_code']]],
            );
        }

        $verifiedIncomeMinor = (int) $affordability['monthly_income_minor'];
        $effectiveObligationMinor = (int) $affordability['estimated_obligation_minor'];
        $reasonCodes = array_values(array_unique([
            ...$validated['reason_codes'],
            $affordability['reason_code'],
            'AFFORDABILITY_VERIFIED_INPUTS_APPLIED',
        ]));

        $decision->update([
            'decided_by' => $request->user()->id,
            'status' => CreditDecision::STATUS_APPROVED,
            'approved_amount_minor' => $approvedAmountMinor,
            'monthly_income_minor' => $verifiedIncomeMinor,
            'estimated_obligation_minor' => $effectiveObligationMinor,
            'policy_version' => $validated['policy_version'],
            'reason_codes' => $reasonCodes,
            'decision_summary' => $validated['decision_summary'],
            'decided_at' => now(),
        ]);

        $decision->application()->update(['status' => 'Approved', 'approved_at' => now()]);
        $this->auditLogger->record(
            'credit.decision.approved',
            $request->user(),
            $decision,
            [
                'policy_version' => $decision->policy_version,
                'approved_amount_minor' => $decision->approved_amount_minor,
                'verified_monthly_income_minor' => $verifiedIncomeMinor,
                'verified_effective_obligation_minor' => $effectiveObligationMinor,
                'operator_supplied_monthly_income_minor' => isset($validated['monthly_income_minor'])
                    ? (int) $validated['monthly_income_minor']
                    : null,
                'operator_supplied_obligation_minor' => isset($validated['estimated_obligation_minor'])
                    ? (int) $validated['estimated_obligation_minor']
                    : null,
                'existing_thirty_day_debt_service_minor' => (int) ($affordability['existing_thirty_day_debt_service_minor'] ?? 0),
                'proposed_thirty_day_debt_service_minor' => (int) ($affordability['proposed_thirty_day_debt_service_minor'] ?? 0),
                'verified_external_obligation_minor' => (int) ($affordability['verified_external_obligation_minor'] ?? 0),
                'external_obligation_is_net_of_opfin' => (bool) ($affordability['external_obligation_is_net_of_opfin'] ?? false),
                'debt_service_ratio_percent' => (float) $affordability['debt_service_ratio_percent'],
                'maximum_debt_service_ratio_percent' => (float) $affordability['maximum_debt_service_ratio_percent'],
                'reason_codes' => $decision->reason_codes,
            ],
            $request,
        );

        return ApiResponse::success('Credit decision approved for offer generation.', [
            'decision' => $decision->fresh(),
            'affordability' => [
                ...$affordability,
                'formula' => '(verified_external_obligation + existing_30d_debt_service + proposed_30d_debt_service) / verified_monthly_income * 100',
                'operator_inputs_are_advisory_only' => true,
            ],
            'next_state' => 'offer_generation',
        ]);
    }
}
