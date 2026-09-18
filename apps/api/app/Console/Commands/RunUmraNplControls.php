<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\CreditReferenceReportingService;
use App\Services\NplRecoveryPolicyService;
use Illuminate\Console\Command;

class RunUmraNplControls extends Command
{
    protected $signature = 'opfin:umra-npl-controls';

    protected $description = 'Evaluate non-performing-loan recovery ceilings and stage negative credit-information updates';

    public function handle(
        NplRecoveryPolicyService $policy,
        CreditReferenceReportingService $creditReporting,
    ): int {
        $evaluated = 0;
        $negative = 0;

        Loan::withoutGlobalScopes()
            ->whereNotNull('disbursed_at')
            ->whereNotIn('status', ['Cleared', 'Reversed', 'Cancelled', 'Rejected'])
            ->orderBy('id')
            ->chunkById(200, function ($loans) use ($policy, $creditReporting, &$evaluated, &$negative) {
                foreach ($loans as $loan) {
                    $control = $policy->evaluate($loan);
                    $evaluated++;

                    if ($control->non_performing_at && data_get($control->metadata, 'currently_non_performing') === true) {
                        $creditReporting->stageLoan(
                            $loan,
                            'non_performing',
                            $control->non_performing_at->toDateString(),
                        );
                        $negative++;
                    }
                }
            });

        $this->info("UMRA NPL controls: {$evaluated} loans evaluated; {$negative} negative credit-information updates staged.");

        return self::SUCCESS;
    }
}
