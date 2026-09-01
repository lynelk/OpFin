<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrbReport;
use App\Models\CreditDecision;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Services\AuditLogger;
use App\Services\ProductionCreditDecisionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $approvedAmountMinor = (int) $validated['approved_amount_minor'];
        if ($approvedAmountMinor > $decision->requested_amount_minor) {
            return ApiResponse::error(
                'Approved amount cannot exceed the customer requested amount.',
                422,
                ['approved_amount_minor' => ['APPROVED_AMOUNT_EXCEEDS_REQUEST']],
            );
        }

        $application = LoanApplication::query()->with('loanProductTerm')->findOrFail($decision->loan_application_id);
        $monthlyIncomeMinor = (int) $validated['monthly_income_minor'];
        $declaredObligationMinor = (int) $validated['estimated_obligation_minor'];
        $existingThirtyDayDebtMinor = $this->existingThirtyDayDebtServiceMinor((int) $decision->user_id);
        $proposedThirtyDayDebtMinor = $this->projectedThirtyDayDebtServiceMinor($application, $approvedAmountMinor);
        $systemMinimumObligationMinor = $existingThirtyDayDebtMinor + $proposedThirtyDayDebtMinor;
        $effectiveObligationMinor = max($declaredObligationMinor, $systemMinimumObligationMinor);
        $dsrPercent = round(($effectiveObligationMinor / $monthlyIncomeMinor) * 100, 2);
        $maxDsrPercent = (float) config('opfin.credit.max_debt_service_ratio_percent', 35);
        if ($dsrPercent > $maxDsrPercent) {
            return ApiResponse::error(
                'Debt-service ratio exceeds the configured production affordability limit.',
                422,
                ['estimated_obligation_minor' => ['AFFORDABILITY_DSR_EXCEEDED']],
            );
        }

        $reasonCodes = array_values(array_unique([
            ...$validated['reason_codes'],
            'AFFORDABILITY_DSR_WITHIN_POLICY',
            'AFFORDABILITY_SYSTEM_OBLIGATION_FLOOR_APPLIED',
        ]));

        $decision->update([
            'decided_by' => $request->user()->id,
            'status' => CreditDecision::STATUS_APPROVED,
            'approved_amount_minor' => $approvedAmountMinor,
            'monthly_income_minor' => $monthlyIncomeMinor,
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
                'monthly_income_minor' => $monthlyIncomeMinor,
                'declared_obligation_minor' => $declaredObligationMinor,
                'existing_thirty_day_debt_service_minor' => $existingThirtyDayDebtMinor,
                'proposed_thirty_day_debt_service_minor' => $proposedThirtyDayDebtMinor,
                'system_minimum_obligation_minor' => $systemMinimumObligationMinor,
                'effective_obligation_minor' => $effectiveObligationMinor,
                'debt_service_ratio_percent' => $dsrPercent,
                'maximum_debt_service_ratio_percent' => $maxDsrPercent,
                'reason_codes' => $decision->reason_codes,
            ],
            $request,
        );

        return ApiResponse::success('Credit decision approved for offer generation.', [
            'decision' => $decision->fresh(),
            'affordability' => [
                'debt_service_ratio_percent' => $dsrPercent,
                'maximum_debt_service_ratio_percent' => $maxDsrPercent,
                'declared_obligation_minor' => $declaredObligationMinor,
                'existing_thirty_day_debt_service_minor' => $existingThirtyDayDebtMinor,
                'proposed_thirty_day_debt_service_minor' => $proposedThirtyDayDebtMinor,
                'effective_obligation_minor' => $effectiveObligationMinor,
                'formula' => 'max(declared_obligation, existing_30d_debt_service + proposed_30d_debt_service) / monthly_income * 100',
            ],
            'next_state' => 'offer_generation',
        ]);
    }

    private function existingThirtyDayDebtServiceMinor(int $userId): int
    {
        $from = now()->toDateString();
        $to = now()->addDays(30)->toDateString();
        $production = 0;
        if (Schema::hasTable('credit_repayment_schedule_items')) {
            $production = (int) DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNotNull('loans.credit_offer_id')
                ->whereNull('loans.deleted_at')
                ->whereNotIn('loans.status', ['Reversed'])
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->whereBetween('schedule.due_date', [$from, $to])
                ->sum('schedule.total_outstanding_minor');
        }

        $legacy = 0;
        if (Schema::hasTable('loan_schedules')) {
            $legacy = (int) round((float) DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNull('loans.credit_offer_id')
                ->whereNull('loans.deleted_at')
                ->whereNotIn('loans.status', ['Reversed'])
                ->where('schedule.total_outstanding', '>', 0)
                ->whereBetween('schedule.due_date', [$from, $to])
                ->sum('schedule.total_outstanding'));
        }

        return $production + $legacy;
    }

    private function projectedThirtyDayDebtServiceMinor(LoanApplication $application, int $approvedAmountMinor): int
    {
        $term = $application->loanProductTerm;
        if (! $term) {
            return $approvedAmountMinor;
        }

        $duration = (int) $term->duration;
        $installments = Loan::getInstallments($duration, (string) $term->repayment_frequency);
        $repaymentMinor = Loan::getRepaymentAmount(
            (float) $term->interest_rate / 100,
            $approvedAmountMinor,
            (string) $term->interest_type,
            $installments,
            (string) $term->interest_cycle,
            $duration,
        );
        $frequencyDays = Loan::getDaysInFrequency((string) $term->repayment_frequency);
        $occurrences = $duration <= 30
            ? $installments
            : max(1, min($installments, intdiv(30, $frequencyDays)));
        $base = intdiv($repaymentMinor, $installments);
        $remainder = $repaymentMinor % $installments;

        return ($base * $occurrences) + ($occurrences === $installments ? $remainder : 0);
    }
}
