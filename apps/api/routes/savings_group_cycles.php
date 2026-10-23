<?php

use App\Http\Controllers\Api\SavingsGroupCycleController;
use App\Http\Controllers\Api\SavingsGroupSettlementController;
use Illuminate\Support\Facades\Route;

// Candidate routes are closed in production until all module-specific
// legal, consent, employer and finance release gates are approved.
if (config('opfin_core.enabled') !== true) {
    return;
}


Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('financial-spaces/{space}/savings-groups')
    ->where(['space' => '[0-9]+'])->group(function (): void {
        Route::get('cycles', [SavingsGroupCycleController::class, 'index']);
        Route::post('cycles', [SavingsGroupCycleController::class, 'createCycle']);
        Route::post('cycles/{cycle}/activate', [SavingsGroupCycleController::class, 'activate'])->whereNumber('cycle');
        Route::post('cycles/{cycle}/close', [SavingsGroupCycleController::class, 'close'])->whereNumber('cycle');
        Route::get('cycles/{cycle}/contributions', [SavingsGroupCycleController::class, 'contributions'])->whereNumber('cycle');
        Route::post('cycles/{cycle}/contributions', [SavingsGroupCycleController::class, 'pledge'])->whereNumber('cycle');
        Route::post('cycles/{cycle}/contributions/{contribution}/verify', [SavingsGroupCycleController::class, 'verifyContribution'])
            ->whereNumber('cycle')->whereNumber('contribution');
        Route::post('cycles/{cycle}/meetings', [SavingsGroupCycleController::class, 'createMeeting'])->whereNumber('cycle');
        Route::post('cycles/{cycle}/meetings/{meeting}/record', [SavingsGroupCycleController::class, 'recordMeeting'])
            ->whereNumber('cycle')->whereNumber('meeting');
        Route::post('cycles/{cycle}/loans', [SavingsGroupCycleController::class, 'loanRequest'])->whereNumber('cycle');
        Route::post('cycles/{cycle}/loans/{loan}/approve', [SavingsGroupCycleController::class, 'approveLoanRequest'])
            ->whereNumber('cycle')->whereNumber('loan');
        Route::post('cycles/{cycle}/loans/{loan}/allocate-disbursement', [SavingsGroupSettlementController::class, 'loanDisbursement'])
            ->whereNumber('cycle')->whereNumber('loan');
        Route::post('cycles/{cycle}/loans/{loan}/allocate-repayment', [SavingsGroupSettlementController::class, 'loanRepayment'])
            ->whereNumber('cycle')->whereNumber('loan');
        Route::post('cycles/{cycle}/social-claims', [SavingsGroupCycleController::class, 'socialClaim'])->whereNumber('cycle');
        Route::post('cycles/{cycle}/social-claims/{claim}/approve', [SavingsGroupCycleController::class, 'approveSocialClaim'])
            ->whereNumber('cycle')->whereNumber('claim');
        Route::post('cycles/{cycle}/social-claims/{claim}/allocate-payout', [SavingsGroupSettlementController::class, 'socialFundPayout'])
            ->whereNumber('cycle')->whereNumber('claim');
        Route::get('cycles/{cycle}/shareouts', [SavingsGroupCycleController::class, 'shareouts'])->whereNumber('cycle');
        Route::post('cycles/{cycle}/shareouts/prepare', [SavingsGroupCycleController::class, 'prepareShareout'])->whereNumber('cycle');
        Route::post('cycles/{cycle}/shareouts/{allocation}/approve', [SavingsGroupCycleController::class, 'approveShareout'])
            ->whereNumber('cycle')->whereNumber('allocation');
        Route::post('cycles/{cycle}/shareouts/{allocation}/allocate-payout', [SavingsGroupSettlementController::class, 'shareoutPayout'])
            ->whereNumber('cycle')->whereNumber('allocation');
    });
