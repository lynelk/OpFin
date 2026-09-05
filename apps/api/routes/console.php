<?php

use App\Console\Commands\EvaluateMoneyAutopilot;
use App\Console\Commands\GenerateRegulatoryReports;
use App\Console\Commands\ReconcileLongRangeFinancialIntents;
use App\Console\Commands\RunFinancialIntegrityAudit;
use App\Console\Commands\RunPlatformAutopilot;
use App\Jobs\QueueWorkerHeartbeat;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new QueueWorkerHeartbeat)->everyFiveMinutes()->onOneServer();
Schedule::command(ReconcileLongRangeFinancialIntents::class)->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command(RunFinancialIntegrityAudit::class)->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command(RunPlatformAutopilot::class)->everyFifteenMinutes()->withoutOverlapping(15)->onOneServer();
Schedule::command(EvaluateMoneyAutopilot::class)->hourly()->withoutOverlapping(60)->onOneServer();
Schedule::command(GenerateRegulatoryReports::class)->dailyAt('01:15')->withoutOverlapping(120)->onOneServer();
