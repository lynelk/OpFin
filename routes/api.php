<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CapabilityController;
use App\Http\Controllers\Api\CpayWebhookController;
use App\Http\Controllers\Api\CustomerSupportController;
use App\Http\Controllers\Api\FinancialWellbeingController;
use App\Http\Controllers\Api\FoundationAdminController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvestorDemoController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\LoanRepaymentController;
use App\Http\Controllers\Api\NinValidationController;
use App\Http\Controllers\Api\ProductionConsentController;
use App\Http\Controllers\Api\ProductionCreditController;
use App\Http\Controllers\Api\ProductionCreditOfferController;
use App\Http\Controllers\Api\ProductionKycController;
use App\Http\Controllers\Api\ProductionLoanApplicationController;
use App\Http\Controllers\Api\ProductionOperationsController;
use App\Http\Controllers\Api\ProductionPaymentOperationsController;
use App\Http\Controllers\Api\ProductionReconciliationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProtectionController;
use App\Http\Controllers\Api\SaveProtectionOperationsController;
use App\Http\Controllers\Api\SaveProtectionWorkQueueController;
use App\Http\Controllers\Api\SavingsController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\V5P0PlatformController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/generate-otp', [AuthController::class, 'generateOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});
Route::post('/webhooks/cpay', CpayWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.cpay');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show'])->middleware('audit.sensitive:profile.viewed');
    Route::get('/capabilities', [CapabilityController::class, 'index']);

    Route::get('/security-centre', [V5P0PlatformController::class, 'security']);
    Route::patch('/security-centre', [V5P0PlatformController::class, 'updateSecurity']);
    Route::get('/credit-builder', [V5P0PlatformController::class, 'creditBuilder']);
    Route::put('/credit-builder', [V5P0PlatformController::class, 'saveCreditBuilder']);
    Route::get('/hardship', [V5P0PlatformController::class, 'hardship']);
    Route::post('/hardship', [V5P0PlatformController::class, 'openHardship']);
    Route::get('/financial-passport', [V5P0PlatformController::class, 'passport']);
    Route::get('/reconciliation/summary', [V5P0PlatformController::class, 'reconciliation']);

    Route::get('/financial-compass', [FinancialWellbeingController::class, 'compass']);
    Route::get('/financial-categories', [FinancialWellbeingController::class, 'categories']);
    Route::get('/financial-accounts', [FinancialWellbeingController::class, 'accounts']);
    Route::post('/financial-accounts', [FinancialWellbeingController::class, 'storeAccount']);
    Route::patch('/financial-accounts/{account}', [FinancialWellbeingController::class, 'updateAccount']);
    Route::delete('/financial-accounts/{account}', [FinancialWellbeingController::class, 'destroyAccount']);
    Route::get('/budgets', [FinancialWellbeingController::class, 'budgets']);
    Route::post('/budgets', [FinancialWellbeingController::class, 'storeBudget']);
    Route::patch('/budgets/{budget}', [FinancialWellbeingController::class, 'updateBudget']);
    Route::delete('/budgets/{budget}', [FinancialWellbeingController::class, 'destroyBudget']);
    Route::get('/cash-flow', [FinancialWellbeingController::class, 'cashFlow']);
    Route::post('/cash-flow/entries', [FinancialWellbeingController::class, 'storeEntry']);
    Route::patch('/cash-flow/entries/{entry}', [FinancialWellbeingController::class, 'updateEntry']);
    Route::get('/financial-calendar', [FinancialWellbeingController::class, 'calendar']);
    Route::post('/financial-calendar/events', [FinancialWellbeingController::class, 'storeCalendarEvent']);
    Route::patch('/financial-calendar/events/{event}', [FinancialWellbeingController::class, 'updateCalendarEvent']);
    Route::delete('/financial-calendar/events/{event}', [FinancialWellbeingController::class, 'destroyCalendarEvent']);

    Route::get('/support-cases', [CustomerSupportController::class, 'index']);
    Route::post('/support-cases', [CustomerSupportController::class, 'store']);
    Route::post('/validate-nin', [NinValidationController::class, 'validateNin']);
    Route::post('/credit-scores', [NinValidationController::class, 'creditScores']);

    Route::get('/credit/applications', [ProductionLoanApplicationController::class, 'index']);
    Route::post('/credit/applications', [ProductionLoanApplicationController::class, 'store']);
    Route::get('/credit/applications/{application}', [ProductionLoanApplicationController::class, 'show']);
    Route::get('/credit/offers', [ProductionCreditOfferController::class, 'index']);
    Route::get('/credit/offers/{offer}', [ProductionCreditOfferController::class, 'show']);
    Route::post('/credit/offers/{offer}/accept', [ProductionCreditOfferController::class, 'accept']);

    Route::get('/savings/products', [SavingsController::class, 'products']);
    Route::get('/savings/goals', [SavingsController::class, 'index']);
    Route::post('/savings/goals', [SavingsController::class, 'store']);
    Route::get('/savings/goals/{goal}', [SavingsController::class, 'show']);
    Route::patch('/savings/goals/{goal}/schedule', [SavingsController::class, 'schedule']);
    Route::post('/savings/goals/{goal}/pause', [SavingsController::class, 'pause']);
    Route::post('/savings/goals/{goal}/resume', [SavingsController::class, 'resume']);
    Route::post('/savings/goals/{goal}/contributions', [SavingsController::class, 'contribute']);
    Route::post('/savings/goals/{goal}/withdrawals', [SavingsController::class, 'withdraw']);

    Route::get('/protection/products', [ProtectionController::class, 'products']);
    Route::get('/protection/policies', [ProtectionController::class, 'policies']);
    Route::get('/protection/policies/{policy}', [ProtectionController::class, 'show']);
    Route::post('/protection/products/{product}/enroll', [ProtectionController::class, 'enroll']);
    Route::post('/protection/policies/{policy}/premiums', [ProtectionController::class, 'payPremium']);
    Route::post('/protection/policies/{policy}/claims', [ProtectionController::class, 'submitClaim']);
    Route::post('/protection/claims/{claim}/dispute', [ProtectionController::class, 'disputeClaim']);

    Route::post('/loan-applications', [ProductionLoanApplicationController::class, 'store']);
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

Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations'])->group(function () {
    Route::post('/admin/hardship/{case}/approve', [V5P0PlatformController::class, 'approveHardship']);
    Route::post('/admin/product-factory/products', [V5P0PlatformController::class, 'createProduct']);
    Route::post('/admin/product-factory/products/{product}/transition', [V5P0PlatformController::class, 'transitionProduct']);
    Route::post('/admin/rules', [V5P0PlatformController::class, 'createRule']);
    Route::post('/admin/rules/{rule}/approve', [V5P0PlatformController::class, 'approveRule']);
    Route::post('/admin/rules/evaluate', [V5P0PlatformController::class, 'evaluateRules']);
    Route::post('/admin/workflows', [V5P0PlatformController::class, 'createWorkflow']);
    Route::post('/admin/workflows/{workflow}/approve', [V5P0PlatformController::class, 'approveWorkflow']);
    Route::post('/admin/workflows/{workflow}/runs', [V5P0PlatformController::class, 'startWorkflow']);
    Route::post('/admin/workflow-runs/{run}/transition', [V5P0PlatformController::class, 'transitionWorkflow']);

    Route::post('/admin/credit-decisions/{decision}/approve', [ProductionCreditController::class, 'approve']);
    Route::post('/admin/credit/applications/{application}/offer', [ProductionCreditOfferController::class, 'store']);
    Route::post('/admin/mobile-money-transactions/{transaction}/refresh-status', [ProductionPaymentOperationsController::class, 'refreshStatus']);
    Route::post('/admin/reconciliation-runs', [ProductionReconciliationController::class, 'store']);
    Route::post('/admin/reconciliation-runs/{run}/provider-records', [ProductionReconciliationController::class, 'ingest']);
    Route::post('/admin/reconciliation-runs/{run}/complete', [ProductionReconciliationController::class, 'complete']);

    Route::get('/admin/save-protection/work-queue', SaveProtectionWorkQueueController::class);
    Route::get('/admin/savings-products', [SaveProtectionOperationsController::class, 'savingsProducts']);
    Route::post('/admin/savings-products', [SaveProtectionOperationsController::class, 'createSavingsProduct']);
    Route::patch('/admin/savings-products/{product}', [SaveProtectionOperationsController::class, 'updateSavingsProduct']);
    Route::post('/admin/savings-products/{product}/activate', [SaveProtectionOperationsController::class, 'activateSavingsProduct']);
    Route::post('/admin/savings-movements/{movement}/confirm-contribution', [SaveProtectionOperationsController::class, 'confirmSavingsContribution']);
    Route::post('/admin/savings-movements/{movement}/release-withdrawal', [SaveProtectionOperationsController::class, 'releaseSavingsWithdrawal']);
    Route::post('/admin/savings-movements/{movement}/retry-payout', [SaveProtectionOperationsController::class, 'retrySavingsWithdrawalPayout']);

    Route::get('/admin/protection-products', [SaveProtectionOperationsController::class, 'protectionProducts']);
    Route::post('/admin/protection-products', [SaveProtectionOperationsController::class, 'createProtectionProduct']);
    Route::patch('/admin/protection-products/{product}', [SaveProtectionOperationsController::class, 'updateProtectionProduct']);
    Route::post('/admin/protection-products/{product}/activate', [SaveProtectionOperationsController::class, 'activateProtectionProduct']);
    Route::post('/admin/protection-premiums/{payment}/confirm', [SaveProtectionOperationsController::class, 'confirmPremium']);
    Route::post('/admin/protection-policies/{policy}/issue', [SaveProtectionOperationsController::class, 'issuePolicy']);
    Route::patch('/admin/protection-claims/{claim}', [SaveProtectionOperationsController::class, 'updateClaim']);
});

Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations,support'])->group(function () {
    Route::get('/admin/foundation-check', [FoundationAdminController::class, 'check']);
    Route::patch('/admin/kyc/cases/{case}', [ProductionKycController::class, 'review']);
    Route::post('/admin/crb-reports', [ProductionCreditController::class, 'storeCrbReport']);
    Route::post('/admin/loan-applications/{application}/decision', [ProductionCreditController::class, 'decide']);
    Route::get('/admin/ledger-transactions', [ProductionOperationsController::class, 'ledgerTransactions']);
    Route::get('/admin/reconciliation-runs', [ProductionOperationsController::class, 'reconciliationRuns']);
    Route::get('/admin/reconciliation-runs/{run}/items', [ProductionOperationsController::class, 'reconciliationItems']);
    Route::patch('/admin/reconciliation-items/{item}', [ProductionOperationsController::class, 'resolveReconciliationItem']);
    Route::get('/admin/support-cases', [ProductionOperationsController::class, 'supportCases']);
    Route::post('/admin/support-cases', [ProductionOperationsController::class, 'createSupportCase']);
    Route::patch('/admin/support-cases/{case}', [ProductionOperationsController::class, 'updateSupportCase']);
    Route::get('/admin/compliance-reports', [ProductionOperationsController::class, 'complianceReports']);
    Route::post('/admin/compliance-reports', [ProductionOperationsController::class, 'createComplianceReport']);
    Route::post('/admin/compliance-reports/{report}/exports', [ProductionOperationsController::class, 'createComplianceExport']);
});
