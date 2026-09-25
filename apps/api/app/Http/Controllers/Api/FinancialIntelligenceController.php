<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\FinancialIntelligence\Access;
use App\Services\FinancialIntelligence\CsvImportReader;
use App\Services\FinancialIntelligence\IntelligenceService;
use App\Services\FinancialIntelligence\PortfolioEngine;
use App\Services\FinancialIntelligence\Values;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use JsonException;

final class FinancialIntelligenceController extends Controller
{
    public function __construct(private readonly IntelligenceService $service, private readonly Access $access, private readonly CsvImportReader $csv) {}

    public function overview(Request $request, FinancialSpace $space): JsonResponse
    {
        return $this->result(fn () => $this->service->overview($space, $request->user()));
    }

    public function source(Request $request, FinancialSpace $space): JsonResponse
    {
        $v = $request->validate(['name' => ['required', 'string', 'max:80'], 'population' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'], 'lawful_basis_reference' => ['required', 'string', 'max:255']]);
        return $this->result(fn () => $this->service->createSource($space, $request->user(), $v), 201);
    }

    public function stage(Request $request, FinancialSpace $space, int $source): JsonResponse
    {
        $v = $request->validate(['portfolio' => ['required', 'array']]);
        return $this->result(fn () => $this->service->stage($space, $request->user(), $source, $v['portfolio'], $this->key($request)), 201);
    }

    public function upload(Request $request, FinancialSpace $space, int $source): JsonResponse
    {
        $this->access->require($space, $request->user(), 'import');
        $v = $request->validate(['loans_file' => ['required', 'file', 'max:24414'], 'instalments_file' => ['nullable', 'file', 'max:24414'],
            'manifest' => ['required', 'string', 'max:100000'], 'mapping' => ['nullable', 'string', 'max:20000']]);
        return $this->result(function () use ($request, $space, $source, $v): array {
            $loans = file_get_contents($request->file('loans_file')->getRealPath());
            $schedules = $request->hasFile('instalments_file') ? file_get_contents($request->file('instalments_file')->getRealPath()) : null;
            if ($loans === false || $schedules === false) { throw new InvalidArgumentException('Unable to read the source export.'); }
            $manifest = json_decode($v['manifest'], true, 64, JSON_THROW_ON_ERROR);
            $mapping = json_decode($v['mapping'] ?? '{}', true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || ! is_array($mapping)) { throw new InvalidArgumentException('Manifest and mapping must be JSON objects.'); }
            $input = $this->csv->portfolioInput($loans, $schedules, $manifest, $mapping);
            return $this->service->stage($space, $request->user(), $source, $input, $this->key($request),
                ['loans' => hash('sha256', $loans), 'instalments' => $schedules !== null ? hash('sha256', $schedules) : null, 'mapping' => Values::hash($mapping)]);
        }, 201);
    }

    public function imports(Request $request, FinancialSpace $space, int $source): JsonResponse
    {
        return $this->result(fn () => $this->service->imports($space, $request->user(), $source, $this->page($request)));
    }

    public function detail(Request $request, FinancialSpace $space, int $import): JsonResponse
    {
        return $this->result(fn () => $this->service->detail($space, $request->user(), $import, $this->page($request)));
    }

    public function review(Request $request, FinancialSpace $space, int $import): JsonResponse
    {
        $v = $request->validate(['decision' => ['required', Rule::in(['published', 'rejected'])], 'reason' => ['required', 'string', 'max:1000']]);
        return $this->result(fn () => $this->service->publish($space, $request->user(), $import, $v['decision'], $v['reason']));
    }

    public function compare(Request $request, FinancialSpace $space): JsonResponse
    {
        $v = $request->validate(['from_import_id' => ['required', 'integer', 'min:1'], 'to_import_id' => ['required', 'integer', 'min:1']]);
        return $this->result(fn () => $this->service->comparison($space, $request->user(), (int) $v['from_import_id'], (int) $v['to_import_id']));
    }

    public function stress(Request $request, FinancialSpace $space): JsonResponse
    {
        $v = $request->validate(['import_id' => ['required', 'integer', 'min:1'], 'inflow_reduction_bps' => ['required', 'integer', 'between:0,10000'],
            'outflow_increase_bps' => ['required', 'integer', 'between:0,10000']]);
        return $this->result(fn () => $this->service->stress($space, $request->user(), (int) $v['import_id'], (int) $v['inflow_reduction_bps'], (int) $v['outflow_increase_bps']));
    }

    public function cases(Request $request, FinancialSpace $space): JsonResponse
    {
        return $this->result(fn () => $this->service->cases($space, $request->user(), $this->page($request)));
    }

    public function caseHistory(Request $request, FinancialSpace $space, int $case): JsonResponse
    {
        return $this->result(fn () => $this->service->caseHistory($space, $request->user(), $case, $this->page($request)));
    }

    public function caseEvent(Request $request, FinancialSpace $space, int $case): JsonResponse
    {
        $v = $request->validate(['action' => ['required', Rule::in(['assign', 'start', 'monitor', 'close', 'reopen', 'note'])],
            'expected_version' => ['required', 'integer', 'min:1'], 'note' => ['required', 'string', 'max:2000'],
            'assigned_to' => ['required_if:action,assign', 'integer', 'min:1'], 'due_on' => ['required_if:action,assign,monitor', 'date_format:Y-m-d'],
            'outcome' => ['required_if:action,close', Rule::in(['source_corrected', 'payment_recorded_in_core', 'hardship_plan', 'referred', 'other'])],
            'source_evidence_reference' => ['required_if:outcome,payment_recorded_in_core,source_corrected', 'string', 'max:255']]);
        return $this->result(fn () => $this->service->caseEvent($space, $request->user(), $case, $v, $this->key($request)), 201);
    }

    public function grant(Request $request, FinancialSpace $space): JsonResponse
    {
        $v = $request->validate(['user_id' => ['required', 'integer', 'min:1'], 'role' => ['required', Rule::in(Access::ROLES)],
            'expires_at' => ['required', 'date', 'after:now', 'before:+367 days'], 'revoke' => ['sometimes', 'boolean']]);
        $v['expires_at'] = Carbon::parse($v['expires_at'])->toDateTimeString();
        return $this->result(fn () => $this->service->grant($space, $request->user(), $v));
    }

    public function freezeReport(Request $request, FinancialSpace $space): JsonResponse
    {
        $v = $request->validate(['import_id' => ['required', 'integer', 'min:1']]);
        return $this->result(fn () => $this->service->report($space, $request->user(), (int) $v['import_id']), 201);
    }

    public function report(Request $request, FinancialSpace $space, int $report): JsonResponse
    {
        return $this->result(fn () => $this->service->readReport($space, $request->user(), $report));
    }

    public function reportCsv(Request $request, FinancialSpace $space, int $report)
    {
        $data = $this->service->readReport($space, $request->user(), $report);
        return response()->streamDownload(function () use ($data): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Report', 'As of', 'Currency', 'Metric', 'Value', 'Evidence hash'], ',', '"', '');
            foreach ($data['report']['analysis']['currency_metrics'] as $currency => $metrics) {
                foreach ($metrics as $metric => $value) {
                    fputcsv($output, array_map([Values::class, 'csvCell'], [$data['report_id'], $data['report']['as_of'], $currency, $metric, $value, $data['content_hash']]), ',', '"', '');
                }
            }
            fclose($output);
        }, 'opfin-intelligence-report-'.$report.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reportHtml(Request $request, FinancialSpace $space, int $report)
    {
        $data = $this->service->readReport($space, $request->user(), $report);
        return response()->view('intelligence.report', $data)->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'");
    }

    public function shareReport(Request $request, FinancialSpace $space, int $report): JsonResponse
    {
        $v = $request->validate(['recipient_space_id' => ['required', 'integer', 'min:1'], 'expires_at' => ['required', 'date', 'after:now', 'before:+367 days'], 'revoke' => ['sometimes', 'boolean']]);
        $v['expires_at'] = Carbon::parse($v['expires_at'])->toDateTimeString();
        return $this->result(fn () => $this->service->shareReport($space, $request->user(), $report, $v));
    }

    public function network(Request $request, FinancialSpace $space): JsonResponse
    {
        return $this->result(fn () => $this->service->network($space, $request->user(), $this->page($request)));
    }

    public function template(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->require($space, $request->user(), 'import');
        return response()->json(['data' => ['schema_version' => PortfolioEngine::VERSION,
            'loans_csv_headers' => ['loan_ref', 'borrower_ref', 'currency', 'principal_outstanding_minor', 'original_principal_minor', 'originated_on', 'product', 'branch', 'officer', 'sector', 'funding_source', 'guarantor_ref', 'restructured', 'unlikely_to_pay', 'unlikely_to_pay_evidence_ref', 'written_off_minor', 'regulatory_npl', 'regulatory_classification_ref'],
            'instalments_csv_headers' => ['loan_ref', 'instalment_ref', 'due_on', 'principal_due_minor', 'interest_due_minor', 'principal_paid_minor', 'interest_paid_minor'],
            'manifest_fields' => ['as_of', 'source_system', 'population', 'expected_loan_count', 'control_totals_minor', 'financials'],
            'money' => 'Integer minor units. Declare each currency explicitly. Never include thousands separators, decimal major units or mixed currencies in one control total.',
            'privacy' => 'Use institution-local pseudonymous references. No customer names, phone numbers, NINs or protected/programme attributes are required.',
            'schedule' => 'Complete remaining principal schedule and payment allocations as of the reporting cutoff. Missing schedule means unknown delinquency, not current.',
            'reconciliation' => 'Source record counts and currency-separated principal totals are mandatory. Imported records do not update the source MIS.']]);
    }

    private function key(Request $r): string { return Values::text((string) $r->header('Idempotency-Key', ''), 'Idempotency-Key'); }
    private function page(Request $r): int { $v = $r->validate(['page' => ['sometimes', 'integer', 'between:1,100000']]); return (int) ($v['page'] ?? 1); }
    private function result(callable $work, int $status = 200): JsonResponse
    {
        try { return response()->json(['data' => $work()], $status); }
        catch (InvalidArgumentException $error) { return response()->json(['message' => $error->getMessage()], 422); }
        catch (JsonException) { return response()->json(['message' => 'The import manifest or mapping is not valid exact JSON.'], 422); }
    }
}
