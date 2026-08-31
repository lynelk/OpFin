<?php

namespace App\Console\Commands;

use App\Services\ExperiencePlatformService;
use Illuminate\Console\Command;

class EvaluateMoneyAutopilot extends Command
{
    protected $signature = 'opfin:money-autopilot {--user=}';

    protected $description = 'Evaluate user-authorised Money Autopilot rules without bypassing provider or settlement controls.';

    public function handle(ExperiencePlatformService $service): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $result = $service->evaluateMoneyAutopilotRules($userId);
        $this->info('Evaluated '.$result['evaluated'].' Money Autopilot rule(s).');

        return self::SUCCESS;
    }
}
