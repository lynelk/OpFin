<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanProductTerm;
use App\Services\CreditReferenceReportingService;
use App\Services\CreditTermGovernanceService;
use App\Services\UmraNplCapService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UmraComplianceController extends Controller
{
    public function creditReporting(CreditReferenceReportingService $reporting): JsonResponse
    {
        return ApiResponse::success('Credit information exchange status loaded.', [
            'summary' => [
                'pending' => DB::table('credit_information_reports')->where('status', 'pending')->count(),
                'failed' => DB::table('credit_information_reports')->where('status', 'failed')->count(),
                'submitted' => DB::table('credit_information_reports')->where('status', 'submitted')->count(),
                'overdue' => DB::table('credit_information_reports')
                    ->whereIn('status', ['pending', 'failed'])
                    ->where('due_at', '<', now())
                    ->count(),
            ],
            'records' => DB::table('credit_information_reports')->latest('id')->limit(100)->get(),
        ]);
    }

    public function submitCreditReporting(CreditReferenceReportingService $reporting): JsonResponse
    {
        return ApiResponse::success('Credit information submission run completed.', $reporting->submitPending());
    }

    public function evaluateNpl(Loan $loan, UmraNplCapService $service): JsonResponse
    {
        return ApiResponse::success('UMRA NPL controls evaluated.', ['loan' => $service->evaluate($loan)]);
    }

    public function accrueDefaultInterest(Loan $loan, Request $request, UmraNplCapService $service): JsonResponse
    {
        $validated = $request->validate(['as_of_date' => 'nullable|date']);
        $asOf = isset($validated['as_of_date']) ? Carbon::parse($validated['as_of_date'])->endOfDay() : null;

        return ApiResponse::success('Default interest recalculated from governed rate, principal and elapsed time.', [
            'loan' => $service->accrueDefaultInterest($loan, $asOf),
        ]);
    }

    public function setNplEnforcement(Loan $loan, Request $request, UmraNplCapService $service): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'override_id' => 'nullable|integer|exists:financial_control_overrides,id',
        ]);

        return ApiResponse::success('Default-interest control setting updated under governed override rules.', [
            'loan' => $service->setEnforcement(
                $loan,
                (bool) $validated['enabled'],
                $request->user(),
                $validated['override_id'] ?? null,
            ),
        ]);
    }

    public function termChange(LoanProductTerm $term, Request $request, CreditTermGovernanceService $service): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proposed_terms' => 'required|array',
            'reason' => 'required|string|max:2000',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        return ApiResponse::success('Credit-term change submitted for approval.', [
            'change_request' => $service->requestChange($term, $request->user(), $request->input('proposed_terms'), $request->input('reason')),
        ], 201);
    }

    public function approveTermChange(int $change, Request $request, CreditTermGovernanceService $service): JsonResponse
    {
        $request->validate([
            'umra_approval_reference' => 'nullable|string|max:255',
            'umra_approved_at' => 'nullable|date|before_or_equal:now',
            'regulatory_approval_reference' => 'nullable|string|max:255',
            'regulatory_approved_at' => 'nullable|date|before_or_equal:now',
        ]);

        return ApiResponse::success('Credit-term change approved.', [
            'change_request' => $service->approve(
                $change,
                $request->user(),
                $request->input('regulatory_approval_reference', $request->input('umra_approval_reference')),
                $request->input('regulatory_approved_at', $request->input('umra_approved_at')),
            ),
        ]);
    }

    public function applyTermChange(int $change, Request $request, CreditTermGovernanceService $service): JsonResponse
    {
        return ApiResponse::success('Approved credit-term change applied to future offers.', [
            'term' => $service->apply($change, $request->user()),
        ]);
    }

    public function termChanges(): JsonResponse
    {
        return ApiResponse::success('Credit-term governance register loaded.', [
            'changes' => DB::table('credit_term_change_requests')->latest('id')->limit(100)->get(),
        ]);
    }
}
