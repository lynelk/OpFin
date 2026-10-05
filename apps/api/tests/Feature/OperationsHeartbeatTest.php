<?php

namespace Tests\Feature;

use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OperationsHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_heartbeats_make_operations_ready(): void
    {
        $this->getJson('/api/health/ready')->assertOk()->assertJsonPath('data.operations.status', 'warming');

        $this->artisan('schedule:list')->assertSuccessful();
        $events = collect(app(Schedule::class)->events());
        foreach (['opfin-scheduler-heartbeat', 'opfin-worker-heartbeat-dispatch'] as $name) {
            $event = $events->first(fn (Event $event): bool => $event->description === $name);
            $this->assertNotNull($event, "{$name} must be scheduled");
            $event->run($this->app);
        }

        $this->getJson('/api/health/ready')->assertOk()
            ->assertJsonPath('data.operations.scheduler.status', 'ready')
            ->assertJsonPath('data.operations.worker.status', 'ready')
            ->assertJsonPath('data.operations.status', 'ready');
    }

    public function test_heartbeats_older_than_the_freshness_window_are_stale(): void
    {
        Cache::put('opfin:operations:scheduler_heartbeat', now()->subMinutes(30)->toIso8601String(), now()->addHour());
        Cache::put(RecordWorkerHeartbeat::CACHE_KEY, now()->subMinutes(30)->toIso8601String(), now()->addHour());

        $this->getJson('/api/health/ready')->assertOk()
            ->assertJsonPath('data.operations.scheduler.status', 'stale')
            ->assertJsonPath('data.operations.worker.status', 'stale')
            ->assertJsonPath('data.operations.status', 'warming');
    }
}
