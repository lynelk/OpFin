<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RecordWorkerHeartbeat;
use App\Services\FinancialReadinessService;
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
    private const HEARTBEAT_FRESH_MINUTES = 12;

    public function __construct(
        private readonly ProductionIntegrationReadinessService $integrations,
        private readonly FinancialReadinessService $financialReadiness,
    ) {}

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
            'queue' => $this->queueReadiness(),
            'operations' => [
                'status' => $operationsReady ? 'ready' : 'warming',
                'worker' => $workerHeartbeat,
                'scheduler' => $schedulerHeartbeat,
            ],
            'integration_readiness' => $this->integrations->report()['required_integrations_ready'] ? 'ready' : 'blocked',
        ]);
    }

    public function financialReady(): JsonResponse
    {
        $report = $this->financialReadiness->report();
        $checks = collect($report['checks'])->map(function (array $check, string $name): array {
            $public = ['status' => $check['status'] ?? 'blocked'];

            if ($name === 'financial_integrity') {
                $public['fresh'] = (bool) ($check['fresh'] ?? false);
                $public['freshness_limit_minutes'] = (int) ($check['freshness_limit_minutes'] ?? 0);
                $public['latest_completed_at'] = data_get($check, 'latest_run.completed_at');
                $public['open_critical_alerts'] = (int) ($check['open_critical_alerts'] ?? 0);
                $public['open_high_alerts'] = (int) ($check['open_high_alerts'] ?? 0);
            }

            return $public;
        })->all();

        $publicReport = [
            'financial_operations_ready' => (bool) $report['financial_operations_ready'],
            'status' => $report['status'],
            'checks' => $checks,
            'rules' => $report['rules'],
        ];

        return $report['financial_operations_ready']
            ? ApiResponse::success('Financial operations are ready.', $publicReport)
            : ApiResponse::error('Financial operations are blocked.', 503, $publicReport);
    }

    public function integrations(): JsonResponse
    {
        $report = $this->integrations->report();
        $public = [
            'production_ready' => (bool) $report['production_ready'],
            'required_integrations_ready' => (bool) $report['required_integrations_ready'],
            'integrations' => collect($report['integrations'])->map(fn (array $integration) => [
                'status' => $integration['status'] ?? 'blocked',
                'required' => (bool) ($integration['required'] ?? false),
                'purpose' => $integration['purpose'] ?? null,
            ])->all(),
        ];

        return ApiResponse::success(
            $report['production_ready'] ? 'Required production integrations are configured.' : 'One or more required production integrations still need configuration.',
            $public,
        );
    }

    public function show(): JsonResponse
    {
        return $this->ready();
    }

    private function heartbeatStatus(mixed $lastSeen): array
    {
        $ageSeconds = null;

        if (is_string($lastSeen) && $lastSeen !== '') {
            try {
                $ageSeconds = (int) Carbon::parse($lastSeen)->diffInSeconds(now());
            } catch (Throwable) {
                $lastSeen = null;
            }
        } else {
            $lastSeen = null;
        }

        $status = match (true) {
            $ageSeconds !== null && $ageSeconds <= self::HEARTBEAT_FRESH_MINUTES * 60 => 'ready',
            $ageSeconds !== null => 'stale',
            default => 'warming',
        };

        return [
            'status' => $status,
            'last_seen_at' => $lastSeen,
            'heartbeat_age_seconds' => $ageSeconds,
        ];
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
