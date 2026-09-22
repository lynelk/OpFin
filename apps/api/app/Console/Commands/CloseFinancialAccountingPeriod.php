<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FinancialAccountingPeriodService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class CloseFinancialAccountingPeriod extends Command
{
    protected $signature = 'opfin:finance-close
        {start : Period start date YYYY-MM-DD}
        {end : Period end date YYYY-MM-DD}
        {actor_id : Platform admin or operations user ID}
        {--reason= : Documented close reason}';

    protected $description = 'Close an accounting period only after balanced financial-integrity evidence.';

    public function handle(FinancialAccountingPeriodService $periods): int
    {
        try {
            $actor = User::withoutGlobalScopes()->findOrFail((int) $this->argument('actor_id'));
            $this->assertFinanceActor($actor);
            $period = $periods->close(
                Carbon::parse((string) $this->argument('start')),
                Carbon::parse((string) $this->argument('end')),
                $actor,
                (string) $this->option('reason'),
            );

            $this->info(
                'Closed '.$period->period_start->toDateString().' to '.$period->period_end->toDateString().
                ' using integrity evidence '.$period->integrity_evidence_hash.'.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertFinanceActor(User $actor): void
    {
        if (! in_array($actor->role, [User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS], true)) {
            throw new \InvalidArgumentException('Accounting-period close requires a platform admin or operations actor.');
        }
    }
}
