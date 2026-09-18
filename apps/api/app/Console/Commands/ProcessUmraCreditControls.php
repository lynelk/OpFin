<?php

namespace App\Console\Commands;

use App\Services\CreditReferenceReportingService;
use App\Services\UmraNplCapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessUmraCreditControls extends Command
{
    protected $signature = 'opfin:umra-credit-controls';

    protected $description = 'Evaluate UMRA NPL controls, complaint SLA and outbound credit-reference reporting';

    public function handle(
        UmraNplCapService $npl,
        CreditReferenceReportingService $reporting,
    ): int {
        $nplSummary = $npl->scan();

        $breached = DB::table('support_cases')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('regulatory_due_at')
            ->where('regulatory_due_at', '<', now())
            ->update([
                'sla_breached' => true,
                'updated_at' => now(),
            ]);

        $reportingSummary = $reporting->submitPending();

        $this->info(json_encode([
            'npl' => $nplSummary,
            'complaint_sla_breaches_marked' => $breached,
            'credit_reporting' => $reportingSummary,
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
