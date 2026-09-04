<?php

namespace App\Providers;

use App\Support\ProductionConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
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

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('phone') ?: $request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

    }
}
