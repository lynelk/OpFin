<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
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
// Account deletion routes
Route::get('/account/delete', [AuthController::class, 'showDeleteForm'])->name('account.delete');
Route::delete('/account/delete', [AuthController::class, 'destroy'])->name('account.destroy');


Route::middleware('auth')->group(function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    // loan products
    Route::get('/loan-products', [App\Http\Controllers\LoanProductsController::class, 'index'])->name('loan-products.index');
    Route::get('/loan-products/create', [App\Http\Controllers\LoanProductsController::class, 'create'])->name('loan-products.create');
    Route::post('/loan-products', [App\Http\Controllers\LoanProductsController::class, 'store'])->name('loan-products.store');
    Route::get('/loan-products/{loanProduct}/edit', [App\Http\Controllers\LoanProductsController::class, 'edit'])->name('loan-products.edit');
    Route::put('/loan-products/{loanProduct}', [App\Http\Controllers\LoanProductsController::class, 'update'])->name('loan-products.update');
    Route::put('/loan-products/{loanProduct}/change-status', [App\Http\Controllers\LoanProductsController::class, 'changeStatus'])->name('loan-products.change-status');
    Route::get('/loan-products/{loanProduct}', [App\Http\Controllers\LoanProductsController::class, 'show'])->name('loan-products.show');
    // addTerm route
    Route::get('/loan-products/{loanProduct}/add-term', [App\Http\Controllers\LoanProductsController::class, 'addTerm'])->name('loan-products.add-term');
    Route::post('/loan-products/{loanProduct}/terms', [App\Http\Controllers\LoanProductsController::class, 'storeTerm'])->name('loan-products.store-term');
    // edit term route
    Route::get('/loan-products/{loanProduct}/terms/{term}/edit', [App\Http\Controllers\LoanProductsController::class, 'editTerm'])->name('loan-products.edit-term');
    Route::put('/loan-products/{loanProduct}/terms/{term}', [App\Http\Controllers\LoanProductsController::class, 'updateTerm'])->name('loan-products.update-term');
    // change term status route
    Route::put('/loan-products/{loanProduct}/terms/{term}/change-status', [App\Http\Controllers\LoanProductsController::class, 'changeTermStatus'])->name('loan-products.change-term-status');

    Route::get('/loan-applications', [App\Http\Controllers\LoanApplicationsController::class, 'index'])->name('loan-applications.index');
    Route::get('/loans', [App\Http\Controllers\LoansController::class, 'index'])->name('loans.index');
    Route::get('/transactions', [App\Http\Controllers\TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('/accounts', [App\Http\Controllers\AccountsController::class, 'index'])->name('accounts.index');
    Route::get('/float-management', [App\Http\Controllers\FloatManagementController::class, 'index'])->name('float-management.index');
    // float topup store
    Route::post('/float-management', [App\Http\Controllers\FloatManagementController::class, 'store'])->name('float-topups.store');
    // Users routes
    Route::get('/users', [App\Http\Controllers\UsersController::class, 'index'])->name('users.index');
    Route::get('/users/create', [App\Http\Controllers\UsersController::class, 'create'])->name('users.create');
    Route::post('/users', [App\Http\Controllers\UsersController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [App\Http\Controllers\UsersController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [App\Http\Controllers\UsersController::class, 'update'])->name('users.update');
    Route::get('/users/{user}', [App\Http\Controllers\UsersController::class, 'show'])->name('users.show');
    // Institutions routes
    Route::get('/institutions', [App\Http\Controllers\InstitutionsController::class, 'index'])->name('institutions.index');
    Route::get('/institutions/create', [App\Http\Controllers\InstitutionsController::class, 'create'])->name('institutions.create');
    Route::post('/institutions', [App\Http\Controllers\InstitutionsController::class, 'store'])->name('institutions.store');
    Route::get('/institutions/{institution}/edit', [App\Http\Controllers\InstitutionsController::class, 'edit'])->name('institutions.edit');
    Route::put('/institutions/{institution}', [App\Http\Controllers\InstitutionsController::class, 'update'])->name('institutions.update');
    Route::delete('/institutions/{institution}', [App\Http\Controllers\InstitutionsController::class, 'destroy'])->name('institutions.destroy');
    Route::post('/institutions/administrator', [App\Http\Controllers\InstitutionsController::class, 'postAdministrator'])->name('institutions.postAdministrator');
    Route::put('/institutions/administrator/{id}', [App\Http\Controllers\InstitutionsController::class, 'updateAdministrator'])->name('institutions.updateAdministrator');

    // SMS messages routes
    Route::get('/sms-messages', [App\Http\Controllers\SmsMessagesController::class, 'index'])->name('sms-messages.index');
    // charts
    Route::get('/chart/user-growth', [HomeController::class, 'getUserGrowthData'])
        ->name('home.user-growth.chart');

    Route::get('/chart/loans-disbursed', [HomeController::class, 'getLoansDisbursedData'])
        ->name('home.loans-disbursed.chart');

    Route::get('/chats', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/{chat}', [ChatController::class, 'show']);
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy']);
    Route::post('/chats/{chat}/ask', [ChatController::class, 'ask']);
    Route::get('/chats/{chat}/stream', [ChatController::class, 'stream']);
});
