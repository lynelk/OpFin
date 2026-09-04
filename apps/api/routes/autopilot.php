<?php

use App\Http\Controllers\Api\AutonomousOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations'])->group(function () {
    Route::get('/admin/autopilot/summary', [AutonomousOperationsController::class, 'summary']);
    Route::get('/admin/autopilot/work-queue', [AutonomousOperationsController::class, 'queue']);
    Route::post('/admin/autopilot/runs', [AutonomousOperationsController::class, 'run']);
    Route::patch('/admin/autopilot/work-items/{item}', [AutonomousOperationsController::class, 'resolve']);
});
