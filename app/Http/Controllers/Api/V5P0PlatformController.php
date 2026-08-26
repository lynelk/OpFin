<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\V5P0PlatformService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class V5P0PlatformController extends Controller
{
    public function __construct(private V5P0PlatformService $service) {}

    public function security(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->security($request->user()));
    }

    public function updateSecurity(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->updateSecurity($request->user(), $request->all(), $this->actor($request), $request->ip()));
    }

    public function creditBuilder(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->creditBuilder($request->user()));
    }

    public function saveCreditBuilder(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->saveCreditBuilder($request->user(), $request->all()));
    }

    public function hardship(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->hardship($request->user()));
    }

    public function openHardship(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->openHardship($request->user(), $request->all(), $this->actor($request)));
    }

    public function passport(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->passport($request->user()));
    }

    public function reconciliation(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->reconciliation($request->user()));
    }

    public function approveHardship(Request $request, int $case): JsonResponse
    {
        return $this->run(fn () => $this->service->approveHardship($case, $request->input('approved_relief', []), $this->actor($request)));
    }

    public function createProduct(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->createProduct($request->all(), $this->actor($request)));
    }

    public function transitionProduct(Request $request, int $product): JsonResponse
    {
        return $this->run(fn () => $this->service->productTransition($product, $request->string('status')->toString(), $this->actor($request)));
    }

    public function createRule(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->createRule($request->all(), $this->actor($request)));
    }

    public function approveRule(Request $request, int $rule): JsonResponse
    {
        return $this->run(fn () => $this->service->approveRule($rule, $this->actor($request)));
    }

    public function evaluateRules(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->evaluateRules($request->input('context', [])));
    }

    public function createWorkflow(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->service->createWorkflow($request->all(), $this->actor($request)));
    }

    public function approveWorkflow(Request $request, int $workflow): JsonResponse
    {
        return $this->run(fn () => $this->service->approveWorkflow($workflow, $this->actor($request)));
    }

    public function startWorkflow(Request $request, int $workflow): JsonResponse
    {
        return $this->run(fn () => $this->service->startWorkflow(
            $workflow,
            $request->user(),
            $request->string('subject_type')->toString(),
            $request->string('subject_reference')->toString(),
            $request->input('context', []),
        ));
    }

    public function transitionWorkflow(Request $request, int $run): JsonResponse
    {
        return $this->run(fn () => $this->service->transitionWorkflow($run, $request->string('to_state')->toString(), $this->actor($request), $request->input('context', [])));
    }

    private function actor(Request $request): string
    {
        return 'USER:'.($request->user()?->id ?? 'system');
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
