<?php

use App\Http\Controllers\Api\PayrollDeductionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/payroll-deduction/provider-capability', [PayrollDeductionController::class, 'providerCapability']);
    Route::get('/payroll-deduction/cases', [PayrollDeductionController::class, 'index']);
    Route::post('/payroll-deduction/cases', [PayrollDeductionController::class, 'store']);
    Route::get('/payroll-deduction/cases/{case}', [PayrollDeductionController::class, 'show']);
    Route::post('/payroll-deduction/cases/{case}/reservation', [PayrollDeductionController::class, 'requestReservation']);
    Route::post('/payroll-deduction/cases/{case}/cancel', [PayrollDeductionController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations'])
    ->prefix('operations/payroll-deduction')
    ->group(function () {
        Route::get('/cases', [PayrollDeductionController::class, 'operationsIndex']);
        Route::get('/cases/{case}', [PayrollDeductionController::class, 'operationsShow']);
        Route::post('/cases/{case}/affordability', [PayrollDeductionController::class, 'affordability']);
        Route::post('/cases/{case}/reservation', [PayrollDeductionController::class, 'reservation']);
        Route::post('/cases/{case}/key-facts', [PayrollDeductionController::class, 'keyFacts']);
        Route::post('/cases/{case}/vote-decision', [PayrollDeductionController::class, 'voteDecision']);
        Route::post('/cases/{case}/payroll-submission', [PayrollDeductionController::class, 'payrollSubmission']);
        Route::post('/cases/{case}/payroll-result', [PayrollDeductionController::class, 'payrollResult']);
        Route::post('/cases/{case}/amend', [PayrollDeductionController::class, 'amend']);
        Route::post('/cases/{case}/reconcile', [PayrollDeductionController::class, 'reconcile']);
    });
