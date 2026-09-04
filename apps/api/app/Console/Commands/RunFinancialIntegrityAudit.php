<?php

namespace App\Console\Commands;

use App\Services\FinancialIntegrityService;
use Illuminate\Console\Command;

class RunFinancialIntegrityAudit extends Command
{
    protected $signature = 'opfin:integrity-audit';

    protected $description = 'Continuously verify ledger balance, payment reconciliation, and duplicate-funds indicators';

    public function handle(FinancialIntegrityService $service): int
    {
        $run = $service->run('scheduled');
        $this->info("Integrity run {$run->id}: {$run->status}");

        return $run->status === 'critical' ? self::FAILURE : self::SUCCESS;
    }
}
