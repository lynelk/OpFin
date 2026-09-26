<?php

namespace App\Console\Commands;

use App\Services\FinancialReadinessService;
use Illuminate\Console\Command;

class CheckFinancialReadiness extends Command
{
    protected $signature = 'opfin:financial-readiness {--json : Emit machine-readable JSON}';

    protected $description = 'Fail closed unless OpFin is ready for production-equivalent financial operations or UAT.';

    public function handle(FinancialReadinessService $readiness): int
    {
        $report = $readiness->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Financial readiness: '.strtoupper($report['status']));
            foreach ($report['checks'] as $name => $check) {
                $this->line(sprintf('%-36s %s', $name, strtoupper((string) ($check['status'] ?? 'unknown'))));
            }
        }

        return $report['financial_operations_ready'] ? self::SUCCESS : self::FAILURE;
    }
}
