<?php

use App\Console\Commands\AnalysePendingStatements;
use App\Console\Commands\EvaluateMoneyAutopilot;
use App\Console\Commands\GenerateRegulatoryReports;
use App\Console\Commands\MaintainProgrammeMeasurement;
use App\Console\Commands\ProcessTaxAndEfris;
use App\Console\Commands\ProcessUmraCreditControls;
use App\Console\Commands\PurgeStatementOriginals;
use App\Console\Commands\ReconcileLongRangeFinancialIntents;
use App\Console\Commands\RunFinancialIntegrityAudit;
use App\Console\Commands\RunPlatformAutopilot;
use App\Jobs\QueueWorkerHeartbeat;
use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Heartbeats read by /api/health/ready. The scheduler writes its own; the worker proves it is
// consuming the queue by running RecordWorkerHeartbeat. Restored after being dropped in a merge.
Schedule::call(function (): void {
    Cache::put('opfin:operations:scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(20));
})->name('opfin-scheduler-heartbeat')->everyFiveMinutes()->withoutOverlapping(10)->onOneServer();
Schedule::job(new RecordWorkerHeartbeat)->name('opfin-worker-heartbeat-dispatch')->everyFiveMinutes()->withoutOverlapping(10)->onOneServer();
Schedule::job(new QueueWorkerHeartbeat)->everyFiveMinutes()->onOneServer();
Schedule::command(ReconcileLongRangeFinancialIntents::class)->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command(RunFinancialIntegrityAudit::class)->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command(RunPlatformAutopilot::class)->everyFifteenMinutes()->withoutOverlapping(15)->onOneServer();
Schedule::command(EvaluateMoneyAutopilot::class)->hourly()->withoutOverlapping(60)->onOneServer();
Schedule::command(GenerateRegulatoryReports::class)->dailyAt('01:15')->withoutOverlapping(120)->onOneServer();
Schedule::command(ProcessUmraCreditControls::class)->hourly()->withoutOverlapping(55)->onOneServer();
Schedule::command(ProcessTaxAndEfris::class)->hourly()->withoutOverlapping(55)->onOneServer();
Schedule::command(AnalysePendingStatements::class)->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command(PurgeStatementOriginals::class)->dailyAt('02:30')->withoutOverlapping(60)->onOneServer();

Schedule::command(MaintainProgrammeMeasurement::class)->hourly()->withoutOverlapping(55)->onOneServer();
