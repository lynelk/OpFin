<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Facades\DB;

/**
 * External proof is settlement evidence, NEVER an automatic posting into a
 * club's NAV, treasury cashbook, employer deductions or investment ledger.
 */
class FinancialSpaceActionSettlementService
{
    public function recordProviderStatementMatch(MobileMoneyTransaction $money): ?object
    {
        $metadata = is_array($money->metadata) ? $money->metadata : [];
        if (($metadata['purpose'] ?? '') !== 'financial_space_action'
            || ($metadata['source_type'] ?? '') !== 'financial_space_action'
            || ! isset($metadata['source_id'])) {
            return null;
        }

        return DB::transaction(function () use ($money, $metadata) {
            $action = DB::table('financial_space_action_intents')
                ->where('id', (int) $metadata['source_id'])->lockForUpdate()->first();
            if (! $action) {
                return null;
            }
            // Recheck the canonical money instruction, not merely the webhook's
            // untrusted reference. Mismatch becomes a visible review exception.
            $expected = $action->direction === 'collection'
                ? (int) $action->total_amount_minor : (int) $action->principal_amount_minor;
            $valid = (int) $money->amount_minor === $expected
                && strtoupper((string) $money->currency) === strtoupper((string) $action->currency)
                && (string) $money->phone === (string) $action->counterparty_phone
                && (string) $money->direction === (string) $action->direction
                && (string) $money->idempotency_key === 'space-action:'.$action->public_id
                && (string) $money->internal_reference === 'OPF-SPACE-'.$action->public_id
                && (! $action->mobile_money_transaction_id
                    || (int) $action->mobile_money_transaction_id === (int) $money->id);

            if (! $valid) {
                DB::table('financial_space_action_intents')->where('id', $action->id)->update([
                    'status' => 'settlement_exception',
                    'provider_statement_status' => 'instruction_mismatch',
                    'updated_at' => now(),
                ]);

                return DB::table('financial_space_action_intents')->find($action->id);
            }
            if ($money->statement_reconciliation_status !== MobileMoneyTransaction::STATEMENT_MATCHED) {
                return $action;
            }
            $status = match ($money->status) {
                MobileMoneyTransaction::STATUS_SUCCESSFUL => 'statement_matched_unallocated',
                MobileMoneyTransaction::STATUS_REVERSED => 'reversed_requires_book_correction',
                default => 'settlement_exception',
            };
            DB::table('financial_space_action_intents')->where('id', $action->id)->update([
                'mobile_money_transaction_id' => $money->id,
                'status' => $status,
                'provider_statement_status' => (string) $money->status,
                'provider_statement_matched_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('financial_space_action_intents')->find($action->id);
        });
    }
}
