<?php

use App\Console\Commands\CheckTransactionStatus;
use App\Console\Commands\EvaluateMoneyAutopilot;
use App\Console\Commands\GenerateRegulatoryReports;
use App\Console\Commands\ReconcileLongRangeFinancialIntents;
use App\Console\Commands\RunFinancialIntegrityAudit;
use App\Console\Commands\RunPlatformAutopilot;
use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    Cache::put('opfin:operations:scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(20));
})->name('opfin-scheduler-heartbeat')->everyFiveMinutes()->withoutOverlapping(10);

Schedule::job(new RecordWorkerHeartbeat)
    ->name('opfin-worker-heartbeat-dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command(CheckTransactionStatus::class)->everyMinute();
Schedule::command(ReconcileLongRangeFinancialIntents::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(RunFinancialIntegrityAudit::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(RunPlatformAutopilot::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(EvaluateMoneyAutopilot::class)->hourly()->withoutOverlapping();
Schedule::command(GenerateRegulatoryReports::class)->dailyAt('01:15')->withoutOverlapping();
