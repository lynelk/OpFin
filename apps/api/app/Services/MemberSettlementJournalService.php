<?php

namespace App\Services;

use App\Models\FinancialSpace;
use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Posts one balanced, immutable member-ledger event only when the same canonical
 * instruction has a verified provider-statement match AND a separately matched
 * treasury cashbook entry. No client-provided amount, provider status or account
 * code is trusted. This does not trigger provider disbursement or collection.
 */
class MemberSettlementJournalService
{
    /**
     * @param  array{code:string,member:?int}  $debit
     * @param  array{code:string,member:?int}  $credit
     */
    public function post(
        FinancialSpace $space,
        int $actionId,
        int $treasuryTransactionId,
        string $sourceType,
        string $sourceReference,
        string $eventType,
        array $debit,
        array $credit,
        int $actorId
    ): object {
        return DB::transaction(function () use (
            $space, $actionId, $treasuryTransactionId, $sourceType,
            $sourceReference, $eventType, $debit, $credit, $actorId
        ): object {
            abort_unless($space->status === 'active' && $space->deleted_at === null, 403);
            $action = DB::table('financial_space_action_intents')
                ->where('id', $actionId)
                ->where('financial_space_id', $space->id)
                ->lockForUpdate()->first();
            abort_unless($action, 404);
            abort_unless(
                $action->source_type === $sourceType
                && $action->source_reference === $sourceReference,
                409,
                'Canonical financial instruction is not linked to this account or member event.'
            );
            $existing = DB::table('financial_space_member_journals')
                ->where('financial_space_id', $space->id)
                ->where('action_intent_id', $actionId)
                ->lockForUpdate()->first();
            if ($existing) {
                abort_unless(
                    (int) $existing->treasury_transaction_id === $treasuryTransactionId
                    && $existing->event_type === $eventType
                    && $existing->event_key === $sourceType.':'.$sourceReference.':'.$actionId,
                    409,
                    'This financial instruction was already allocated to a different economic event.'
                );

                return $existing;
            }

            abort_unless(
                $action->status === 'statement_matched_unallocated'
                && $action->provider_statement_status === MobileMoneyTransaction::STATUS_SUCCESSFUL
                && $action->provider_statement_matched_at !== null
                && $action->mobile_money_transaction_id !== null,
                409,
                'Verified provider-statement success is required before member posting.'
            );
            $cash = DB::table('financial_space_transactions')
                ->where('id', $treasuryTransactionId)
                ->where('financial_space_id', $space->id)
                ->lockForUpdate()->first();
            abort_unless($cash, 404);
            $direction = $action->direction === 'collection' ? 'credit' : 'debit';
            // Gateway fees have their own economic events and cannot inflate a
            // member's principal. Non-zero, non-equal cashbook rows are exceptions.
            $amount = (int) $action->principal_amount_minor;
            abort_unless(
                $amount > 0
                && (int) $cash->amount_minor === $amount
                && (string) $cash->currency === (string) $action->currency
                && (string) $cash->currency === (string) $space->currency
                && $cash->direction === $direction
                && $cash->reconciliation_status === 'matched'
                && $cash->reconciled_at !== null,
                409,
                'Exact currency, principal, direction and statement-matched cashbook evidence required.'
            );
            abort_if(
                DB::table('financial_space_member_journals')
                    ->where('treasury_transaction_id', $treasuryTransactionId)->exists(),
                409,
                'Cashbook transaction already posted to the member ledger.'
            );
            abort_unless(
                preg_match('/^[a-z][a-z0-9_]{2,89}$/D', $debit['code']) === 1
                && preg_match('/^[a-z][a-z0-9_]{2,89}$/D', $credit['code']) === 1
                && $debit['code'] !== $credit['code'],
                422,
                'Invalid or self-balancing member ledger accounts.'
            );

            $key = $sourceType.':'.$sourceReference.':'.$actionId;
            $id = DB::table('financial_space_member_journals')->insertGetId([
                'financial_space_id' => $space->id,
                'treasury_transaction_id' => $treasuryTransactionId,
                'action_intent_id' => $actionId,
                'recorded_by_user_id' => $actorId,
                'event_key' => $key,
                'event_type' => $eventType,
                'amount_minor' => $amount,
                'currency' => $cash->currency,
                'evidence_level' => 'provider_success_and_statement_matched',
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $entries = [];
            foreach (['debit' => $debit, 'credit' => $credit] as $side => $account) {
                $entries[] = [
                    'journal_id' => $id,
                    'account_code' => $account['code'],
                    'member_user_id' => $account['member'],
                    'side' => $side,
                    'amount_minor' => $amount,
                    'currency' => $cash->currency,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            // Exactly two positive, equal-sized entries, one on each side.
            DB::table('financial_space_member_journal_entries')->insert($entries);
            DB::table('financial_space_action_intents')
                ->where('id', $actionId)->where('financial_space_id', $space->id)
                ->update([
                    'status' => 'book_allocated',
                    'book_allocation_reference' => $key,
                    'book_allocation_approved_at' => now(),
                    'updated_at' => now(),
                ]);

            return DB::table('financial_space_member_journals')->find($id);
        }, 3);
    }
}
