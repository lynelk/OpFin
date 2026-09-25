<?php

namespace App\Services\ClubAccounting;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClubCorrectionService
{
    public function reverse(ClubPostingContext $context): array
    {
        $original = DB::table('club_journals')->where('book_id', $context->book->id)
            ->whereIn('event_type', ['journal', 'cash_entry'])->find((int) $context->payload()['original_journal_id']);
        if (! $original || DB::table('club_reversals')->where('original_journal_id', $original->id)->exists()) {
            throw new InvalidArgumentException('Select an unreversed ordinary journal or classified cash entry. Controlled capital and investment events use their own correcting workflows.');
        }
        if ($context->date() < $original->business_date) {
            throw new InvalidArgumentException('A reversal cannot predate its source journal.');
        }
        $lines = DB::table('club_journal_entries')->where('journal_id', $original->id)->orderBy('id')->get()
            ->map(static fn ($entry): array => ['account_id' => (int) $entry->account_id,
                'member_user_id' => $entry->member_user_id === null ? null : (int) $entry->member_user_id,
                'direction' => $entry->direction === 'debit' ? 'credit' : 'debit', 'amount_minor' => (int) $entry->amount_minor])->all();
        $sources = DB::table('club_treasury_posts as posting')->join('financial_space_transactions as source', 'source.id', '=', 'posting.treasury_transaction_id')
            ->where('posting.journal_id', $original->id)->get(['source.*']);
        $originalInstruction = DB::table('club_instructions')->find($original->instruction_id);
        $counterId = (int) ($context->payload()['treasury_transaction_id'] ?? 0);
        if ($sources->count() > 1 && $counterId > 0) {
            throw new InvalidArgumentException('A single counter-transaction cannot reverse several source cash movements.');
        }
        foreach ($sources as $source) {
            $internallyCreated = $source->source_type === 'club_accounting'
                && $source->source_reference === $originalInstruction?->reference;
            $externallyMatched = in_array(strtolower((string) $source->reconciliation_status), ['matched', 'reconciled', 'confirmed'], true)
                || DB::table('financial_space_statement_rows')->where('matched_transaction_id', $source->id)->exists();
            if ($counterId === 0 && (! $internallyCreated || $externallyMatched)) {
                throw new InvalidArgumentException('A bank-derived or reconciled cash movement requires an actual matching opposite source transaction. Use a non-cash reclassification journal when only the accounting category was wrong.');
            }
            $context->cash($source->direction === 'credit' ? 'debit' : 'credit', (int) $source->amount_minor,
                (int) $source->treasury_account_id, $counterId);
        }
        $context->post($lines, $context->payload()['description']);
        DB::table('club_reversals')->insert(['book_id' => $context->book->id, 'instruction_id' => $context->instruction->id,
            'original_journal_id' => $original->id, 'reversal_journal_id' => $context->journalId, 'created_at' => now()]);

        return ['journal_id' => $context->journalId, 'reversed_journal_id' => (int) $original->id, 'original_record_preserved' => true];
    }
}
