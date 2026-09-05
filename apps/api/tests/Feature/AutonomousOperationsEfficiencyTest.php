<?php

namespace Tests\Feature;

use App\Jobs\RecordWorkerHeartbeat;
use App\Models\User;
use App\Services\AutonomousOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutonomousOperationsEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_autopilot_summaries_do_not_recursively_embed_previous_summaries(): void
    {
        $service = app(AutonomousOperationsService::class);

        $service->run('test');
        $second = $service->run('test');
        $third = $service->run('test');

        foreach ([$second['run_id'], $third['run_id']] as $runId) {
            $stored = (string) DB::table('autopilot_runs')->where('id', $runId)->value('summary');
            $summary = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);

            $this->assertIsArray($summary['last_run']);
            $this->assertArrayNotHasKey('summary', $summary['last_run']);
            $this->assertLessThan(4096, strlen($stored));
        }
    }

    public function test_autopilot_processes_large_review_backlogs_in_bounded_batches(): void
    {
        $user = User::factory()->create();
        $now = now();
        $rows = [];

        for ($index = 1; $index <= 225; $index++) {
            $rows[] = [
                'user_id' => $user->id,
                'provider' => 'manual',
                'national_id' => sprintf('TEST-NIN-%04d', $index),
                'status' => 'submitted',
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 75) as $batch) {
            DB::table('kyc_cases')->insert($batch);
        }

        $result = app(AutonomousOperationsService::class)->run('batch-test');

        $this->assertSame(225, $result['open_exceptions']);
        $this->assertDatabaseCount('autopilot_work_items', 225);
        $this->assertDatabaseHas('autopilot_runs', [
            'id' => $result['run_id'],
            'status' => 'completed',
            'observations' => 225,
            'exceptions_created' => 225,
        ]);
    }

    public function test_readiness_reports_worker_and_scheduler_heartbeat_freshness(): void
    {
        Cache::forget(RecordWorkerHeartbeat::CACHE_KEY);
        Cache::forget('opfin:operations:scheduler_heartbeat');

        $this->getJson('/api/health/ready')
            ->assertOk()
            ->assertJsonPath('data.worker.status', 'missing')
            ->assertJsonPath('data.scheduler.status', 'missing')
            ->assertJsonPath('data.operations', 'degraded');

        app(RecordWorkerHeartbeat::class)->handle();
        Cache::put('opfin:operations:scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(20));

        $this->getJson('/api/health/ready')
            ->assertOk()
            ->assertJsonPath('data.worker.status', 'ready')
            ->assertJsonPath('data.scheduler.status', 'ready')
            ->assertJsonPath('data.operations', 'ready');
    }
}
