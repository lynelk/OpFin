<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileMoneyTransaction;
use App\Services\AuditLogger;
use App\Services\EarlySettlementService;
use App\Services\MobileMoney\MobileMoneyService;
use App\Services\ProductionCreditOfferService;
use App\Services\ProductionRepaymentService;
use App\Services\SubscriptionBillingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionPaymentOperationsController extends Controller
{
    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly ProductionCreditOfferService $creditOffers,
        private readonly EarlySettlementService $earlySettlements,
        private readonly ProductionRepaymentService $repayments,
        private readonly SubscriptionBillingService $subscriptions,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function refreshStatus(MobileMoneyTransaction $transaction, Request $request): JsonResponse
    {
        $before = $transaction->status;
        $updated = $this->mobileMoney->lookupStatus($transaction);
        $disbursedLoan = $this->creditOffers->syncDisbursementState($updated);
        $settledLoan = $this->earlySettlements->sync($updated);
        $repaidLoan = $this->repayments->syncCollectionState($updated);
        $subscriptionInvoice = $this->subscriptions->sync($updated);

        $this->auditLogger->record('mobile_money.status_repair.completed', $request->user(), $updated, [
            'previous_status' => $before,
            'current_status' => $updated->status,
            'provider_reference' => $updated->provider_reference,
        ], $request);

        return ApiResponse::success('Payment status refreshed.', [
            'transaction' => $updated->fresh(),
            'loan_id' => $disbursedLoan?->id ?? $settledLoan?->id ?? $repaidLoan?->id,
            'subscription_invoice_id' => $subscriptionInvoice?->id,
        ]);
    }
}
