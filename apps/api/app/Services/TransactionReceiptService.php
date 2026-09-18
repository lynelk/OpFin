<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionReceiptService
{
    public function __construct(private readonly SmsService $smsService) {}

    public function issue(MobileMoneyTransaction $transaction, string $receiptType): object
    {
        $existing = DB::table('transaction_receipts')
            ->where('mobile_money_transaction_id', $transaction->id)
            ->where('receipt_type', $receiptType)
            ->first();

        if ($existing) {
            return $existing;
        }

        $details = [
            'provider' => $transaction->provider,
            'provider_reference' => $transaction->provider_reference,
            'internal_reference' => $transaction->internal_reference,
            'direction' => $transaction->direction,
            'status' => $transaction->status,
            'phone_masked' => $this->maskPhone((string) $transaction->phone),
            'reconciliation_status' => $transaction->reconciliation_status,
            'issued_by' => 'OpFin',
        ];
        $canonical = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $id = DB::table('transaction_receipts')->insertGetId([
            'user_id' => $transaction->user_id,
            'loan_id' => $transaction->loan_id,
            'mobile_money_transaction_id' => $transaction->id,
            'receipt_number' => 'OPF-RCP-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'receipt_type' => $receiptType,
            'status' => $transaction->status,
            'amount_minor' => $transaction->amount_minor,
            'currency' => $transaction->currency,
            'details' => $canonical,
            'receipt_hash' => hash('sha256', $canonical.'|'.$transaction->id.'|'.$transaction->amount_minor),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receipt = DB::table('transaction_receipts')->find($id);

        if ($transaction->phone) {
            $this->smsService->queueSms(
                (string) $transaction->phone,
                'OpFin receipt '.$receipt->receipt_number.': '.strtoupper($receiptType).' UGX '.number_format((int) $transaction->amount_minor).'. Status: '.$transaction->status.'. Ref: '.($transaction->provider_reference ?: $transaction->internal_reference).'.'
            );
        }

        return $receipt;
    }

    private function maskPhone(string $phone): string
    {
        return strlen($phone) <= 4 ? $phone : str_repeat('*', max(0, strlen($phone) - 4)).substr($phone, -4);
    }
}
