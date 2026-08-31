<?php

namespace App\Console\Commands;

use App\Services\LongRangeFinancialActionService;
use Illuminate\Console\Command;

class ReconcileLongRangeFinancialIntents extends Command
{
    protected $signature = 'long-range:reconcile-financial-intents';

    protected $description = 'Reconcile long-range financial action intents against governed CPay/mobile-money finality.';

    public function handle(LongRangeFinancialActionService $service): int
    {
        $result = $service->reconcile();
        $this->info(sprintf(
            'Long-range financial intents reconciled: processed=%d settled=%d failed=%d',
            $result['processed'],
            $result['settled'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
