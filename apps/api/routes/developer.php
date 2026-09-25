<?php

use App\Http\Controllers\Api\DeveloperDocumentationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'throttle:60,1'])->group(function (): void {
    Route::get('/developers', [DeveloperDocumentationController::class, 'portal']);
    Route::prefix('api/developer')->group(function (): void {
        Route::get('manifest', [DeveloperDocumentationController::class, 'manifest']);
        Route::get('public', [DeveloperDocumentationController::class, 'publicCatalogue']);
        Route::get('guides', [DeveloperDocumentationController::class, 'guides']);
        Route::get('guides/{guide}', [DeveloperDocumentationController::class, 'guide'])->where('guide', '[a-z][a-z0-9-]{0,60}');
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('catalogue', [DeveloperDocumentationController::class, 'catalogue']);
            Route::get('operations/{operation}', [DeveloperDocumentationController::class, 'operation'])->where('operation', '[a-zA-Z][a-zA-Z0-9_.-]{0,95}');
            Route::get('openapi', [DeveloperDocumentationController::class, 'openapi']);
            Route::get('agent-tools', [DeveloperDocumentationController::class, 'agentTools']);
        });
    });
});
