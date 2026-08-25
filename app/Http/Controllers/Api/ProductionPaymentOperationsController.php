<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileMoneyTransaction;
use App\Services\AuditLogger;
use App\Services\MobileMoney\MobileMoneyService;
use App\Services\ProductionCreditOfferService;
use App\Services\ProductionRepaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionPaymentOperationsController extends Controller
{
    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly ProductionCreditOfferService $creditOffers,
        private readonly ProductionRepaymentService $repayments,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function refreshStatus(MobileMoneyTransaction $transaction, Request $request): JsonResponse
    {
        $before = $transaction->status;
        $updated = $this->mobileMoney->lookupStatus($transaction);
        $disbursedLoan = $this->creditOffers->syncDisbursementState($updated);
        $repaidLoan = $this->repayments->syncCollectionState($updated);

        $this->auditLogger->record('mobile_money.status_repair.completed', $request->user(), $updated, [
            'previous_status' => $before,
            'current_status' => $updated->status,
            'provider_reference' => $updated->provider_reference,
        ], $request);

        return ApiResponse::success('Payment status refreshed.', [
            'transaction' => $updated->fresh(),
            'loan_id' => $disbursedLoan?->id ?? $repaidLoan?->id,
        ]);
    }
}
