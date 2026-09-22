<?php

namespace App\Console\Commands;

use App\Services\EfrisService;
use Illuminate\Console\Command;

class ProcessTaxAndEfris extends Command
{
    protected $signature = 'opfin:tax-efris';

    protected $description = 'Submit pending governed EFRIS documents when production EFRIS integration is enabled';

    public function handle(EfrisService $efris): int
    {
        $summary = $efris->submitPending();
        $this->info(json_encode($summary, JSON_UNESCAPED_SLASHES));

        return ($summary['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
