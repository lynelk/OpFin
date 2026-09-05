<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductionIntegrationReadinessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'queue' => $this->queueReadiness(),
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

    private function queueReadiness(): array
    {
        $lastSeen = Cache::get('opfin:queue_worker_last_seen');
        $ageSeconds = null;

        if (is_string($lastSeen) && $lastSeen !== '') {
            try {
                $ageSeconds = (int) Carbon::parse($lastSeen)->diffInSeconds(now());
            } catch (Throwable) {
                $lastSeen = null;
            }
        }

        $status = match (true) {
            $ageSeconds !== null && $ageSeconds <= 600 => 'ready',
            $ageSeconds !== null => 'stale',
            default => 'warming',
        };

        return [
            'driver' => (string) config('queue.default'),
            'worker' => $status,
            'last_seen_at' => $lastSeen,
            'heartbeat_age_seconds' => $ageSeconds,
            'backlog' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
        ];
    }
}
