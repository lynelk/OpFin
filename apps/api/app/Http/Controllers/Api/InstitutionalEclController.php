<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CreditOffer;
use App\Services\InstitutionalExposureAccountingService;
use App\Services\InstitutionalEclService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InstitutionalEclController extends Controller
{
    public function __construct(private readonly InstitutionalEclService $service) {}

    private function requireRead(Request $request): void
    {
        abort_unless($request->user()?->hasRole(User::ROLE_PLATFORM_ADMIN)
            || ($request->user()?->hasRole(User::ROLE_OPERATIONS)
                && (bool) $request->user()->can_manage_platform_credit), 403);
    }

    private function requireInstitution(Request $request, int $institutionId): void
    {
        $this->requireRead($request);
        if ($request->user()->hasRole(User::ROLE_OPERATIONS)
            && (int) $request->user()->institution_id !== $institutionId) {
            abort(403, 'Institution financial records are not available to this user.');
        }
    }

    private function requireChecker(Request $request): void
    {
        abort_unless($request->user()?->hasRole(User::ROLE_PLATFORM_ADMIN), 403);
    }

    private function handled(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['institutional_ecl' => $exception->getMessage()]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $institution = $request->validate(['institution_id' => 'required|integer|exists:institutions,id'])['institution_id'];
        $this->requireInstitution($request, (int) $institution);
        return ApiResponse::success('Institutional ECL register.', [
            'policies' => DB::table('institution_ecl_policies')->where('institution_id', $institution)->orderByDesc('id')->get(),
            'offer_ownerships' => DB::table('credit_offer_ecl_ownerships')->where('institution_id', $institution)->orderByDesc('id')->get(),
            'ownerships' => DB::table('loan_ecl_ownerships')->where('institution_id', $institution)->orderByDesc('id')->get(),
            'import_batches' => DB::table('institution_ecl_import_batches')->where('institution_id', $institution)
                ->orderByDesc('as_of_date')->orderByDesc('id')->limit(300)->get(),
            'external_snapshots' => DB::table('institution_ecl_external_snapshots')
                ->where('institution_id', $institution)->orderByDesc('as_of_date')->limit(500)->get(),
            'source_corrections' => DB::table('institution_ecl_source_corrections as correction')
                ->join('institution_ecl_external_snapshots as source', 'source.id', '=', 'correction.external_snapshot_id')
                ->where('source.institution_id', $institution)->orderByDesc('correction.id')
                ->limit(500)->select('correction.*', 'source.external_loan_reference')->get(),
            'runs' => DB::table('institution_ecl_runs')->where('institution_id', $institution)
                ->orderByDesc('as_of_date')->orderByDesc('revision')->get(),
        ]);
    }

    public function policy(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $v = $request->validate([
            'institution_id' => 'required|integer|exists:institutions,id',
            'version' => 'required|string|max:80',
            'effective_from' => 'required|date_format:Y-m-d',
            'currency' => 'required|regex:/^[A-Z]{3}$/',
            'stage2_dpd' => 'required|integer|min:1|max:365',
            'stage3_dpd' => 'required|integer|min:2|max:730',
            'discount_bps' => 'required|integer|min:0|max:10000',
            'methodology_reference' => 'required|string|max:255',
            'scenarios' => 'required|array|min:2|max:5',
            'scenarios.*.weight_bps' => 'required|integer|min:0|max:10000',
            'scenarios.*.pd_12m_bps' => 'required|integer|min:0|max:10000',
            'scenarios.*.pd_lifetime_bps' => 'required|integer|min:0|max:10000',
            'scenarios.*.pd_impaired_bps' => 'required|integer|min:0|max:10000',
            'scenarios.*.lgd_bps' => 'required|integer|min:0|max:10000',
        ]);
        $this->requireInstitution($request, (int) $v['institution_id']);
        $id = $this->handled(fn () => $this->service->stagePolicy($v['institution_id'], $v, $request->user()));
        return ApiResponse::success('Draft ECL policy created; independent review required.', ['id' => $id], 201);
    }

    public function approvePolicy(Request $request, int $id): JsonResponse
    {
        $this->requireChecker($request);
        $this->handled(fn () => $this->service->approvePolicy($id, $request->user()));
        return ApiResponse::success('ECL policy approved.', ['id' => $id]);
    }

    public function offerOwnership(Request $request, InstitutionalExposureAccountingService $accounting): JsonResponse
    {
        $this->requireRead($request);
        $v = $request->validate([
            'institution_id' => 'required|integer|exists:institutions,id',
            'credit_offer_id' => 'required|integer|exists:credit_offers,id',
            'owner_type' => 'required|in:opfin,partner,shared',
            'opfin_share_bps' => 'required|integer|min:0|max:10000',
            'agreement_reference' => 'required|string|max:255',
        ]);
        $offer = CreditOffer::query()->findOrFail($v['credit_offer_id']);
        abort_unless((int) $offer->institution_id === (int) $v['institution_id'], 422);
        $this->requireInstitution($request, (int) $v['institution_id']);
        $id = $this->handled(fn () => $accounting->stage($offer, $v['owner_type'],
            $v['opfin_share_bps'], $v['agreement_reference'], $request->user()));
        return ApiResponse::success('Pre-origination credit owner awaiting independent approval.', ['id' => $id], 201);
    }

    public function approveOfferOwnership(Request $request, int $id, InstitutionalExposureAccountingService $accounting): JsonResponse
    {
        $this->requireChecker($request);
        $this->handled(fn () => $accounting->approve($id, $request->user()));
        return ApiResponse::success('Pre-origination credit ownership independently approved.', ['id' => $id]);
    }

    public function ownership(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $v = $request->validate([
            'institution_id' => 'required|integer|exists:institutions,id',
            'loan_id' => 'required|integer|exists:loans,id',
            'owner_type' => 'required|in:opfin,partner,shared',
            'opfin_share_bps' => 'required|integer|min:0|max:10000',
            'agreement_reference' => 'required|string|max:255',
        ]);
        $this->requireInstitution($request, (int) $v['institution_id']);
        $id = $this->handled(fn () => $this->service->registerOwnership($v['institution_id'], $v['loan_id'], $v, $request->user()));
        return ApiResponse::success('Loan credit-risk ownership awaiting independent review.', ['id' => $id], 201);
    }

    public function approveOwnership(Request $request, int $id): JsonResponse
    {
        $this->requireChecker($request);
        $this->handled(fn () => $this->service->approveOwnership($id, $request->user()));
        return ApiResponse::success('Credit-risk ownership attested.', ['id' => $id]);
    }

    public function external(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $v = $request->validate([
            'institution_id' => 'required|integer|exists:institutions,id',
            'external_loan_reference' => 'required|string|max:160',
            'as_of_date' => 'required|date_format:Y-m-d',
            'currency' => 'required|regex:/^[A-Z]{3}$/',
            'principal_outstanding_minor' => 'required|integer|min:0|max:1000000000000',
            'days_past_due' => 'required|integer|min:0|max:65535',
            'sicr' => 'required|boolean',
            'credit_impaired' => 'required|boolean',
            'contractual_maturity_date' => 'required|date_format:Y-m-d',
            'source_evidence' => 'required|string|max:255',
        ]);
        $this->requireInstitution($request, (int) $v['institution_id']);
        $id = $this->handled(fn () => $this->service->stageExternal($v['institution_id'], $v, $request->user()));
        return ApiResponse::success('External loan evidence staged, not an OpFin accounting asset.', ['id' => $id], 201);
    }


    public function uploadExternalCsv(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $v = $request->validate([
            'institution_id' => 'required|integer|exists:institutions,id',
            'as_of_date' => 'required|date_format:Y-m-d',
            'currency' => 'required|regex:/^[A-Z]{3}$/',
            'source_evidence' => 'required|string|max:255',
            'expected_loan_count' => 'required|integer|min:1|max:2000',
            'expected_principal_minor' => 'required|integer|min:0',
            'file' => 'required|file|max:2048',
        ]);
        $this->requireInstitution($request, (int) $v['institution_id']);
        $content = file_get_contents($request->file('file')->getRealPath());
        if ($content === false) {
            throw ValidationException::withMessages(['file' => 'Unable to load CSV content.']);
        }
        $id = $this->handled(fn () => $this->service->stageExternalCsv(
            $v['institution_id'], $v['as_of_date'], $v['currency'], $v['source_evidence'],
            $content, $v['expected_loan_count'], $v['expected_principal_minor'], $request->user()
        ));
        return ApiResponse::success('Loan-book CSV staged with source controls; independent approval is required.', ['id' => $id], 201);
    }

    public function approveExternalBatch(Request $request, int $id): JsonResponse
    {
        $this->requireChecker($request);
        $count = $this->handled(fn () => $this->service->approveExternalBatch($id, $request->user()));
        return ApiResponse::success('Institutional source batch approved.', ['id' => $id, 'approved_loans' => $count]);
    }

    public function approveExternal(Request $request, int $id): JsonResponse
    {
        $this->requireChecker($request);
        $this->handled(fn () => $this->service->approveExternal($id, $request->user()));
        return ApiResponse::success('External observation independently reviewed.', ['id' => $id]);
    }

    public function correctExternal(Request $request, int $id): JsonResponse
    {
        $this->requireRead($request);
        $v = $request->validate([
            'principal_outstanding_minor' => 'required|integer|min:0|max:1000000000000',
            'days_past_due' => 'required|integer|min:0|max:65535',
            'sicr' => 'required|boolean',
            'credit_impaired' => 'required|boolean',
            'contractual_maturity_date' => 'required|date_format:Y-m-d',
            'source_evidence' => 'required|string|max:255',
            'reason' => 'required|string|min:12|max:2000',
        ]);
        $institution = DB::table('institution_ecl_external_snapshots')->where('id', $id)->value('institution_id');
        abort_unless($institution, 404);
        $this->requireInstitution($request, (int) $institution);
        $newId = $this->handled(fn () => $this->service->stageSourceCorrection($id, $v, $request->user()));
        return ApiResponse::success('Immutable source correction awaiting independent review.', ['id' => $newId], 201);
    }

    public function approveCorrection(Request $request, int $id): JsonResponse
    {
        $this->requireChecker($request);
        $this->handled(fn () => $this->service->approveSourceCorrection($id, $request->user()));
        return ApiResponse::success('Source correction independently approved; original evidence retained.', ['id' => $id]);
    }

    public function build(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $v = $request->validate([
            'institution_id' => 'required|integer|exists:institutions,id',
            'as_of_date' => 'required|date_format:Y-m-d',
            'expected_external_count' => 'required|integer|min:0|max:1000000',
            'expected_external_principal_minor' => 'required|integer|min:0',
            'coverage_reference' => 'required|string|max:255',
            'restates_run_id' => 'nullable|integer|exists:institution_ecl_runs,id',
            'restatement_reason' => 'required_with:restates_run_id|nullable|string|min:12|max:2000',
        ]);
        $this->requireInstitution($request, (int) $v['institution_id']);
        $id = $this->handled(fn () => $this->service->build($v['institution_id'], $v['as_of_date'],
            $v['expected_external_count'], $v['expected_external_principal_minor'], $v['coverage_reference'], $request->user(),
            $v['restates_run_id'] ?? null, $v['restatement_reason'] ?? null));
        return ApiResponse::success('Draft monthly ECL snapshot created; no journals posted.', ['id' => $id], 201);
    }

    public function approveRun(Request $request, int $id): JsonResponse
    {
        $this->requireChecker($request);
        $this->handled(fn () => $this->service->approveRun($id, $request->user()));
        return ApiResponse::success('Monthly ECL snapshot approved.', ['id' => $id]);
    }

    public function postRun(Request $request, int $id): JsonResponse
    {
        $this->requireChecker($request);
        $this->handled(fn () => $this->service->postRun($id, $request->user()));
        return ApiResponse::success('OpFin-owned ECL entries posted through existing impairment service; partner/share exposures remain advisory.', ['id' => $id]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->requireRead($request);
        $institution = DB::table('institution_ecl_runs')->where('id', $id)->value('institution_id');
        abort_unless($institution, 404);
        $this->requireInstitution($request, (int) $institution);
        return ApiResponse::success('Loan and portfolio-level impairment statement.', $this->handled(fn () => $this->service->details($id)));
    }

    public function export(Request $request, int $id, string $view): StreamedResponse
    {
        $this->requireRead($request);
        abort_unless(in_array($view, ['loans', 'portfolio'], true), 404);
        $institution = DB::table('institution_ecl_runs')->where('id', $id)->value('institution_id');
        abort_unless($institution, 404);
        $this->requireInstitution($request, (int) $institution);
        $details = $this->handled(fn () => $this->service->details($id));
        $run = $details['run'];
        $file = 'ecl-'.$run->institution_id.'-'.$run->as_of_date.'-'.$run->revision.'-'.$view.'.csv';
        $escape = static function (mixed $value): string {
            $s = (string) ($value ?? '');
            // Prevent Excel spreadsheet-formula injection from externally supplied references.
            return preg_match('/^[=+\-@\t\r]/u', $s) ? "'".$s : $s;
        };
        return response()->streamDownload(function () use ($details, $view, $escape) {
            $stream = fopen('php://output', 'w');
            if ($view === 'portfolio') {
                fputcsv($stream, ['institution_id','as_of_date','revision','currency','status','gross_exposure_minor','ecl_minor','opfin_risk_share_ecl_minor','ecl_movement_minor','content_hash']);
                $r = $details['run'];
                fputcsv($stream, [$r->institution_id,$r->as_of_date,$r->revision,$r->currency,$r->status,
                    $r->total_exposure_minor,$r->total_ecl_minor,$r->platform_ecl_minor,$r->ecl_movement_minor,$r->content_hash]);
            } else {
                fputcsv($stream, ['institution_id','as_of_date','loan_reference','owner_type','platform_share_bps','gross_exposure_minor','dpd','stage','ecl_minor','platform_ecl_minor','movement_minor','impairment_assessment_id']);
                foreach ($details['items'] as $item) {
                    fputcsv($stream, [$details['run']->institution_id,$details['run']->as_of_date,$escape($item->loan_reference),
                        $item->owner_type,$item->opfin_share_bps,$item->gross_exposure_minor,$item->days_past_due,
                        $item->stage,$item->expected_credit_loss_minor,$item->platform_ecl_minor,
                        $item->movement_minor,$item->impairment_assessment_id]);
                }
            }
            fclose($stream);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
