<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->string('product_accounting_status')->default('pending')->index();
            $table->timestamp('product_finality_applied_at')->nullable()->index();
            $table->timestamp('provider_reconciled_at')->nullable()->index();
        });

        // Historical code used "matched" for both callback processing and independent
        // reconciliation. Retain matched only where a matched reconciliation item exists.
        DB::table('mobile_money_transactions')
            ->where('reconciliation_status', 'matched')
            ->update([
                'reconciliation_status' => 'pending',
                'provider_reconciled_at' => null,
            ]);

        if (Schema::hasTable('reconciliation_items')) {
            $matchedIds = DB::table('reconciliation_items')
                ->where('status', 'matched')
                ->whereNotNull('mobile_money_transaction_id')
                ->pluck('mobile_money_transaction_id');

            foreach ($matchedIds->chunk(500) as $chunk) {
                DB::table('mobile_money_transactions')
                    ->whereIn('id', $chunk->all())
                    ->update([
                        'reconciliation_status' => 'matched',
                        'provider_reconciled_at' => now(),
                    ]);
            }
        }

        // Backfill credit product-accounting state only where immutable source evidence proves
        // the economic event was already applied.
        $creditMovements = DB::table('mobile_money_transactions')
            ->where(function ($query) {
                $query->whereNotNull('credit_offer_id')->orWhereNotNull('loan_id');
            })
            ->whereIn('status', ['successful', 'failed', 'reversed'])
            ->get(['id', 'transaction_id', 'credit_offer_id', 'loan_id', 'status']);

        foreach ($creditMovements as $movement) {
            $applied = $movement->status === 'failed';

            if ($movement->credit_offer_id) {
                $offer = DB::table('credit_offers')->where('id', $movement->credit_offer_id)->first(['offer_reference']);
                if ($offer) {
                    $reference = $movement->status === 'reversed'
                        ? 'loan.disbursement.reversal:credit-offer:'.$offer->offer_reference
                        : 'loan.disbursement:credit-offer:'.$offer->offer_reference;
                    $applied = $applied || DB::table('ledger_transactions')->where('reference', $reference)->exists();
                }
            } elseif ($movement->transaction_id) {
                $transactionReference = DB::table('transactions')->where('id', $movement->transaction_id)->value('reference');
                if ($transactionReference) {
                    $reference = $movement->status === 'reversed'
                        ? 'loan.repayment.reversal:'.$transactionReference
                        : 'loan.repayment:'.$transactionReference;
                    $applied = $applied || DB::table('ledger_transactions')->where('reference', $reference)->exists();
                }
            }

            if ($applied) {
                DB::table('mobile_money_transactions')->where('id', $movement->id)->update([
                    'product_accounting_status' => 'applied',
                    'product_finality_applied_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'product_accounting_status',
                'product_finality_applied_at',
                'provider_reconciled_at',
            ]);
        });
    }
};
