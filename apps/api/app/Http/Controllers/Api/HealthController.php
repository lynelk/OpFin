<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RecordWorkerHeartbeat;
use App\Services\ProductionIntegrationReadinessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    private const HEARTBEAT_FRESH_MINUTES = 12;

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

        $workerHeartbeat = $this->heartbeatStatus(Cache::get(RecordWorkerHeartbeat::CACHE_KEY));
        $schedulerHeartbeat = $this->heartbeatStatus(Cache::get('opfin:operations:scheduler_heartbeat'));
        $operationsReady = $workerHeartbeat['status'] === 'ready' && $schedulerHeartbeat['status'] === 'ready';

        return ApiResponse::success('Service is ready.', [
            'status' => 'ok',
            'service' => 'opfin-backend',
            'database' => 'ready',
            'queue' => (string) config('queue.default'),
            'worker' => $workerHeartbeat,
            'scheduler' => $schedulerHeartbeat,
            'operations' => $operationsReady ? 'ready' : 'degraded',
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

    private function heartbeatStatus(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [
                'status' => 'missing',
                'last_seen_at' => null,
                'age_seconds' => null,
            ];
        }

        try {
            $lastSeen = Carbon::parse($value);
        } catch (Throwable) {
            return [
                'status' => 'invalid',
                'last_seen_at' => null,
                'age_seconds' => null,
            ];
        }

        $ageSeconds = max(0, $lastSeen->diffInSeconds(now()));
        $fresh = $lastSeen->greaterThanOrEqualTo(now()->subMinutes(self::HEARTBEAT_FRESH_MINUTES));

        return [
            'status' => $fresh ? 'ready' : 'stale',
            'last_seen_at' => $lastSeen->toIso8601String(),
            'age_seconds' => $ageSeconds,
        ];
    }
}
