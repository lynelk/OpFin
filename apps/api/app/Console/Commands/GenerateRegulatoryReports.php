<?php

namespace App\Console\Commands;

use App\Services\RegulatoryReportingService;
use Illuminate\Console\Command;

class GenerateRegulatoryReports extends Command
{
    protected $signature = 'opfin:regulatory-reports';

    protected $description = 'Generate and validate regulator-ready compliance evidence packs';

    public function handle(RegulatoryReportingService $service): int
    {
        $reports = $service->generateScheduledSet();
        $this->info(count($reports).' regulatory reports generated and validated.');

        return self::SUCCESS;
    }
}
