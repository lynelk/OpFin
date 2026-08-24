<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CapabilityController;
use App\Http\Controllers\Api\CpayWebhookController;
use App\Http\Controllers\Api\CustomerSupportController;
use App\Http\Controllers\Api\FoundationAdminController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProductionConsentController;
use App\Http\Controllers\Api\ProductionCreditController;
use App\Http\Controllers\Api\ProductionKycController;
use App\Http\Controllers\Api\ProductionOperationsController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\LoanRepaymentController;
use App\Http\Controllers\Api\InvestorDemoController;
use App\Http\Controllers\Api\NinValidationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', [HealthController::class, 'show']);
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/generate-otp', [AuthController::class, 'generateOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});
Route::post('/handleCallback', [TransactionController::class, 'handleCallback'])->middleware('throttle:webhooks')->name('handleCallback');
Route::post('/webhooks/cpay', CpayWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.cpay');

// Protected routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show'])->middleware('audit.sensitive:profile.viewed');
    Route::get('/capabilities', [CapabilityController::class, 'index']);
    Route::get('/support-cases', [CustomerSupportController::class, 'index']);
    Route::post('/support-cases', [CustomerSupportController::class, 'store']);
    Route::post('/validate-nin', [NinValidationController::class, 'validateNin']);
    Route::post('/credit-scores', [NinValidationController::class, 'creditScores']);
    Route::post('/loan-applications', [LoanApplicationController::class, 'store']);
    Route::get('/loan-applications/{user}', [LoanApplicationController::class, 'index']);
    Route::get('/loan-balance/{user}', [LoanApplicationController::class, 'getLoanBalance']);
    Route::post('/loan-applications/{id}/status', [LoanApplicationController::class, 'updateStatus'])->middleware('audit.sensitive:loan_application.status_updated');
    Route::patch('/transactions/{id}/approve', [TransactionController::class, 'approve'])->middleware('audit.sensitive:transaction.approved');
    Route::post('/loans/{loan_id}/repay', [LoanRepaymentController::class, 'repay']);
    Route::get('/products', [LoanApplicationController::class, 'getProducts']);
    Route::get('/institutions', [LoanApplicationController::class, 'getInstitutions']);
    Route::get('/product-terms/{product}', [LoanApplicationController::class, 'getProductTerms']);

    Route::get('/kyc/status', [ProductionKycController::class, 'show']);
    Route::post('/kyc/cases', [ProductionKycController::class, 'submit']);
    Route::get('/consents', [ProductionConsentController::class, 'index']);
    Route::post('/consents', [ProductionConsentController::class, 'grant']);
    Route::delete('/consents/{consent}', [ProductionConsentController::class, 'revoke']);

    if (config('services.opfin.enable_demo_routes') || app()->environment(['local', 'testing'])) {
        Route::get('/demo/dashboard', [InvestorDemoController::class, 'dashboard']);
        Route::post('/demo/consent', [InvestorDemoController::class, 'grantConsent']);
        Route::delete('/demo/consent', [InvestorDemoController::class, 'revokeConsent']);
        Route::post('/demo/loan-applications', [InvestorDemoController::class, 'submitApplication']);
        Route::get('/demo/loan-applications/{application}/decision', [InvestorDemoController::class, 'decision']);
        Route::get('/demo/loan-applications/{application}/offer', [InvestorDemoController::class, 'offer']);
        Route::post('/demo/loan-offers/{offer}/accept', [InvestorDemoController::class, 'acceptOffer']);
        Route::get('/demo/admin/investor-snapshot', [InvestorDemoController::class, 'adminSnapshot']);
    }
});
Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations,support'])->group(function () {
    Route::get('/admin/foundation-check', [FoundationAdminController::class, 'check']);
    Route::patch('/admin/kyc/cases/{case}', [ProductionKycController::class, 'review']);
    Route::post('/admin/crb-reports', [ProductionCreditController::class, 'storeCrbReport']);
    Route::post('/admin/loan-applications/{application}/decision', [ProductionCreditController::class, 'decide']);
    Route::get('/admin/ledger-transactions', [ProductionOperationsController::class, 'ledgerTransactions']);
    Route::get('/admin/reconciliation-runs', [ProductionOperationsController::class, 'reconciliationRuns']);
    Route::post('/admin/reconciliation-runs', [ProductionOperationsController::class, 'createReconciliationRun']);
    Route::get('/admin/reconciliation-runs/{run}/items', [ProductionOperationsController::class, 'reconciliationItems']);
    Route::patch('/admin/reconciliation-items/{item}', [ProductionOperationsController::class, 'resolveReconciliationItem']);
    Route::get('/admin/support-cases', [ProductionOperationsController::class, 'supportCases']);
    Route::post('/admin/support-cases', [ProductionOperationsController::class, 'createSupportCase']);
    Route::patch('/admin/support-cases/{case}', [ProductionOperationsController::class, 'updateSupportCase']);
    Route::get('/admin/compliance-reports', [ProductionOperationsController::class, 'complianceReports']);
    Route::post('/admin/compliance-reports', [ProductionOperationsController::class, 'createComplianceReport']);
    Route::post('/admin/compliance-reports/{report}/exports', [ProductionOperationsController::class, 'createComplianceExport']);
});
Route::post('/airtel-callback', [TransactionController::class, 'airtelCallback'])->middleware('throttle:webhooks');
Route::post('/mtn-callback', [TransactionController::class, 'mtnCallback'])->middleware('throttle:webhooks');
