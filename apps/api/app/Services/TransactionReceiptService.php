<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use App\Models\TransactionReceipt;
use App\Services\SmsService;
use Illuminate\Support\Str;

class TransactionReceiptService
{
    public function __construct(private readonly SmsService $smsService) {}

    public function issue(MobileMoneyTransaction $transaction): ?TransactionReceipt
    {
        if ($transaction->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL || ! $transaction->user_id) {
            return null;
        }

        $existing = TransactionReceipt::query()
            ->where('mobile_money_transaction_id', $transaction->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $payload = [
            'receipt_reference' => 'OPF-RCT-'.Str::upper(Str::random(14)),
            'transaction_reference' => $transaction->transaction_id ?: $transaction->internal_reference,
            'transaction_type' => $transaction->direction,
            'amount_minor' => (int) $transaction->amount_minor,
            'currency' => $transaction->currency,
            'provider' => $transaction->provider,
            'provider_reference' => $transaction->provider_reference,
            'loan_id' => $transaction->loan_id,
            'status' => 'successful',
            'completed_at' => ($transaction->webhook_received_at ?? $transaction->updated_at ?? now())->toIso8601String(),
        ];
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        $receipt = TransactionReceipt::create([
            'user_id' => $transaction->user_id,
            'loan_id' => $transaction->loan_id,
            'mobile_money_transaction_id' => $transaction->id,
            'receipt_reference' => $payload['receipt_reference'],
            'transaction_type' => $transaction->direction,
            'amount_minor' => $transaction->amount_minor,
            'currency' => $transaction->currency,
            'provider' => $transaction->provider,
            'provider_reference' => $transaction->provider_reference,
            'status' => 'issued',
            'payload' => $payload,
            'payload_hash' => hash('sha256', $canonical),
            'delivery_channel' => 'sms',
            'issued_at' => now(),
        ]);

        $phone = $transaction->phone ?: $transaction->user?->phone;
        if ($phone) {
            $this->smsService->queueSms(
                $phone,
                sprintf(
                    'OpFin receipt %s: %s UGX %s successful. Provider ref: %s.',
                    $receipt->receipt_reference,
                    ucfirst($transaction->direction),
                    number_format((int) $transaction->amount_minor),
                    $transaction->provider_reference ?: 'pending-reference',
                ),
            );
            $receipt->update(['delivered_at' => now(), 'status' => 'delivered']);
        }

        return $receipt->fresh();
    }
}
