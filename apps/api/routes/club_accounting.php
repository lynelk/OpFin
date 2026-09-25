<?php

use App\Http\Controllers\Api\ClubAccountingController;
use App\Http\Middleware\ProtectClubAccountingErrors;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'throttle:api', ProtectClubAccountingErrors::class])->prefix('api')->group(function (): void {
    Route::get('accounting/club-schema', [ClubAccountingController::class, 'schema']);
    Route::get('accounting/my-club-books', [ClubAccountingController::class, 'myBooks']);
    Route::prefix('financial-spaces/{space}/accounting')->where(['space' => '[0-9]+'])->group(function (): void {
        Route::get('books', [ClubAccountingController::class, 'index']);
        Route::post('books', [ClubAccountingController::class, 'createBook']);
        Route::prefix('books/{book}')->where(['book' => '[0-9]+'])->group(function (): void {
            Route::get('catalogue', [ClubAccountingController::class, 'catalogue']);
            Route::get('report', [ClubAccountingController::class, 'report']);
            Route::get('integrity', [ClubAccountingController::class, 'integrity']);
            Route::get('journals', [ClubAccountingController::class, 'journals']);
            Route::get('instructions', [ClubAccountingController::class, 'instructions']);
            Route::post('instructions', [ClubAccountingController::class, 'submit']);
            Route::get('instructions/{instruction}', [ClubAccountingController::class, 'instruction'])->whereNumber('instruction');
            Route::post('instructions/{instruction}/preview', [ClubAccountingController::class, 'preview'])->whereNumber('instruction');
            Route::post('instructions/{instruction}/approve', [ClubAccountingController::class, 'approve'])->whereNumber('instruction');
            Route::post('instructions/{instruction}/reject', [ClubAccountingController::class, 'reject'])->whereNumber('instruction');
            Route::post('instructions/{instruction}/cancel', [ClubAccountingController::class, 'cancel'])->whereNumber('instruction');
            Route::get('statements', [ClubAccountingController::class, 'statements']);
            Route::post('statements', [ClubAccountingController::class, 'issueStatement']);
            Route::get('statements/{statement}', [ClubAccountingController::class, 'statement'])->whereNumber('statement');
            Route::get('statements/{statement}/{format}', [ClubAccountingController::class, 'export'])->where(['statement' => '[0-9]+', 'format' => 'html|csv']);
        });
    });
});
