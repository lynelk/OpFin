<?php

use App\Http\Controllers\Api\LongRangePlatformController;
use Illuminate\Support\Facades\Route;

Route::post('/channels/ussd', [LongRangePlatformController::class, 'ussd'])->middleware('throttle:webhooks');

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('long-range')->group(function () {
    Route::get('/overview', [LongRangePlatformController::class, 'overview']);
    Route::post('/linked-accounts', [LongRangePlatformController::class, 'linkAccount'])->middleware('audit.sensitive:linked_account.created');
    Route::put('/household', [LongRangePlatformController::class, 'household'])->middleware('audit.sensitive:household.updated');
    Route::put('/microbusiness', [LongRangePlatformController::class, 'microbusiness'])->middleware('audit.sensitive:microbusiness.updated');
    Route::post('/asset-finance', [LongRangePlatformController::class, 'assetFinance'])->middleware('audit.sensitive:asset_finance.requested');
    Route::post('/community-memberships', [LongRangePlatformController::class, 'community'])->middleware('audit.sensitive:community.membership_created');
    Route::post('/participatory/listings', [LongRangePlatformController::class, 'participatoryListing'])->middleware('audit.sensitive:participatory.listing_created');
    Route::post('/participatory/commitments', [LongRangePlatformController::class, 'participatoryCommitment'])->middleware('audit.sensitive:participatory.commitment_created');
    Route::post('/referrals', [LongRangePlatformController::class, 'referral']);
    Route::post('/offline-sync', [LongRangePlatformController::class, 'offlineSync']);
    Route::post('/capital-mandates', [LongRangePlatformController::class, 'capital'])->middleware('role:platform_admin,operations');
    Route::post('/partners', [LongRangePlatformController::class, 'partner'])->middleware('role:platform_admin,operations');
});
