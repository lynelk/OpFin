<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrbReport;
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
}
