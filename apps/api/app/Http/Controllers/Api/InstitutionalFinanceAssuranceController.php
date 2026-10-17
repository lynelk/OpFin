<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditOffer;
use App\Models\User;
use App\Services\InstitutionalFinanceAssuranceService;
use App\Services\InstitutionalSharedAccountingPreviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class InstitutionalFinanceAssuranceController extends Controller
{
    public function __construct(private readonly InstitutionalFinanceAssuranceService $service) {}

    private function maker(Request $request, int $institutionId): void
    {
        abort_unless($request->user()?->hasRole(User::ROLE_PLATFORM_ADMIN)
            || ($request->user()?->hasRole(User::ROLE_OPERATIONS)
                && (bool) $request->user()->can_manage_platform_credit
                && (int) $request->user()->institution_id === $institutionId), 403);
    }

    private function checker(Request $request): void
    {
        abort_unless($request->user()?->hasRole(User::ROLE_PLATFORM_ADMIN), 403);
    }

    private function invoke(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['financial_assurance' => $e->getMessage()]);
        }
    }

    /**
     * Non-production, synthetic scenario only. Never touches any real ledger,
     * borrower, payment provider, legal attestation or signed source record.
     */
    public function previewShared(Request $request, InstitutionalSharedAccountingPreviewService $simulator): JsonResponse
    {
        $this->checker($request);
        abort_unless(app()->environment('local', 'testing', 'staging')
            && config('opfin.accounting.defer_institutional_evidence_for_nonproduction', false) === true, 403,
            'Provisional documentary deferral cannot be used in production.');
        $values = $request->validate([
            'contract_form' => 'required|in:direct_joint_creditor,funded_participation,financial_guarantee,first_loss',
            'currency' => 'required|regex:/^[A-Z]{3}$/D',
            'principal_amount_minor' => 'required|integer|min:1|max:1000000000000',
            'cash_disbursement_minor' => 'required|integer|min:1|max:1000000000000',
            'opfin_share_bps' => 'required|integer|min:1|max:9999',
        ]);
        $result = $this->invoke(fn () => $simulator->preview($values));
        return ApiResponse::success('Synthetic journal illustration only; no cash, approvals or credit entries posted.', $result);
    }

    public function index(Request $request): JsonResponse
    {
        $v = $request->validate(['institution_id' => 'required|integer|exists:institutions,id']);
        $this->maker($request, (int) $v['institution_id']);
        $cuts = DB::table('institution_finance_reconciliations')
            ->where('institution_id', $v['institution_id'])->orderByDesc('as_of_date')->limit(60)->get();
        $ids = $cuts->pluck('id')->all();
        return ApiResponse::success('Evidence-based assurance register; recorded reviews are not external audit approval.', [
            'reconciliations' => $cuts,
            'reviews' => DB::table('institution_finance_reviews')
                ->whereIn('reconciliation_id', $ids)->orderByDesc('id')->get(),
            'classifications' => DB::table('institution_shared_classifications')
                ->where('institution_id', $v['institution_id'])->orderByDesc('id')->get(),
            'classification_reviews' => DB::table('institution_shared_classification_reviews')
                ->whereIn('classification_id', DB::table('institution_shared_classifications')
                    ->where('institution_id', $v['institution_id'])->select('id'))
                ->orderByDesc('id')->get(),
            'required_review_capacities' => InstitutionalFinanceAssuranceService::CAPACITIES,
            'shared_origination_enabled' => false,
            'external_evidence_requirement' => app()->environment('local', 'testing', 'staging')
                && config('opfin.accounting.defer_institutional_evidence_for_nonproduction', false) === true
                ? 'deferred_for_nonproduction_modelling'
                : 'required_for_live_release',
        ]);
    }

    public function stage(Request $request): JsonResponse
    {
        $v = $request->validate([
            'institution_id' => 'required|integer|exists:institutions,id',
            'ecl_run_id' => 'required|integer|exists:institution_ecl_runs,id',
            'as_of_date' => 'required|date_format:Y-m-d',
            'currency' => 'required|regex:/^[A-Z]{3}$/',
            'provider_statements' => 'required|array|min:1|max:10',
            'provider_statements.*.provider' => 'required|string|max:50',
            'provider_statements.*.balance_minor' => 'required|integer|min:0|max:1000000000000',
            'provider_statements.*.statement_reference' => 'required|string|min:12|max:255',
            'provider_statements.*.statement_sha256' => 'required|regex:/^[a-fA-F0-9]{64}$/',
            'external_payable_minor' => 'required|integer|min:0|max:1000000000000',
            'external_principal_minor' => 'required|integer|min:0|max:1000000000000',
            'external_loan_count' => 'required|integer|min:0|max:1000000',
            'lender_ledger_reference' => 'required|string|min:12|max:255',
            'lender_ledger_sha256' => 'required|regex:/^[a-fA-F0-9]{64}$/',
        ]);
        $this->maker($request, (int) $v['institution_id']);
        $id = $this->invoke(fn () => $this->service->stage($v, $request->user()));
        return ApiResponse::success('Original bank and lender controls staged; a reviewer must resolve all variances.', ['id' => $id], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $this->checker($request);
        $this->invoke(fn () => $this->service->approve($id, $request->user()));
        return ApiResponse::success('Arithmetic matched and independently checked, subject to authentic-source and professional approval.', ['id' => $id]);
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        $this->checker($request);
        $v = $request->validate([
            'capacity' => 'required|in:treasury,finance,risk,independent_model,legal',
            'report_reference' => 'required|string|min:12|max:255',
            'report_sha256' => 'required|regex:/^[a-fA-F0-9]{64}$/',
            'signed_statement' => 'required|string|min:30|max:255',
        ]);
        $this->invoke(fn () => $this->service->sign($id, $v['capacity'], $v['report_reference'],
            $v['report_sha256'], $v['signed_statement'], $request->user()));
        return ApiResponse::success('Reviewer attestation recorded with immutable source references; no automatic release.', ['id' => $id]);
    }

    public function reviewShared(Request $request, int $id): JsonResponse
    {
        $this->checker($request);
        $v = $request->validate([
            'capacity' => 'required|in:treasury,finance,risk,independent_model,legal',
            'review_reference' => 'required|string|min:12|max:255',
            'review_document_sha256' => 'required|regex:/^[a-fA-F0-9]{64}$/',
            'technical_conclusion' => 'required|string|min:50|max:5000',
        ]);
        $this->invoke(fn () => $this->service->reviewSharedClassification($id,
            $v['capacity'], $v['review_reference'], $v['review_document_sha256'],
            $v['technical_conclusion'], $request->user()));
        return ApiResponse::success('A distinct reviewer attested this contractual classification; shared GL activation remains blocked.', ['id' => $id]);
    }

    public function classifyShared(Request $request): JsonResponse
    {
        $this->checker($request);
        $v = $request->validate([
            'credit_offer_id' => 'required|integer|exists:credit_offers,id',
            'reconciliation_id' => 'required|integer|exists:institution_finance_reconciliations,id',
            'contract_form' => 'required|in:direct_joint_creditor,funded_participation,financial_guarantee,first_loss',
            'opfin_share_bps' => 'required|integer|min:1|max:9999',
            'contract_reference' => 'required|string|min:12|max:255',
            'signed_contract_sha256' => 'required|regex:/^[a-fA-F0-9]{64}$/',
            'accounting_policy_reference' => 'required|string|min:12|max:255',
            'technical_memo_sha256' => 'required|regex:/^[a-fA-F0-9]{64}$/',
        ]);
        $offer = CreditOffer::query()->findOrFail($v['credit_offer_id']);
        $id = $this->invoke(fn () => $this->service->proposeSharedClassification($offer,
            $v['reconciliation_id'], $v['contract_form'], $v['opfin_share_bps'],
            $v['contract_reference'], $v['signed_contract_sha256'],
            $v['accounting_policy_reference'], $v['technical_memo_sha256'], $request->user()));
        return ApiResponse::success('Contractual form classified pending real legal/accounting acceptance. Shared origination remains blocked.', ['id' => $id], 201);
    }
}
