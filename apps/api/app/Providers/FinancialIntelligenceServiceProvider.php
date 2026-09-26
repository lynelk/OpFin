<?php

namespace App\Providers;

use App\Http\Middleware\FinancialIntelligenceRequestGuard;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FinancialIntelligenceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::prefix('api')->middleware(['api', 'auth:sanctum', 'throttle:api', FinancialIntelligenceRequestGuard::class])
                ->group(base_path('routes/financial_intelligence.php'));
        }
    }
}
