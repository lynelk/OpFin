<?php

namespace App\Providers;

use App\Console\Commands\MaintainIdentityEvidence;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class IdentityEvidenceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([MaintainIdentityEvidence::class]);
        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command('identity:evidence-maintain --refresh --limit=50')
                ->hourly()->withoutOverlapping(20)->onOneServer();
        });

        // This is an optional reusable cache, not the retained regulatory KYC
        // record. Purge it even when the account uses a soft deletion.
        User::deleted(function (User $user): void {
            if (Schema::hasTable('identity_verification_receipts')) {
                DB::table('identity_verification_receipts')->where('user_id', $user->id)->delete();
            }
        });
    }
}
