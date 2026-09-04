<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\ComplianceExport;
use App\Models\ComplianceReport;
use App\Models\CreditDecision;
use App\Models\KycCase;
use App\Models\LedgerTransaction;
use App\Models\MobileMoneyTransaction;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\SupportCase;
use App\Models\SupportCaseNote;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductionOperationsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function ledgerTransactions(): JsonResponse
    {
        return ApiResponse::success('Ledger transactions loaded.', [
            'ledger_transactions' => LedgerTransaction::with('entries')
                ->latest('posted_at')
                ->limit(100)
                ->get(),
        ]);
    }

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
                'status' => ReconciliationItem::STATUS_REQUIRES_PROVIDER_MATCH,
            ]);
        }

        $this->auditLogger->record('reconciliation.run.created', $request->user(), $run, ['item_count' => $transactions->count()], $request);

        return ApiResponse::success('Reconciliation run created.', ['run' => $run->fresh(), 'item_count' => $transactions->count()], 201);
    }

    public function reconciliationItems(ReconciliationRun $run): JsonResponse
    {
        return ApiResponse::success('Reconciliation items loaded.', [
            'items' => ReconciliationItem::where('reconciliation_run_id', $run->id)->latest()->get(),
        ]);
    }

    public function resolveReconciliationItem(ReconciliationItem $item, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in([
                ReconciliationItem::STATUS_MATCHED,
                ReconciliationItem::STATUS_EXCEPTION,
                ReconciliationItem::STATUS_WRITTEN_OFF,
            ])],
            'provider_amount_minor' => 'nullable|integer|min:0',
            'notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $item->update([
            ...$validator->validated(),
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        if ($item->mobile_money_transaction_id && $request->input('status') === ReconciliationItem::STATUS_MATCHED) {
            MobileMoneyTransaction::where('id', $item->mobile_money_transaction_id)
                ->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);
        }

        $this->auditLogger->record('reconciliation.item.resolved', $request->user(), $item, ['status' => $item->status], $request);

        return ApiResponse::success('Reconciliation item resolved.', ['item' => $item->fresh()]);
    }

    public function supportCases(): JsonResponse
    {
        return ApiResponse::success('Support cases loaded.', [
            'support_cases' => SupportCase::with('notes')->latest()->limit(50)->get(),
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

    public function updateSupportCase(SupportCase $case, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in([
                SupportCase::STATUS_OPEN,
                SupportCase::STATUS_IN_PROGRESS,
                SupportCase::STATUS_RESOLVED,
                SupportCase::STATUS_CLOSED,
            ])],
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|string|max:32',
            'note' => 'nullable|string|max:4000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $case->update([
            'status' => $request->input('status'),
            'assigned_to' => $request->input('assigned_to', $case->assigned_to),
            'priority' => $request->input('priority', $case->priority),
            'resolved_at' => in_array($request->input('status'), [SupportCase::STATUS_RESOLVED, SupportCase::STATUS_CLOSED], true) ? now() : null,
        ]);

        if ($request->filled('note')) {
            SupportCaseNote::create([
                'support_case_id' => $case->id,
                'created_by' => $request->user()->id,
                'note' => $request->input('note'),
                'is_internal' => true,
            ]);
        }

        $this->auditLogger->record('support.case.updated', $request->user(), $case, ['status' => $case->status], $request);

        return ApiResponse::success('Support case updated.', ['support_case' => $case->fresh('notes')]);
    }

    public function complianceReports(): JsonResponse
    {
        return ApiResponse::success('Compliance reports loaded.', [
            'reports' => ComplianceReport::with('exports')->latest()->limit(50)->get(),
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

    public function createComplianceExport(ComplianceReport $report, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => ['required', Rule::in(['csv', 'json'])],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $export = ComplianceExport::create([
            'compliance_report_id' => $report->id,
            'created_by' => $request->user()->id,
            'format' => $request->input('format'),
            'status' => 'generated',
            'storage_path' => null,
            'manifest' => [
                'report_type' => $report->report_type,
                'period_start' => $report->period_start?->toDateString(),
                'period_end' => $report->period_end?->toDateString(),
                'columns' => array_keys($report->summary ?? []),
                'record_count' => count($report->summary ?? []),
            ],
            'generated_at' => now(),
        ]);

        $report->update(['status' => ComplianceReport::STATUS_EXPORTED]);
        $this->auditLogger->record('compliance.export.created', $request->user(), $export, ['format' => $export->format], $request);

        return ApiResponse::success('Compliance export created.', ['export' => $export], 201);
    }
}
