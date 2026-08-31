<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutonomousOperationsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutonomousOperationsController extends Controller
{
    public function __construct(private AutonomousOperationsService $service) {}

    public function summary(): JsonResponse
    {
        return ApiResponse::success('Autonomous operations summary.', $this->service->summary());
    }

    public function queue(Request $request): JsonResponse
    {
        return ApiResponse::success('Autonomous operations work queue.', [
            'items' => $this->service->workQueue($request->string('domain')->toString() ?: null),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        return ApiResponse::success('Autopilot run completed.', $this->service->run('manual:USER:'.$request->user()->id));
    }

    public function resolve(Request $request, int $item): JsonResponse
    {
        $request->validate([
            'resolution' => ['nullable', 'in:resolved,dismissed'],
        ]);

        $resolved = $this->service->resolve(
            $item,
            'USER:'.$request->user()->id,
            $request->string('resolution')->toString() ?: 'resolved'
        );

        return ApiResponse::success('Work item updated.', ['item' => $resolved]);
    }
}
