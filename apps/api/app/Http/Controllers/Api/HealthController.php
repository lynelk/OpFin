<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductionIntegrationReadinessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __construct(private readonly ProductionIntegrationReadinessService $integrations) {}

    public function live(): JsonResponse
    {
        return ApiResponse::success('Service is alive.', [
            'status' => 'ok',
            'service' => 'opfin-backend',
        ]);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Service is not ready.', 503, [
                'status' => 'degraded',
                'service' => 'opfin-backend',
                'database' => 'unavailable',
            ]);
        }

        return ApiResponse::success('Service is ready.', [
            'status' => 'ok',
            'service' => 'opfin-backend',
            'database' => 'ready',
            'queue' => (string) config('queue.default'),
            'integration_readiness' => $this->integrations->report()['required_integrations_ready'] ? 'ready' : 'blocked',
        ]);
    }

    public function integrations(): JsonResponse
    {
        $report = $this->integrations->report();

        return ApiResponse::success(
            $report['production_ready'] ? 'Required production integrations are configured.' : 'One or more required production integrations still need configuration.',
            $report,
        );
    }

    public function show(): JsonResponse
    {
        return $this->ready();
    }
}
