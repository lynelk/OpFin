<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\FloatManagementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstitutionsController;
use App\Http\Controllers\LoanApplicationsController;
use App\Http\Controllers\LoanProductsController;
use App\Http\Controllers\LoansController;
use App\Http\Controllers\SmsMessagesController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});
Auth::routes(['register' => false]);
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');

Route::get('/allan-abaho', function () {
    return view('allan-abaho');
});
// Account deletion is served by the Web app, which uses AccountDeletionService
// (open-obligation checks, serialisation and audit). This legacy path forwards there.
Route::match(['get', 'delete'], '/account/delete', [AuthController::class, 'redirectToAccountDeletion'])->name('account.delete');

// Legacy back-office. Retained until the Web back office reaches parity; every
// route requires a staff role and every change is recorded in the audit trail.
Route::middleware(['auth', 'role:platform_admin,operations'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    // loan products
    Route::get('/loan-products', [LoanProductsController::class, 'index'])->name('loan-products.index');
    Route::get('/loan-products/create', [LoanProductsController::class, 'create'])->name('loan-products.create');
    Route::post('/loan-products', [LoanProductsController::class, 'store'])->name('loan-products.store')->middleware('audit.sensitive:backoffice.loan_product.created');
    Route::get('/loan-products/{loanProduct}/edit', [LoanProductsController::class, 'edit'])->name('loan-products.edit');
    Route::put('/loan-products/{loanProduct}', [LoanProductsController::class, 'update'])->name('loan-products.update')->middleware('audit.sensitive:backoffice.loan_product.updated');
    Route::put('/loan-products/{loanProduct}/change-status', [LoanProductsController::class, 'changeStatus'])->name('loan-products.change-status')->middleware('audit.sensitive:backoffice.loan_product.status_changed');
    Route::get('/loan-products/{loanProduct}', [LoanProductsController::class, 'show'])->name('loan-products.show');
    // addTerm route
    Route::get('/loan-products/{loanProduct}/add-term', [LoanProductsController::class, 'addTerm'])->name('loan-products.add-term');
    Route::post('/loan-products/{loanProduct}/terms', [LoanProductsController::class, 'storeTerm'])->name('loan-products.store-term')->middleware('audit.sensitive:backoffice.loan_term.created');
    // edit term route
    Route::get('/loan-products/{loanProduct}/terms/{term}/edit', [LoanProductsController::class, 'editTerm'])->name('loan-products.edit-term');
    Route::put('/loan-products/{loanProduct}/terms/{term}', [LoanProductsController::class, 'updateTerm'])->name('loan-products.update-term');
    // change term status route
    Route::put('/loan-products/{loanProduct}/terms/{term}/change-status', [LoanProductsController::class, 'changeTermStatus'])->name('loan-products.change-term-status')->middleware('audit.sensitive:backoffice.loan_term.status_changed');

    Route::get('/loan-applications', [LoanApplicationsController::class, 'index'])->name('loan-applications.index');
    Route::get('/loans', [LoansController::class, 'index'])->name('loans.index');
    Route::get('/transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('/accounts', [AccountsController::class, 'index'])->name('accounts.index');
    Route::get('/float-management', [FloatManagementController::class, 'index'])->name('float-management.index');
    // Float top-ups are recorded as pending and need a second staff member to approve.
    Route::post('/float-management', [FloatManagementController::class, 'store'])->name('float-topups.store')->middleware('audit.sensitive:backoffice.float_topup.recorded');
    Route::post('/float-management/{floatTopup}/approve', [FloatManagementController::class, 'approve'])->name('float-topups.approve')->middleware('audit.sensitive:backoffice.float_topup.approved');
    // Users routes
    Route::get('/users', [UsersController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UsersController::class, 'show'])->name('users.show')->whereNumber('user');
    // Institutions routes
    Route::get('/institutions', [InstitutionsController::class, 'index'])->name('institutions.index');
    Route::get('/institutions/create', [InstitutionsController::class, 'create'])->name('institutions.create');
    Route::post('/institutions', [InstitutionsController::class, 'store'])->name('institutions.store')->middleware('audit.sensitive:backoffice.institution.created');
    Route::get('/institutions/{institution}/edit', [InstitutionsController::class, 'edit'])->name('institutions.edit');
    Route::put('/institutions/{institution}', [InstitutionsController::class, 'update'])->name('institutions.update')->middleware('audit.sensitive:backoffice.institution.updated');
    Route::delete('/institutions/{institution}', [InstitutionsController::class, 'destroy'])->name('institutions.destroy')->middleware('audit.sensitive:backoffice.institution.deleted');

    // Creating accounts or changing identity details is limited to platform administrators.
    Route::middleware('role:platform_admin')->group(function () {
        Route::get('/users/create', [UsersController::class, 'create'])->name('users.create');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store')->middleware('audit.sensitive:backoffice.user.created');
        Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update')->middleware('audit.sensitive:backoffice.user.updated');
        Route::post('/institutions/administrator', [InstitutionsController::class, 'postAdministrator'])->name('institutions.postAdministrator')->middleware('audit.sensitive:backoffice.institution_administrator.created');
        Route::put('/institutions/administrator/{id}', [InstitutionsController::class, 'updateAdministrator'])->name('institutions.updateAdministrator')->middleware('audit.sensitive:backoffice.institution_administrator.updated');
    });

    // SMS messages routes
    Route::get('/sms-messages', [SmsMessagesController::class, 'index'])->name('sms-messages.index');
    // charts
    Route::get('/chart/user-growth', [HomeController::class, 'getUserGrowthData'])
        ->name('home.user-growth.chart');

    Route::get('/chart/loans-disbursed', [HomeController::class, 'getLoansDisbursedData'])
        ->name('home.loans-disbursed.chart');
});
