<?php

use App\Http\Controllers\Api\FinancialSpaceOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/financial-spaces/{space}/operations/analytics', [FinancialSpaceAnalyticsController::class, 'show']);
    Route::get('/financial-spaces/{space}/operations/actions', [FinancialSpaceOperationsController::class, 'index']);
    Route::post('/financial-spaces/{space}/operations/actions', [FinancialSpaceOperationsController::class, 'store']);
    Route::post('/financial-spaces/{space}/operations/actions/{action}/approve', [FinancialSpaceOperationsController::class, 'approve']);
});
