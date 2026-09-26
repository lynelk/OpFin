<?php

use App\Http\Controllers\Api\ClubClientRequestsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'throttle:api'])->prefix('api')->group(function (): void {
    Route::get('accounting/saved-requests', [ClubClientRequestsController::class, 'index']);
    Route::prefix('financial-spaces/{space}/accounting/books/{book}/client-requests')
        ->where(['space' => '[0-9]+', 'book' => '[0-9]+'])->group(function (): void {
            Route::post('prepare/{purpose}', [ClubClientRequestsController::class, 'prepare'])->where('purpose', 'instruction|statement');
            Route::post('inspect', [ClubClientRequestsController::class, 'inspect']);
            Route::post('submit', [ClubClientRequestsController::class, 'submit']);
            Route::post('acknowledge', [ClubClientRequestsController::class, 'acknowledge']);
            Route::post('cancel', [ClubClientRequestsController::class, 'cancel']);
        });
});
