<?php

use App\Http\Controllers\Api\FinancingController;
use App\Http\Controllers\Api\PartnerFinancialIntentController;
use App\Http\Controllers\Api\PayrollDeductionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/financing-applications', [FinancingController::class, 'applications']);
    Route::get('/partner-financial-intents', [PartnerFinancialIntentController::class, 'customerIndex']);
    Route::post('/partner-financial-intents/{partnerRequest}/confirm', [PartnerFinancialIntentController::class, 'confirm']);
    Route::post('/partner-financial-intents/{partnerRequest}/decline', [PartnerFinancialIntentController::class, 'decline']);

    Route::get('/payroll-deduction/provider-capability', [PayrollDeductionController::class, 'providerCapability']);
    Route::get('/payroll-deduction/cases', [PayrollDeductionController::class, 'index']);
    Route::post('/payroll-deduction/cases', [PayrollDeductionController::class, 'store']);
    Route::get('/payroll-deduction/cases/{case}', [PayrollDeductionController::class, 'show']);
    Route::post('/payroll-deduction/cases/{case}/reservation', [PayrollDeductionController::class, 'requestReservation']);
    Route::post('/payroll-deduction/cases/{case}/undertaking', [PayrollDeductionController::class, 'undertaking']);
    Route::post('/payroll-deduction/cases/{case}/cancel', [PayrollDeductionController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'throttle:api', 'role:partner_api,platform_admin,operations'])
    ->prefix('partner')
    ->group(function () {
        Route::post('/financial-intents/{customer}', [PartnerFinancialIntentController::class, 'store']);
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
        Route::post('/cases/{case}/cancellation-release', [PayrollDeductionController::class, 'cancellationRelease']);
        Route::post('/cases/{case}/payroll-result', [PayrollDeductionController::class, 'payrollResult']);
        Route::post('/cases/{case}/amend', [PayrollDeductionController::class, 'amend']);
        Route::post('/cases/{case}/reconcile', [PayrollDeductionController::class, 'reconcile']);
    });
