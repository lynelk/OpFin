<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\AssetFinance\AssetFinanceLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetFinanceLifecycleController extends Controller
{
    public function __construct(private readonly AssetFinanceLifecycleService $lifecycle) {}

    public function index(Request $request, FinancialSpace $space): JsonResponse
    {
        return ApiResponse::success('Asset-finance cases.', ['cases' => $this->lifecycle->list($space, $request->user())]);
    }

    public function show(Request $request, FinancialSpace $space, int $case): JsonResponse
    {
        return ApiResponse::success('Asset-finance case.', ['case' => $this->lifecycle->show($space, $request->user(), $case)]);
    }

    public function store(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'vertical' => ['required', Rule::in(array_keys(config('asset_finance.verticals')))],
            'asset_passport_id' => ['required', 'integer', 'exists:asset_passports,id'],
            'supplier_profile_id' => ['required', 'integer', 'exists:supplier_profiles,id'],
            'financial_product_id' => ['required', 'integer', 'exists:financial_products,id'],
            'capital_mandate_id' => ['required', 'integer', 'exists:capital_mandates,id'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'asset_price_minor' => ['required', 'integer', 'min:1'],
            'customer_contribution_minor' => ['nullable', 'integer', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:84'],
        ]);

        return ApiResponse::success('Asset-finance case submitted.', [
            'case' => $this->lifecycle->create($space, $request->user(), $data, $this->key($request)),
        ], 201);
    }

    public function evidence(Request $request, FinancialSpace $space, int $case): JsonResponse
    {
        $data = $request->validate([
            'evidence_type' => ['required', Rule::in(config('asset_finance.evidence_types'))],
            'evidence_reference' => ['required', 'string', 'max:200'],
            'metadata' => ['nullable', 'array'],
        ]);

        return ApiResponse::success('Asset-finance evidence submitted.', [
            'case' => $this->lifecycle->submitEvidence($space, $request->user(), $case, $data),
        ], 201);
    }

    public function workQueue(): JsonResponse
    {
        return ApiResponse::success('Asset-finance work queue.', ['cases' => $this->lifecycle->workQueue()]);
    }

    public function verifyEvidence(Request $request, int $case, int $evidence): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected'])]]);

        return ApiResponse::success('Asset-finance evidence reviewed.', [
            'case' => $this->lifecycle->verifyEvidence($request->user(), $case, $evidence, $data),
        ]);
    }

    public function review(Request $request, int $case): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'decline'])],
            'reason' => ['required', 'string', 'max:240'],
            'financing_arrangement_id' => ['required_if:decision,approve', 'nullable', 'integer', 'exists:financing_arrangements,id'],
        ]);

        return ApiResponse::success('Asset-finance review recorded.', [
            'case' => $this->lifecycle->review($request->user(), $case, $data, $this->key($request)),
        ]);
    }

    public function createSettlement(Request $request, int $case): JsonResponse
    {
        $data = $request->validate([
            'settlement_type' => ['required', Rule::in(['customer_contribution', 'supplier_settlement'])],
            'amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        return ApiResponse::success('Settlement instruction recorded pending provider confirmation.', [
            'case' => $this->lifecycle->createSettlement($request->user(), $case, $data, $this->key($request)),
        ], 201);
    }

    public function transitionSettlement(Request $request, int $settlement): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['provider_confirmed', 'reconciled', 'reversed'])],
            'provider_reference' => ['required_if:status,provider_confirmed', 'nullable', 'string', 'max:200'],
            'reconciliation_reference' => ['required_if:status,reconciled', 'nullable', 'string', 'max:200'],
            'reason' => ['required_if:status,reversed', 'nullable', 'string', 'max:240'],
        ]);

        return ApiResponse::success('Settlement state recorded.', [
            'case' => $this->lifecycle->transitionSettlement($request->user(), $settlement, $data),
        ]);
    }

    public function activate(Request $request, int $case): JsonResponse
    {
        $data = $request->validate(['activation_reference' => ['required', 'string', 'max:200']]);

        return ApiResponse::success('Asset-finance case activated.', [
            'case' => $this->lifecycle->activate($request->user(), $case, $data, $this->key($request)),
        ]);
    }

    public function transition(Request $request, int $case): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'arrears', 'hardship', 'recovery', 'repossessed', 'resale_pending', 'trade_in_pending', 'settled', 'closed'])],
            'reason' => ['required', 'string', 'max:240'],
            'evidence_reference' => ['required', 'string', 'max:200'],
        ]);

        return ApiResponse::success('Asset-finance lifecycle state recorded.', [
            'case' => $this->lifecycle->transition($request->user(), $case, $data, $this->key($request)),
        ]);
    }

    private function key(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        abort_if($key === '' || strlen($key) > 160, 422, 'An Idempotency-Key header (up to 160 characters) is required.');

        return $key;
    }
}
