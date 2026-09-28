<?php
use App\Http\Controllers\Api\SaccoCoreController;
use App\Http\Controllers\Api\FinancialSpaceBrandController;
use Illuminate\Support\Facades\Route;

Route::get('/white-label/resolve',[FinancialSpaceBrandController::class,'resolve']);
Route::middleware('auth:sanctum')->group(function(){
 Route::get('/financial-spaces/{space}/sacco/products',[SaccoCoreController::class,'products']);
 Route::post('/financial-spaces/{space}/sacco/products',[SaccoCoreController::class,'createProduct']);
 Route::post('/financial-spaces/{space}/sacco/accounts',[SaccoCoreController::class,'openAccount']);
 Route::get('/financial-spaces/{space}/sacco/me',[SaccoCoreController::class,'myPosition']);
 Route::post('/financial-spaces/{space}/sacco/guarantees',[SaccoCoreController::class,'guarantee']);
 Route::put('/financial-spaces/{space}/brand',[FinancialSpaceBrandController::class,'configure']);
 Route::post('/financial-spaces/{space}/domains',[FinancialSpaceBrandController::class,'domain']);
});
