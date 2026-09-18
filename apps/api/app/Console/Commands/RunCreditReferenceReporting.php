<?php

namespace App\Console\Commands;

use App\Services\CreditReferenceReportingService;
use Illuminate\Console\Command;

class RunCreditReferenceReporting extends Command
{
    protected $signature = 'opfin:credit-reference-reporting {--submit-limit=200}';

    protected $description = 'Stage and submit complete positive and negative borrower information to the configured credit-reference mechanism';

    public function handle(CreditReferenceReportingService $service): int
    {
        $staged = $service->stagePortfolioSnapshot();
        $result = $service->submitDue((int) $this->option('submit-limit'));

        $this->info(sprintf(
            'CRB reporting: %d portfolio records staged, %d submitted, %d failed, %d skipped.',
            $staged,
            $result['submitted'],
            $result['failed'],
            $result['skipped'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
