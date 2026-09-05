<?php

namespace Tests\Feature;

use App\Jobs\QueueWorkerHeartbeat;
use App\Models\User;
use App\Services\AutonomousOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutonomousOperationsEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_does_not_recursively_embed_previous_run_summaries(): void
    {
        DB::table('autopilot_runs')->insert([
            'status' => 'completed',
            'trigger' => 'test',
            'observations' => 3,
            'actions_executed' => 1,
            'exceptions_created' => 2,
            'summary' => json_encode([
                'last_run' => [
                    'summary' => str_repeat('historical-payload-', 200),
                ],
            ]),
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = app(AutonomousOperationsService::class)->summary();

        $this->assertNotNull($summary['last_run']);
        $this->assertArrayNotHasKey('summary', (array) $summary['last_run']);
        $this->assertSame(3, $summary['last_run']->observations);
    }

    public function test_autonomous_scans_process_more_than_one_batch_without_loading_every_row(): void
    {
        $user = User::factory()->create();
        $now = now();
        $rows = [];

        for ($index = 1; $index <= 205; $index++) {
            $rows[] = [
                'user_id' => $user->id,
                'national_id' => 'CF'.str_pad((string) $index, 12, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('kyc_cases')->insert($rows);

        $result = app(AutonomousOperationsService::class)->run('batch-regression-test');

        $this->assertSame(205, DB::table('autopilot_work_items')->where('domain', 'kyc')->count());
        $this->assertDatabaseHas('autopilot_runs', [
            'id' => $result['run_id'],
            'status' => 'completed',
            'observations' => 205,
            'exceptions_created' => 205,
        ]);
    }

    public function test_queue_worker_heartbeat_records_a_bounded_last_seen_marker(): void
    {
        Cache::forget('opfin:queue_worker_last_seen');

        (new QueueWorkerHeartbeat)->handle();

        $lastSeen = Cache::get('opfin:queue_worker_last_seen');
        $this->assertIsString($lastSeen);
        $this->assertNotSame('', $lastSeen);
    }
}
