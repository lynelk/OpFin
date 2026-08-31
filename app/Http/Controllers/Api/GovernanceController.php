<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\FinancialIntegrityService;
use App\Services\RegulatoryReportingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GovernanceController extends Controller
{
    public function __construct(
        private readonly RegulatoryReportingService $reporting,
        private readonly FinancialIntegrityService $integrity,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success('Governance dashboard loaded.', [
            'integrity' => $this->integrity->summary(),
            'regulatory_reports' => DB::table('regulatory_report_runs')->latest('id')->limit(25)->get(),
            'open_integrity_alerts' => DB::table('financial_integrity_alerts')->where('status', 'open')->latest('id')->limit(100)->get(),
            'whatsapp' => [
                'verified_sessions' => DB::table('whatsapp_conversations')->where('state', 'verified')->where('expires_at', '>', now())->count(),
                'messages_24h' => DB::table('whatsapp_messages')->where('occurred_at', '>=', now()->subDay())->count(),
                'audit_hashes_present' => DB::table('whatsapp_messages')->whereNotNull('payload_hash')->count(),
            ],
            'privacy' => [
                'open_breach_incidents' => DB::table('data_breach_incidents')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'unnotified_breach_incidents' => DB::table('data_breach_incidents')->whereNull('notified_pdpo_at')->count(),
            ],
        ]);
    }

    public function reports(): JsonResponse
    {
        return ApiResponse::success('Regulatory reports loaded.', [
            'profiles' => RegulatoryReportingService::PROFILES,
            'reports' => DB::table('regulatory_report_runs')->latest('id')->limit(100)->get(),
        ]);
    }

    public function generateReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => ['required', Rule::in(array_keys(RegulatoryReportingService::PROFILES))],
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $report = $this->reporting->generate(
            $request->string('report_type')->toString(),
            Carbon::parse($request->input('period_start'))->startOfDay(),
            Carbon::parse($request->input('period_end'))->endOfDay(),
        );

        DB::table('regulatory_report_runs')->where('id', $report->id)->update([
            'generated_by' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $report = DB::table('regulatory_report_runs')->find($report->id);

        $this->auditLogger->record('governance.regulatory_report.generated', $request->user(), null, [
            'report_id' => $report->id,
            'report_type' => $report->report_type,
            'regulator' => $report->regulator,
            'payload_hash' => $report->payload_hash,
        ], $request);

        return ApiResponse::success('Regulatory report generated and validated.', ['report' => $report], 201);
    }

    public function approveReport(int $report, Request $request): JsonResponse
    {
        $record = DB::table('regulatory_report_runs')->where('id', $report)->first();
        if (! $record) {
            return ApiResponse::error('Regulatory report not found.', 404);
        }
        if ($record->status !== 'validated') {
            return ApiResponse::error('Only validated reports can be approved.', 422);
        }
        if ($record->generated_by && (int) $record->generated_by === (int) $request->user()->id) {
            return ApiResponse::error('Maker-checker control requires a different officer to approve this report.', 409);
        }

        DB::table('regulatory_report_runs')->where('id', $report)->update([
            'status' => 'approved_for_submission',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'updated_at' => now(),
        ]);

        $this->auditLogger->record('governance.regulatory_report.approved', $request->user(), null, [
            'report_id' => $report,
            'report_type' => $record->report_type,
            'regulator' => $record->regulator,
            'payload_hash' => $record->payload_hash,
        ], $request);

        return ApiResponse::success('Regulatory report approved for external submission.', [
            'report' => DB::table('regulatory_report_runs')->find($report),
        ]);
    }

    public function runIntegrity(Request $request): JsonResponse
    {
        $run = $this->integrity->run('manual_admin');
        $this->auditLogger->record('governance.financial_integrity.run', $request->user(), null, [
            'run_id' => $run->id,
            'status' => $run->status,
            'evidence_hash' => $run->evidence_hash,
        ], $request);

        return ApiResponse::success('Financial integrity self-audit completed.', ['run' => $run]);
    }

    public function resolveIntegrityAlert(int $alert, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['resolution' => 'required|string|max:2000']);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $record = DB::table('financial_integrity_alerts')->where('id', $alert)->where('status', 'open')->first();
        if (! $record) {
            return ApiResponse::error('Open integrity alert not found.', 404);
        }

        DB::table('financial_integrity_alerts')->where('id', $alert)->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'resolution_evidence' => json_encode([
                'resolution' => $request->input('resolution'),
                'resolved_at' => now()->toIso8601String(),
            ]),
            'updated_at' => now(),
        ]);

        $this->auditLogger->record('governance.financial_integrity.alert_resolved', $request->user(), null, [
            'alert_id' => $alert,
            'type' => $record->type,
            'reference' => $record->reference,
        ], $request);

        return ApiResponse::success('Integrity alert resolved.', [
            'alert' => DB::table('financial_integrity_alerts')->find($alert),
        ]);
    }
}
