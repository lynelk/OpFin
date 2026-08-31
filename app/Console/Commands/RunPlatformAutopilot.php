<?php

namespace App\Console\Commands;

use App\Services\AutonomousOperationsService;
use Illuminate\Console\Command;

class RunPlatformAutopilot extends Command
{
    protected $signature = 'opfin:autopilot {--trigger=scheduled}';

    protected $description = 'Observe operational domains, create exception work items, and execute safe autonomous actions.';

    public function handle(AutonomousOperationsService $service): int
    {
        $result = $service->run((string) $this->option('trigger'));
        $this->info(sprintf(
            'Autopilot run %s complete: %s human exceptions, %s automatic items.',
            $result['run_id'],
            $result['open_exceptions'],
            $result['open_automatic_items']
        ));

        return self::SUCCESS;
    }
}
