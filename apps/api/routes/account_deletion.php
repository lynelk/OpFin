<?php

use App\Http\Controllers\Api\AccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum','throttle:api'])->group(function () {
    Route::get('/account/deletion-readiness',[AccountController::class,'deletionReadiness']);
    Route::delete('/account/data',[AccountController::class,'deleteData']);
});
