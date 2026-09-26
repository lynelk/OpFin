<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubAsset;
use App\Support\ExactAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ClubInvestments
{
    public function acquire(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $amount = (int) $data['amount_minor'];
        $fee = (int) ($data['fee_minor'] ?? 0);
        $cost = ExactAllocation::add($amount, $fee);
        $quantity = (int) $data['quantity_micro'];
        if ($cost > 9007199254740991) {
            throw new InvalidArgumentException('Acquisition cost exceeds the supported amount range.');
        }
        $asset = ! empty($data['asset_id']) ? $this->asset($context, (int) $data['asset_id']) : null;
        if (! $asset) {
            $accountId = DB::table('club_accounts')->insertGetId([
                'book_id' => $context->book->id, 'code' => 'INV-'.Str::upper(Str::random(12)),
                'name' => $data['name'], 'kind' => 'asset', 'controlled' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $asset = ClubAsset::create([
                'reference' => (string) Str::uuid(), 'book_id' => $context->book->id, 'account_id' => $accountId,
                'name' => $data['name'], 'asset_class' => $data['asset_class'],
                'quantity_micro' => 0, 'cost_minor' => 0, 'carrying_value_minor' => 0, 'status' => 'active',
            ]);
        }
        $context->post([$context->cash('debit', $cost),
            ['account_id' => $asset->account_id, 'direction' => 'debit', 'amount_minor' => $cost, 'member_user_id' => null]],
            'Recorded investment acquisition including approved transaction costs');
        $nextQuantity = ExactAllocation::add($asset->quantity_micro, $quantity);
        $nextCost = ExactAllocation::add($asset->cost_minor, $cost);
        $nextValue = ExactAllocation::add($asset->carrying_value_minor, $cost);
        if (max($nextQuantity, $nextCost, $nextValue) > 9007199254740991) {
            throw new InvalidArgumentException('The resulting holding exceeds the supported quantity or amount range.');
        }
        // A purchase does not renew the valuation age of previously held units.
        $valuationDate = $asset->quantity_micro > 0 && $asset->valuation_date
            ? $asset->valuation_date->toDateString() : $context->date();
        $valuationReference = $asset->quantity_micro > 0 && $asset->valuation_reference
            ? $asset->valuation_reference : $data['evidence_reference'];
        $asset->update(['quantity_micro' => $nextQuantity, 'cost_minor' => $nextCost,
            'carrying_value_minor' => $nextValue, 'valuation_date' => $valuationDate,
            'valuation_reference' => $valuationReference, 'status' => 'active']);
        $this->movement($context, $asset, 'acquisition', $quantity, $cost, $cost, -$cost);

        return ['journal_id' => $context->journalId, 'asset_id' => $asset->id,
            'quantity_acquired_micro' => $quantity, 'capitalised_cost_minor' => $cost];
    }

    public function value(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $asset = $this->asset($context, (int) $data['asset_id']);
        if ($asset->quantity_micro <= 0) {
            throw new InvalidArgumentException('There is no held quantity to value.');
        }
        $value = (int) $data['carrying_value_minor'];
        $delta = $value - $asset->carrying_value_minor;
        if ($delta !== 0) {
            $context->post([
                ['account_id' => $asset->account_id, 'direction' => $delta > 0 ? 'debit' : 'credit',
                    'amount_minor' => abs($delta), 'member_user_id' => null],
                $context->ledger->line($context->book, 'UNREALISED_GAIN_LOSS', $delta > 0 ? 'credit' : 'debit', abs($delta)),
            ], 'Approved asset valuation adjustment');
        }
        $asset->update(['carrying_value_minor' => $value, 'valuation_date' => $context->date(),
            'valuation_reference' => $data['evidence_reference']]);
        $this->movement($context, $asset, 'valuation', 0, 0, $delta, 0);

        return ['journal_id' => $context->journalId, 'asset_id' => $asset->id,
            'carrying_value_minor' => $value, 'unrealised_change_minor' => $delta, 'valuation_date' => $context->date()];
    }

    public function dispose(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $asset = $this->asset($context, (int) $data['asset_id']);
        $quantity = (int) $data['quantity_micro'];
        $gross = (int) $data['amount_minor'];
        $fee = (int) ($data['fee_minor'] ?? 0);
        if ($quantity <= 0 || $quantity > $asset->quantity_micro || $fee < 0 || $fee >= $gross) {
            throw new InvalidArgumentException('Disposal exceeds the holding or leaves no positive net proceeds.');
        }
        [$cost] = ExactAllocation::multiplyDivide($asset->cost_minor, $quantity, $asset->quantity_micro);
        [$carrying] = ExactAllocation::multiplyDivide($asset->carrying_value_minor, $quantity, $asset->quantity_micro);
        $realised = $gross - $cost;
        $priorValuation = $carrying - $cost;
        $lines = [$context->cash('credit', $gross - $fee),
            ['account_id' => $asset->account_id, 'direction' => 'credit', 'amount_minor' => $carrying, 'member_user_id' => null]];
        if ($fee > 0) {
            $lines[] = $context->ledger->line($context->book, 'OPERATING_EXPENSES', 'debit', $fee);
        }
        if ($realised !== 0) {
            $lines[] = $context->ledger->line($context->book, 'REALISED_GAIN_LOSS', $realised > 0 ? 'credit' : 'debit', abs($realised));
        }
        if ($priorValuation !== 0) {
            $lines[] = $context->ledger->line($context->book, 'UNREALISED_GAIN_LOSS', $priorValuation > 0 ? 'debit' : 'credit', abs($priorValuation));
        }
        $context->post($lines, 'Recorded investment disposal and realised result');
        $remaining = $asset->quantity_micro - $quantity;
        $asset->update(['quantity_micro' => $remaining, 'cost_minor' => $asset->cost_minor - $cost,
            'carrying_value_minor' => $asset->carrying_value_minor - $carrying, 'status' => $remaining === 0 ? 'closed' : 'active']);
        $this->movement($context, $asset, 'disposal', -$quantity, -$cost, -$carrying, $gross - $fee);

        return ['journal_id' => $context->journalId, 'asset_id' => $asset->id, 'gross_proceeds_minor' => $gross,
            'net_proceeds_minor' => $gross - $fee, 'cost_released_minor' => $cost, 'carrying_released_minor' => $carrying,
            'realised_before_fee_minor' => $realised, 'fee_minor' => $fee, 'remaining_quantity_micro' => $remaining];
    }

    public function split(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $asset = $this->asset($context, (int) $data['asset_id']);
        if ($asset->quantity_micro <= 0) {
            throw new InvalidArgumentException('A corporate action requires an existing positive holding.');
        }
        [$quantity, $remainder] = ExactAllocation::multiplyDivide($asset->quantity_micro, (int) $data['numerator'], (int) $data['denominator']);
        if ($remainder !== 0 || $quantity <= 0 || $quantity > 9007199254740991) {
            throw new InvalidArgumentException('The split cannot be represented in exact micro-units. Record an approved cash-in-lieu adjustment separately rather than inventing fractional ownership.');
        }
        $delta = $quantity - $asset->quantity_micro;
        $asset->update(['quantity_micro' => $quantity]);
        $this->movement($context, $asset, 'split', $delta, 0, 0, 0);

        return ['asset_id' => $asset->id, 'quantity_micro' => $quantity,
            'cost_minor' => $asset->cost_minor, 'carrying_value_minor' => $asset->carrying_value_minor,
            'accounting_value_unchanged' => true];
    }

    public function cashEntry(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $amount = (int) $data['amount_minor'];
        $direction = $data['direction'];
        $lines = [$context->cash($direction, $amount)];
        $allocated = 0;
        foreach ($data['allocations'] as $row) {
            $account = $context->ledger->account($context->book, $row['account_code']);
            if ($account->controlled) {
                throw new InvalidArgumentException('Controlled capital, distribution, investment and cash accounts require their dedicated workflow.');
            }
            $share = (int) $row['amount_minor'];
            $memberId = isset($row['member_user_id']) ? (int) $row['member_user_id'] : null;
            if ($memberId !== null) {
                $context->member($memberId, false);
            }
            $allocated = ExactAllocation::add($allocated, $share);
            $lines[] = $context->ledger->line($context->book, $account->code,
                $direction === 'credit' ? 'credit' : 'debit', $share, $memberId);
        }
        if ($allocated !== $amount) {
            throw new InvalidArgumentException('The receipt or payment must be classified exactly.');
        }
        $context->post($lines, $data['description'] ?? 'Classified club cashbook transaction');

        return ['journal_id' => $context->journalId, 'amount_minor' => $amount, 'direction' => $direction];
    }

    public function journal(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $lines = [];
        foreach ($data['entries'] as $row) {
            $account = $context->ledger->account($context->book, $row['account_code']);
            if ($account->controlled && $account->code !== 'RETAINED_EARNINGS') {
                throw new InvalidArgumentException('A manual adjustment cannot bypass cash, capital, holding or distribution subledgers.');
            }
            $lines[] = $context->ledger->line($context->book, $account->code, $row['direction'], (int) $row['amount_minor']);
        }
        $context->post($lines, $data['description']);

        return ['journal_id' => $context->journalId, 'correction_is_append_only' => true];
    }

    public function reverseJournal(ClubPostingContext $context): array
    {
        $original = DB::table('club_journals')->where('book_id', $context->book->id)
            ->whereIn('event_type', ['journal', 'cash_entry'])->find((int) $context->payload()['original_journal_id']);
        if (! $original || DB::table('club_reversals')->where('original_journal_id', $original->id)->exists()) {
            throw new InvalidArgumentException('Select an unreversed ordinary journal or classified cash entry in this book. Capital, holdings and distributions require their specific correcting workflow.');
        }
        if ($context->date() < $original->business_date) {
            throw new InvalidArgumentException('A reversal cannot predate the original journal.');
        }
        $lines = DB::table('club_journal_entries')->where('journal_id', $original->id)->orderBy('id')->get()
            ->map(static fn ($entry): array => ['account_id' => (int) $entry->account_id,
                'member_user_id' => $entry->member_user_id === null ? null : (int) $entry->member_user_id,
                'direction' => $entry->direction === 'debit' ? 'credit' : 'debit', 'amount_minor' => (int) $entry->amount_minor])->all();
        $cashSources = DB::table('club_treasury_posts as posting')
            ->join('financial_space_transactions as source', 'source.id', '=', 'posting.treasury_transaction_id')
            ->where('posting.journal_id', $original->id)->get(['source.*']);
        foreach ($cashSources as $source) {
            $context->cash($source->direction === 'credit' ? 'debit' : 'credit', (int) $source->amount_minor, (int) $source->treasury_account_id, 0);
        }
        $context->post($lines, $context->payload()['description']);
        DB::table('club_reversals')->insert(['book_id' => $context->book->id, 'instruction_id' => $context->instruction->id,
            'original_journal_id' => $original->id, 'reversal_journal_id' => $context->journalId, 'created_at' => now()]);

        return ['journal_id' => $context->journalId, 'reversed_journal_id' => (int) $original->id,
            'original_record_preserved' => true];
    }

    private function asset(ClubPostingContext $context, int $assetId): ClubAsset
    {
        $asset = ClubAsset::query()->where('book_id', $context->book->id)->lockForUpdate()->findOrFail($assetId);
        $latest = DB::table('club_asset_movements')->where('asset_id', $asset->id)->max('business_date');
        if ($latest !== null && $context->date() < $latest) {
            throw new InvalidArgumentException('Acquisition, valuation and disposal records must preserve the asset chronology.');
        }

        return $asset;
    }

    private function movement(ClubPostingContext $context, ClubAsset $asset, string $type, int $quantity, int $cost, int $carrying, int $cash): void
    {
        DB::table('club_asset_movements')->insert([
            'book_id' => $context->book->id, 'asset_id' => $asset->id, 'instruction_id' => $context->instruction->id,
            'journal_id' => $context->journalId, 'type' => $type, 'business_date' => $context->date(),
            'quantity_delta_micro' => $quantity, 'cost_delta_minor' => $cost,
            'carrying_delta_minor' => $carrying, 'cash_flow_minor' => $cash, 'created_at' => now(),
        ]);
    }
}
