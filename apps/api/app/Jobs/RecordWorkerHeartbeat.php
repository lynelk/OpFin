<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RecordWorkerHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY = 'opfin:operations:worker_heartbeat';

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, now()->toIso8601String(), now()->addMinutes(20));
    }

    public function uniqueId(): string
    {
        return 'opfin-worker-heartbeat';
    }
}
