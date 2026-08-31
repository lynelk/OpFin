<?php

use App\Console\Commands\CheckTransactionStatus;
use App\Console\Commands\EvaluateMoneyAutopilot;
use App\Console\Commands\GenerateRegulatoryReports;
use App\Console\Commands\RunFinancialIntegrityAudit;
use App\Console\Commands\RunPlatformAutopilot;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CheckTransactionStatus::class)->everyMinute();
Schedule::command(RunFinancialIntegrityAudit::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(RunPlatformAutopilot::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(EvaluateMoneyAutopilot::class)->hourly()->withoutOverlapping();
Schedule::command(GenerateRegulatoryReports::class)->dailyAt('01:15')->withoutOverlapping();
