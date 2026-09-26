<?php

namespace App\Console\Commands;

use App\Services\ClubAccounting\ClubSchedules;
use Illuminate\Console\Command;

class GenerateClubContributionCalls extends Command
{
    protected $signature = 'club:contribution-calls {--limit=100 : Maximum approved plans processed in one run}';

    protected $description = 'Create due club contribution calls from approved plans without collecting money or creating loans.';

    public function handle(ClubSchedules $schedules): int
    {
        $result = $schedules->generateDueCalls((int) $this->option('limit'));
        $this->info('Club contribution plans: '.json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
