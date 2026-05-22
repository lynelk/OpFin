<?php

namespace App\Providers;

use App\Support\ProductionConfiguration;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/dashboard';
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductionConfiguration::assertSafe($this->app->environment('production'), [
            'app_debug' => config('app.debug'),
            'mobile_money_provider' => config('services.mobile_money.default_provider'),
            'enable_demo_routes' => config('services.opfin.enable_demo_routes'),
        ]);

        Paginator::useBootstrap();

        // Register API routes manually
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));
    }
}
