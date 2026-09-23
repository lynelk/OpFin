<?php

namespace App\Console\Commands;

use App\Services\CommercialInsightsService;
use App\Services\ProgrammeDeliveryService;
use Illuminate\Console\Command;

class MaintainProgrammeMeasurement extends Command
{
    protected $signature = 'opfin:programme-measurement:maintain';

    protected $description = 'Generate programme follow-ups, mark due/overdue items, and refresh commercial graduation evidence.';

    public function handle(
        ProgrammeDeliveryService $delivery,
        CommercialInsightsService $commercial,
    ): int {
        $followUps = $delivery->generateFollowUps();
        $graduations = $commercial->evaluateGraduations();

        $this->info(sprintf(
            'Programme measurement maintained: %d follow-ups generated; %d participants evaluated; %d graduated.',
            $followUps['generated'] ?? 0,
            $graduations['evaluated'] ?? 0,
            $graduations['graduated'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
