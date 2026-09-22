<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PartnerReportingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PartnerReportingController extends Controller
{
    public function __construct(private readonly PartnerReportingService $reports) {}

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
