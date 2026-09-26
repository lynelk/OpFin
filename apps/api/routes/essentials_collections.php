<?php

use App\Http\Controllers\Api\EssentialsCollectionRecoveryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'throttle:api'])->prefix('api/essentials/repayments')->group(function (): void {
    Route::get('{repayment}/collection-status', [EssentialsCollectionRecoveryController::class, 'show'])->whereNumber('repayment');
    Route::post('{repayment}/cancel-unsubmitted', [EssentialsCollectionRecoveryController::class, 'cancel'])->whereNumber('repayment');
});
