<?php

use App\Http\Controllers\Api\ExperiencePlatformController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/activation', [ExperiencePlatformController::class, 'activation']);
    Route::patch('/activation', [ExperiencePlatformController::class, 'saveActivation']);

    Route::get('/money-autopilot', [ExperiencePlatformController::class, 'moneyAutopilot']);
    Route::post('/money-autopilot/rules', [ExperiencePlatformController::class, 'createMoneyAutopilotRule']);
    Route::patch('/money-autopilot/rules/{rule}', [ExperiencePlatformController::class, 'setMoneyAutopilotRuleStatus']);

    Route::get('/investments/workspace', [ExperiencePlatformController::class, 'investments']);
    Route::put('/investments/suitability', [ExperiencePlatformController::class, 'saveSuitability']);
    Route::post('/investments/products/{product}/orders', [ExperiencePlatformController::class, 'createInvestmentOrder']);

    Route::get('/employer/workspace', [ExperiencePlatformController::class, 'employerDashboard']);
    Route::post('/employer/programs', [ExperiencePlatformController::class, 'createEmployerProgram']);
});

Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations'])->group(function () {
    Route::post('/admin/investment-products', [ExperiencePlatformController::class, 'createInvestmentProduct']);
    Route::post('/admin/investment-products/{product}/approve', [ExperiencePlatformController::class, 'approveInvestmentProduct']);
});
