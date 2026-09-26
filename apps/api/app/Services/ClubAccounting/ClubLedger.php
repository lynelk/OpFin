<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Models\ClubInstruction;
use App\Support\ExactAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class ClubLedger
{
    public const CHART = [
        'MEMBER_CAPITAL' => ['Member capital', 'equity', true],
        'RETAINED_EARNINGS' => ['Retained earnings', 'equity', true],
        'DISTRIBUTIONS_PAYABLE' => ['Member distributions payable', 'liability', true],
        'TRADE_PAYABLES' => ['Trade and other payables', 'liability', false],
        'OTHER_RECEIVABLES' => ['Other receivables', 'asset', false],
        'OTHER_ASSETS' => ['Other recorded assets', 'asset', false],
        'INVESTMENT_INCOME' => ['Interest and investment income', 'income', false],
        'REALISED_GAIN_LOSS' => ['Realised investment gains and losses', 'income', true],
        'UNREALISED_GAIN_LOSS' => ['Unrealised investment gains and losses', 'income', true],
        'OTHER_INCOME' => ['Other income', 'income', false],
        'OPERATING_EXPENSES' => ['Operating expenses', 'expense', false],
        'BANK_FEES' => ['Bank and payment fees', 'expense', false],
        'TAX_EXPENSE' => ['Tax expense', 'expense', false],
        'TAX_PAYABLE' => ['Tax payable', 'liability', false],
    ];

    public function initialise(ClubBook $book): void
    {
        foreach (self::CHART as $code => [$name, $kind, $controlled]) {
            DB::table('club_accounts')->insertOrIgnore([
                'book_id' => $book->id, 'code' => $code, 'name' => $name,
                'kind' => $kind, 'controlled' => $controlled, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function account(ClubBook $book, string $code): object
    {
        $account = DB::table('club_accounts')->where('book_id', $book->id)->where('code', $code)->first();
        if (! $account) {
            throw new InvalidArgumentException('Unknown accounting account for this book.');
        }

        return $account;
    }

    public function line(ClubBook $book, string $code, string $direction, int $amount, ?int $memberUserId = null): array
    {
        return ['account_id' => (int) $this->account($book, $code)->id,
            'member_user_id' => $memberUserId, 'direction' => $direction, 'amount_minor' => $amount];
    }

    public function post(ClubBook $book, ClubInstruction $instruction, array $entries, string $description, string $evidence): int
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Journal posting requires the locked accounting transaction.');
        }
        if ((int) $instruction->book_id !== (int) $book->id || $instruction->status !== 'pending') {
            throw new InvalidArgumentException('Journal instruction does not match the pending book transaction.');
        }
        $entries = array_values(array_filter($entries, static fn (array $line): bool => ($line['amount_minor'] ?? null) !== 0));
        if (count($entries) < 2 || trim($evidence) === '') {
            throw new InvalidArgumentException('A journal needs source evidence and at least two positive entries.');
        }
        $debits = 0;
        $credits = 0;
        foreach ($entries as &$line) {
            if (! is_int($line['amount_minor'] ?? null) || $line['amount_minor'] <= 0
                || ! in_array($line['direction'] ?? '', ['debit', 'credit'], true)) {
                throw new InvalidArgumentException('Journal amounts and directions are invalid.');
            }
            $account = DB::table('club_accounts')->where('book_id', $book->id)->where('id', $line['account_id'])->first();
            if (! $account) {
                throw new InvalidArgumentException('A journal cannot use an account from another book.');
            }
            $memberId = $line['member_user_id'] ?? null;
            if ($memberId !== null && ! DB::table('club_members')->where('book_id', $book->id)->where('user_id', $memberId)->exists()) {
                throw new InvalidArgumentException('The journal member must belong to this book.');
            }
            $line = ['account_id' => (int) $account->id, 'member_user_id' => $memberId === null ? null : (int) $memberId,
                'direction' => $line['direction'], 'amount_minor' => $line['amount_minor']];
            if ($line['direction'] === 'debit') {
                $debits = ExactAllocation::add($debits, $line['amount_minor']);
            } else {
                $credits = ExactAllocation::add($credits, $line['amount_minor']);
            }
        }
        unset($line);
        if ($debits !== $credits || $debits === 0) {
            throw new InvalidArgumentException('Journal debits and credits must match exactly.');
        }
        $sequence = $book->journal_sequence + 1;
        $document = [
            'book_id' => $book->id, 'sequence' => $sequence,
            'business_date' => $instruction->business_date->toDateString(), 'event_type' => $instruction->type,
            'description' => $description, 'evidence_reference' => $evidence, 'entries' => $entries,
        ];
        $hash = hash('sha256', ($book->last_journal_hash ?? '')."\n".self::canonical($document));
        $id = DB::table('club_journals')->insertGetId([
            'reference' => (string) Str::uuid(), 'book_id' => $book->id, 'instruction_id' => $instruction->id,
            'sequence' => $sequence, 'business_date' => $document['business_date'], 'event_type' => $instruction->type,
            'description' => $description, 'evidence_reference' => $evidence,
            'total_debits_minor' => $debits, 'total_credits_minor' => $credits,
            'previous_hash' => $book->last_journal_hash, 'content_hash' => $hash, 'posted_at' => now(),
        ]);
        foreach ($entries as $entry) {
            DB::table('club_journal_entries')->insert(array_merge($entry, ['journal_id' => $id, 'book_id' => $book->id]));
        }
        $book->update(['journal_sequence' => $sequence, 'last_journal_hash' => $hash]);

        return (int) $id;
    }

    public function trialBalance(ClubBook $book, ?string $through = null): array
    {
        $query = DB::table('club_journal_entries as entry')
            ->join('club_journals as journal', 'journal.id', '=', 'entry.journal_id')
            ->where('entry.book_id', $book->id)
            ->when($through, static fn ($q) => $q->where('journal.business_date', '<=', $through))
            ->select('entry.account_id', 'entry.direction')->selectRaw('SUM(entry.amount_minor) as amount')
            ->groupBy('entry.account_id', 'entry.direction')->get();
        $totals = [];
        foreach ($query as $row) {
            $totals[(int) $row->account_id][$row->direction] = (int) $row->amount;
        }
        $rows = [];
        $debits = 0;
        $credits = 0;
        $position = ['assets_minor' => 0, 'liabilities_minor' => 0, 'equity_minor' => 0, 'income_minor' => 0, 'expenses_minor' => 0];
        foreach (DB::table('club_accounts')->where('book_id', $book->id)->orderBy('code')->get() as $account) {
            $dr = $totals[$account->id]['debit'] ?? 0;
            $cr = $totals[$account->id]['credit'] ?? 0;
            $signed = $dr - $cr;
            $rows[] = ['account_id' => (int) $account->id, 'code' => $account->code, 'name' => $account->name,
                'kind' => $account->kind, 'debits_minor' => $dr, 'credits_minor' => $cr,
                'balance_debit_minor' => max(0, $signed), 'balance_credit_minor' => max(0, -$signed)];
            $debits = ExactAllocation::add($debits, max(0, $signed));
            $credits = ExactAllocation::add($credits, max(0, -$signed));
            $key = match ($account->kind) {
                'asset' => 'assets_minor', 'liability' => 'liabilities_minor', 'equity' => 'equity_minor',
                'income' => 'income_minor', 'expense' => 'expenses_minor',
            };
            $position[$key] = ExactAllocation::add($position[$key], in_array($account->kind, ['asset', 'expense'], true) ? $signed : -$signed);
        }
        $position['surplus_minor'] = $position['income_minor'] - $position['expenses_minor'];
        $position['net_assets_minor'] = $position['assets_minor'] - $position['liabilities_minor'];
        $position['accounting_equation_variance_minor'] = $position['net_assets_minor'] - $position['equity_minor'] - $position['surplus_minor'];

        return ['currency' => $book->currency, 'through' => $through, 'accounts' => $rows,
            'total_debits_minor' => $debits, 'total_credits_minor' => $credits,
            'balanced' => $debits === $credits && $position['accounting_equation_variance_minor'] === 0, 'position' => $position];
    }

    public function debitBalance(ClubBook $book, string $code): int
    {
        $account = $this->account($book, $code);
        $rows = DB::table('club_journal_entries')->where('book_id', $book->id)->where('account_id', $account->id)
            ->select('direction')->selectRaw('SUM(amount_minor) as amount')->groupBy('direction')->get();
        $balance = 0;
        foreach ($rows as $row) {
            $balance = ExactAllocation::add($balance, $row->direction === 'debit' ? (int) $row->amount : -(int) $row->amount);
        }

        return $balance;
    }

    public static function canonical(array $data): string
    {
        $normalise = function (array $value) use (&$normalise): array {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $normalise($item);
                }
            }
            unset($item);

            return $value;
        };

        return json_encode($normalise($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
