<?php

namespace App\Providers;

use App\Http\Controllers\Api\PartnerEssentialsController;
use App\Http\Controllers\Api\ScopedPartnerEssentialsController;
use App\Services\AccountDeletionService;
use App\Services\EssentialsCustomerMutex;
use App\Services\EssentialsOrchestrationService;
use App\Services\SerialisedAccountDeletionService;
use App\Services\SerialisedEssentialsOrchestrationService;
use Illuminate\Support\ServiceProvider;

class EssentialsAuthorityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EssentialsCustomerMutex::class);
        $this->app->bind(EssentialsOrchestrationService::class, SerialisedEssentialsOrchestrationService::class);
        $this->app->bind(AccountDeletionService::class, SerialisedAccountDeletionService::class);
        $this->app->bind(PartnerEssentialsController::class, ScopedPartnerEssentialsController::class);
    }
}
