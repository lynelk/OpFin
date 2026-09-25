<?php

use App\Http\Controllers\Api\FinancialIntelligenceController as FI;
use App\Http\Controllers\Api\StatementIntelligenceController as Statements;
use Illuminate\Support\Facades\Route;

Route::prefix('financial-spaces/{space}/intelligence')->group(function (): void {
    Route::get('/', [FI::class, 'overview']);
    Route::get('/template', [FI::class, 'template']);
    Route::post('/sources', [FI::class, 'source']);
    Route::get('/sources/{source}/imports', [FI::class, 'imports']);
    Route::post('/sources/{source}/imports', [FI::class, 'stage']);
    Route::post('/sources/{source}/csv', [FI::class, 'upload']);
    Route::get('/imports/{import}', [FI::class, 'detail']);
    Route::post('/imports/{import}/review', [FI::class, 'review']);
    Route::get('/comparison', [FI::class, 'compare']);
    Route::post('/stress', [FI::class, 'stress']);
    Route::get('/cases', [FI::class, 'cases']);
    Route::get('/cases/{case}/events', [FI::class, 'caseHistory']);
    Route::post('/cases/{case}/events', [FI::class, 'caseEvent']);
    Route::put('/grants', [FI::class, 'grant']);
    Route::post('/reports', [FI::class, 'freezeReport']);
    Route::get('/reports/{report}', [FI::class, 'report']);
    Route::get('/reports/{report}/csv', [FI::class, 'reportCsv']);
    Route::get('/reports/{report}/html', [FI::class, 'reportHtml']);
    Route::put('/reports/{report}/share', [FI::class, 'shareReport']);
    Route::get('/network', [FI::class, 'network']);
    Route::get('/issuers', [Statements::class, 'issuers']);
    Route::get('/statements', [Statements::class, 'index']);
    Route::post('/statements', [Statements::class, 'upload']);
    Route::get('/statements/{statement}', [Statements::class, 'show']);
    Route::delete('/statements/{statement}/permission', [Statements::class, 'revoke']);
});
Route::post('intelligence/admin/issuers', [Statements::class, 'proposeIssuer']);
Route::post('intelligence/admin/issuers/{issuer}/review', [Statements::class, 'reviewIssuer']);
