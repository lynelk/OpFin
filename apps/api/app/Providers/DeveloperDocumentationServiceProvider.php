<?php

namespace App\Providers;

use App\Console\Commands\ExportApiCatalogue;
use App\Services\ApiDocumentation\ApiDiscovery;
use Illuminate\Support\ServiceProvider;

class DeveloperDocumentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped instances reset between requests in persistent workers.
        $this->app->scoped(ApiDiscovery::class, static fn () => new ApiDiscovery);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/developer.php'));
        $this->commands([ExportApiCatalogue::class]);
    }
}
