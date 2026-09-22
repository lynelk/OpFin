<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PartnerReportingService;
use App\Services\ServiceEconomicsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PartnerReportingController extends Controller
{
    public function __construct(
        private readonly PartnerReportingService $reports,
        private readonly ServiceEconomicsService $economics,
    ) {}

    public function recordServiceEconomics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'partner_product_id' => ['nullable', 'integer', 'exists:partner_products,id'],
            'commercial_agreement_id' => ['nullable', 'integer', 'exists:commercial_agreements,id'],
            'service_code' => ['required', 'string', 'max:80'],
            'capability_code' => ['nullable', 'string', 'max:120'],
            'provider' => ['required', 'string', 'max:120'],
            'route' => ['required', 'string', 'max:40'],
            'environment' => ['nullable', 'string', 'max:40'],
            'request_reference' => ['required', 'string', 'max:200'],
            'provider_reference' => ['nullable', 'string', 'max:200'],
            'status' => ['required', 'string', 'max:40'],
            'currency' => ['nullable', 'string', 'size:3'],
            'provider_gross_cost_minor' => ['nullable', 'integer'],
            'provider_discount_minor' => ['nullable', 'integer'],
            'provider_net_cost_minor' => ['nullable', 'integer'],
            'customer_service_charge_minor' => ['nullable', 'integer'],
            'customer_platform_fee_minor' => ['nullable', 'integer'],
            'partner_commission_minor' => ['nullable', 'integer'],
            'cito_platform_fee_minor' => ['nullable', 'integer'],
            'opfin_platform_fee_minor' => ['nullable', 'integer'],
            'tax_amount_minor' => ['nullable', 'integer'],
            'net_settlement_to_provider_minor' => ['nullable', 'integer'],
            'gross_revenue_minor' => ['nullable', 'integer'],
            'net_revenue_minor' => ['nullable', 'integer'],
            'gross_margin_minor' => ['nullable', 'integer'],
            'price_book_version' => ['nullable', 'string', 'max:120'],
            'contract_version' => ['nullable', 'string', 'max:120'],
            'reconciliation_reference' => ['nullable', 'string', 'max:200'],
            'metadata' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
            'reconciled_at' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(
            'Service economics event recorded.',
            ['event' => $this->economics->record($validated)],
            201,
        );
    }

    public function serviceEconomics(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $filters = $request->validate([
            'service_code' => ['nullable', 'string', 'max:80'],
            'provider' => ['nullable', 'string', 'max:120'],
            'route' => ['nullable', 'string', 'max:40'],
            'environment' => ['nullable', 'string', 'max:40'],
        ]);

        return ApiResponse::success('Service economics report generated.', $this->reports->serviceEconomics($start, $end, $filters));
    }

    public function capitalLoanBook(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);

        return ApiResponse::success('Capital and loan-book performance report generated.', $this->reports->capitalAndLoanBook($start, $end));
    }

    public function insurance(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);

        return ApiResponse::success('Insurance product, premium and claims report generated.', $this->reports->insurance($start, $end));
    }

    public function savingsInvestments(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);

        return ApiResponse::success('Savings and investment partner report generated.', $this->reports->savingsAndInvestments($start, $end));
    }

    public function employmentBehaviour(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);

        return ApiResponse::success('Positive employment behaviour report generated.', $this->reports->employmentPositiveBehaviour($start, $end));
    }

    public function financialAccountBehaviour(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);

        return ApiResponse::success('Financial account behaviour report generated.', $this->reports->financialAccountBehaviour($start, $end));
    }

    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $start = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfMonth();
        $end = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        return [$start, $end];
    }
}
