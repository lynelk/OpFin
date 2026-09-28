<?php
use App\Http\Controllers\Api\AudMonExportController;
use App\Http\Middleware\AuthenticateAudMon;
use Illuminate\Support\Facades\Route;
Route::prefix('integrations/audmon/v1')->middleware(AuthenticateAudMon::class)->group(function(){
 Route::get('/capabilities',[AudMonExportController::class,'capabilities']);
 Route::get('/audit-events',[AudMonExportController::class,'auditEvents']);
 Route::get('/reconciliation-exceptions',[AudMonExportController::class,'reconciliation']);
 Route::get('/revenue-events',[AudMonExportController::class,'revenue']);
 Route::get('/financial-space-controls',[AudMonExportController::class,'controls']);
});
