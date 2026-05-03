<?php

use App\Console\Commands\CheckTransactionStatus;
use Illuminate\Support\Facades\Schedule;


Schedule::command(CheckTransactionStatus::class)->everyMinute();
