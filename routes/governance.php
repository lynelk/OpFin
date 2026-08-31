<?php

use App\Http\Controllers\Api\GovernanceController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:webhooks');
Route::post('/webhooks/whatsapp', WhatsAppWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.whatsapp');

Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations,support'])->prefix('admin/governance')->group(function () {
    Route::get('/dashboard', [GovernanceController::class, 'dashboard']);
    Route::get('/regulatory-reports', [GovernanceController::class, 'reports']);
    Route::post('/regulatory-reports', [GovernanceController::class, 'generateReport']);
    Route::post('/regulatory-reports/{report}/approve', [GovernanceController::class, 'approveReport']);
    Route::post('/integrity-runs', [GovernanceController::class, 'runIntegrity']);
    Route::post('/integrity-alerts/{alert}/resolve', [GovernanceController::class, 'resolveIntegrityAlert']);
});
