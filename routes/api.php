<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\LoanRepaymentController;
use App\Http\Controllers\Api\NinValidationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/generate-otp', [AuthController::class, 'generateOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/handleCallback', [TransactionController::class, 'handleCallback'])->name('handleCallback');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/validate-nin', [NinValidationController::class, 'validateNin']);
    Route::post('/credit-scores', [NinValidationController::class, 'creditScores']);
    Route::post('/loan-applications', [LoanApplicationController::class, 'store']);
    Route::get('/loan-applications/{user}', [LoanApplicationController::class, 'index']);
    Route::get('/loan-balance/{user}', [LoanApplicationController::class, 'getLoanBalance']);
    Route::post('/loan-applications/{id}/status', [LoanApplicationController::class, 'updateStatus']);
    Route::patch('/transactions/{id}/approve', [TransactionController::class, 'approve']);
    Route::post('/loans/{loan_id}/repay', [LoanRepaymentController::class, 'repay']);
    Route::get('/products', [LoanApplicationController::class, 'getProducts']);
    Route::get('/institutions', [LoanApplicationController::class, 'getInstitutions']);
    Route::get('/product-terms/{product}', [LoanApplicationController::class, 'getProductTerms']);
});
Route::post('/airtel-callback', [TransactionController::class, 'airtelCallback']);
Route::post('/mtn-callback', [TransactionController::class, 'mtnCallback']);
