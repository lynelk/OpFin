<?php

use App\Http\Controllers\Api\FinancialSpaceBrandController;
use App\Http\Controllers\Api\SaccoCoreController;
use App\Http\Controllers\Api\SaccoRegulatoryController;
use App\Http\Controllers\Api\SaccoSettlementController;
use Illuminate\Support\Facades\Route;

Route::get('/white-label/resolve', [FinancialSpaceBrandController::class, 'resolve']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/financial-spaces/{space}/sacco/products', [SaccoCoreController::class, 'products']);
    Route::post('/financial-spaces/{space}/sacco/products', [SaccoCoreController::class, 'createProduct']);
    if (config('opfin_core.enabled')) {
        Route::get('/financial-spaces/{space}/sacco/regulatory-attestations', [SaccoRegulatoryController::class, 'attestations']);
        Route::post('/financial-spaces/{space}/sacco/regulatory-attestations', [SaccoRegulatoryController::class, 'submitAttestation']);
        Route::post('/financial-spaces/{space}/sacco/products/{product}/activate', [SaccoRegulatoryController::class, 'activateProduct']);
    }
    Route::post('/financial-spaces/{space}/sacco/accounts', [SaccoCoreController::class, 'openAccount']);
    if (config('opfin_core.enabled')) {
        Route::post('/financial-spaces/{space}/sacco/accounts/{account}/allocate-settlement', [SaccoSettlementController::class, 'allocate'])->whereNumber('account');
    }
    Route::get('/financial-spaces/{space}/sacco/me', [SaccoCoreController::class, 'myPosition']);
    Route::post('/financial-spaces/{space}/sacco/guarantees', [SaccoCoreController::class, 'guarantee']);
    Route::post('/financial-spaces/{space}/sacco/distributions', [SaccoCoreController::class, 'prepareDistribution']);
    Route::post('/financial-spaces/{space}/sacco/distributions/{run}/approve', [SaccoCoreController::class, 'approveDistribution']);
    Route::put('/financial-spaces/{space}/brand', [FinancialSpaceBrandController::class, 'configure']);
    Route::post('/financial-spaces/{space}/domains', [FinancialSpaceBrandController::class, 'domain']);
});

if (config('opfin_core.enabled')) {
    Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations'])->group(function (): void {
        Route::post('/admin/sacco/{space}/regulatory-attestations/{attestation}/review',
            [SaccoRegulatoryController::class, 'reviewAttestation']);
    });
});

}
