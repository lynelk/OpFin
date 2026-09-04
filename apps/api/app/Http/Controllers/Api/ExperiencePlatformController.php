<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExperiencePlatformService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ExperiencePlatformController extends Controller
{
    public function __construct(private ExperiencePlatformService $service) {}

    public function activation(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->activation($request->user()));
    }

    public function saveActivation(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->saveActivation($request->user(), $request->all()));
    }

    public function moneyAutopilot(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->moneyAutopilot($request->user()));
    }

    public function createMoneyAutopilotRule(Request $request): JsonResponse
    {
        return $this->run(fn () => ['rule' => $this->service->createMoneyAutopilotRule($request->user(), $request->all())]);
    }

    public function setMoneyAutopilotRuleStatus(Request $request, int $rule): JsonResponse
    {
        return $this->run(fn () => ['rule' => $this->service->setMoneyAutopilotRuleStatus($request->user(), $rule, $request->string('status')->toString())]);
    }

    public function investments(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->investmentWorkspace($request->user()));
    }

    public function saveSuitability(Request $request): JsonResponse
    {
        return $this->run(fn () => ['suitability' => $this->service->saveSuitability($request->user(), $request->all())]);
    }

    public function createInvestmentOrder(Request $request, int $product): JsonResponse
    {
        return $this->run(fn () => ['order' => $this->service->createInvestmentOrder($request->user(), $product, $request->all())]);
    }

    public function employerDashboard(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->employerDashboard($request->user()));
    }

    public function createEmployerProgram(Request $request): JsonResponse
    {
        return $this->run(fn () => ['program' => $this->service->createEmployerProgram($request->user(), $request->all())]);
    }

    public function createInvestmentProduct(Request $request): JsonResponse
    {
        return $this->run(fn () => ['product' => $this->service->createInvestmentProduct($request->all(), 'USER:'.$request->user()->id)]);
    }

    public function approveInvestmentProduct(Request $request, int $product): JsonResponse
    {
        return $this->run(fn () => ['product' => $this->service->approveInvestmentProduct($product, 'USER:'.$request->user()->id)]);
    }

    private function run(callable $callback): JsonResponse
    {
        try {
            return ApiResponse::success('OK.', $callback());
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }
}
