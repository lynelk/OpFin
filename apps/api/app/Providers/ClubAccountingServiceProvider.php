<?php

namespace App\Providers;

use App\Console\Commands\GenerateClubContributionCalls;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ClubAccountingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/club_accounting.php'));
        $this->commands([GenerateClubContributionCalls::class]);
        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)->command('club:contribution-calls --limit=100')
                ->hourly()->withoutOverlapping(20)->onOneServer();
        });
    }
}
