<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\ComplianceReport;
use App\Models\CreditDecision;
use App\Models\KycCase;
use App\Models\MobileMoneyTransaction;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\SupportCase;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class ProductionOperationsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function reconciliationRuns(): JsonResponse
    {
        return ApiResponse::success('Reconciliation runs loaded.', [
            'runs' => ReconciliationRun::latest()->limit(50)->get(),
        ]);
    }

    public function createReconciliationRun(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|max:64',
            'business_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $run = ReconciliationRun::create([
            ...$validator->validated(),
            'created_by' => $request->user()->id,
            'started_at' => now(),
            'summary' => ['source' => 'system_unreconciled_transactions'],
        ]);

        $transactions = MobileMoneyTransaction::where('provider', $run->provider)
            ->where('reconciliation_status', '!=', MobileMoneyTransaction::RECONCILIATION_MATCHED)
            ->get();

        foreach ($transactions as $transaction) {
            ReconciliationItem::create([
                'reconciliation_run_id' => $run->id,
                'mobile_money_transaction_id' => $transaction->id,
                'provider_reference' => $transaction->provider_reference,
                'system_amount_minor' => $transaction->amount_minor,
                'status' => 'requires_provider_match',
            ]);
        }

        $this->auditLogger->record('reconciliation.run.created', $request->user(), $run, ['item_count' => $transactions->count()], $request);

        return ApiResponse::success('Reconciliation run created.', ['run' => $run->fresh(), 'item_count' => $transactions->count()], 201);
    }

    public function supportCases(): JsonResponse
    {
        return ApiResponse::success('Support cases loaded.', [
            'support_cases' => SupportCase::latest()->limit(50)->get(),
        ]);
    }

    public function createSupportCase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:users,id',
            'category' => 'required|string|max:64',
            'priority' => 'nullable|string|max:32',
            'subject' => 'required|string|max:160',
            'description' => 'required|string|max:4000',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $case = SupportCase::create([
            ...$validator->validated(),
            'case_number' => 'CASE-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8)),
            'created_by' => $request->user()->id,
            'status' => 'open',
            'priority' => $request->input('priority', 'normal'),
        ]);

        $this->auditLogger->record('support.case.created', $request->user(), $case, ['category' => $case->category], $request);

        return ApiResponse::success('Support case created.', ['support_case' => $case], 201);
    }

    public function complianceReports(): JsonResponse
    {
        return ApiResponse::success('Compliance reports loaded.', [
            'reports' => ComplianceReport::latest()->limit(50)->get(),
        ]);
    }

    public function createComplianceReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|string|max:64',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'parameters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $report = ComplianceReport::create([
            ...$validator->validated(),
            'generated_by' => $request->user()->id,
            'summary' => [
                'kyc_cases' => KycCase::whereBetween('created_at', [$request->date('period_start')->startOfDay(), $request->date('period_end')->endOfDay()])->count(),
                'consents' => ConsentRecord::whereBetween('created_at', [$request->date('period_start')->startOfDay(), $request->date('period_end')->endOfDay()])->count(),
                'credit_decisions' => CreditDecision::whereBetween('created_at', [$request->date('period_start')->startOfDay(), $request->date('period_end')->endOfDay()])->count(),
                'mobile_money_transactions' => MobileMoneyTransaction::whereBetween('created_at', [$request->date('period_start')->startOfDay(), $request->date('period_end')->endOfDay()])->count(),
                'note' => 'Report record created; attach regulator-specific extract jobs before live submission.',
            ],
            'generated_at' => now(),
        ]);

        $this->auditLogger->record('compliance.report.created', $request->user(), $report, ['report_type' => $report->report_type], $request);

        return ApiResponse::success('Compliance report created.', ['report' => $report], 201);
    }
}
