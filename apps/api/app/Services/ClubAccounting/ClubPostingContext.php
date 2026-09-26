<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Models\ClubInstruction;
use App\Models\ClubMember;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceTransaction;
use App\Models\FinancialSpaceTreasuryAccount;
use App\Models\User;
use App\Services\FinancialSpaceStatementService;
use App\Support\ExactAllocation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClubPostingContext
{
    private array $cashTransactions = [];
    public ?int $journalId = null;
    public int $externalFlowMinor = 0;

    public function __construct(public readonly ClubBook $book, public readonly ClubInstruction $instruction,
        public readonly User $checker, public readonly ClubLedger $ledger) {}

    public function payload(): array { return $this->instruction->payload; }
    public function date(): string { return $this->instruction->business_date->toDateString(); }

    public function member(int $userId, bool $requireActive = true): ClubMember
    {
        if ($requireActive) { app(ClubAccess::class)->member($this->book, $userId); }
        $member = ClubMember::query()->where('book_id', $this->book->id)->where('user_id', $userId)->lockForUpdate()->first();
        if (! $member && ! $requireActive) {
            throw new InvalidArgumentException('The member has no accounting position in this book.');
        }
        return $member ?? ClubMember::create(['book_id' => $this->book->id, 'user_id' => $userId, 'units_micro' => 0, 'capital_minor' => 0]);
    }

    public function movement(ClubMember $member, string $type, int $unitsDelta, int $capitalDelta, int $cashFlow, array $evidence = []): void
    {
        $units = ExactAllocation::add($member->units_micro, $unitsDelta);
        $capital = ExactAllocation::add($member->capital_minor, $capitalDelta);
        if ($units < 0 || $capital < 0 || max($units, $capital) > 9007199254740991) {
            throw new InvalidArgumentException('Member units and capital must remain within non-negative supported bounds.');
        }
        $member->update(['units_micro' => $units, 'capital_minor' => $capital]);
        $bookUnits = (int) ClubMember::where('book_id', $this->book->id)->sum('units_micro');
        $bookCapital = (int) ClubMember::where('book_id', $this->book->id)->sum('capital_minor');
        if (max($bookUnits, $bookCapital) > 9007199254740991) {
            throw new InvalidArgumentException('The book exceeds the supported exact-integer reporting range.');
        }
        DB::table('club_member_movements')->insert([
            'book_id' => $this->book->id, 'member_id' => $member->id, 'instruction_id' => $this->instruction->id,
            'journal_id' => $this->journalId, 'type' => $type, 'business_date' => $this->date(),
            'units_delta_micro' => $unitsDelta, 'capital_delta_minor' => $capitalDelta, 'cash_flow_minor' => $cashFlow,
            'evidence' => ClubLedger::canonical($evidence), 'created_at' => now(),
        ]);
    }

    public function cash(string $direction, int $amount, ?int $accountOverride = null, ?int $sourceOverride = null): array
    {
        if ($amount <= 0 || $amount > 9007199254740991 || ! in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException('Cash movements require a supported positive integer amount and direction.');
        }
        $payload = $this->payload();
        $sourceId = $sourceOverride ?? (int) ($payload['treasury_transaction_id'] ?? 0);
        $treasury = FinancialSpaceTreasuryAccount::query()->where('financial_space_id', $this->book->financial_space_id)
            ->where('currency', $this->book->currency)->lockForUpdate()
            ->findOrFail($accountOverride ?? (int) ($payload['treasury_account_id'] ?? 0));
        $link = app(ClubBooks::class)->linkTreasury($this->book, $treasury);
        if ($direction === 'debit') {
            $unposted = DB::table('financial_space_transactions as transaction')
                ->leftJoin('club_treasury_posts as posting', 'posting.treasury_transaction_id', '=', 'transaction.id')
                ->where('transaction.treasury_account_id', $treasury->id)->where('transaction.currency', $this->book->currency)
                ->where('transaction.transaction_date', '>=', $this->book->cutover_date->toDateString())
                ->where('transaction.transaction_date', '<=', $this->date())
                ->when($sourceId > 0, static fn ($query) => $query->where('transaction.id', '<>', $sourceId))
                ->whereNull('posting.id')->exists();
            if ($unposted) {
                throw new InvalidArgumentException('Classify earlier and same-day cashbook activity before approving a payment from this account.');
            }
            if ($this->ledger->debitBalance($this->book, 'CASH-'.$treasury->id) < $amount) {
                throw new InvalidArgumentException('The recorded treasury balance cannot fund this payment.');
            }
            // A new record must not overdraw the current cashbook after other
            // already-recorded movements. An existing source is not deducted twice.
            if ($sourceId === 0 && $treasury->current_balance_minor < $amount) {
                throw new InvalidArgumentException('Existing cashbook payments leave insufficient recorded cash for another payment.');
            }
        }
        if ($sourceId > 0) {
            $transaction = FinancialSpaceTransaction::query()->where('financial_space_id', $this->book->financial_space_id)
                ->where('treasury_account_id', $treasury->id)->lockForUpdate()->findOrFail($sourceId);
            if ($transaction->currency !== $this->book->currency || $transaction->direction !== $direction
                || (int) $transaction->amount_minor !== $amount || $transaction->transaction_date->toDateString() !== $this->date()) {
                throw new InvalidArgumentException('Source date, account, currency, direction and amount must match the approved accounting instruction.');
            }
            if (DB::table('club_treasury_posts')->where('treasury_transaction_id', $sourceId)->exists()) {
                throw new InvalidArgumentException('This cashbook transaction is already posted to accounting.');
            }
        } else {
            $transaction = app(FinancialSpaceStatementService::class)->recordTransaction(
                FinancialSpace::findOrFail($this->book->financial_space_id), $treasury, $this->checker,
                ['transaction_date' => $this->date(), 'direction' => $direction, 'amount_minor' => $amount,
                    'currency' => $this->book->currency, 'transaction_type' => $this->instruction->type,
                    'description' => (string) ($payload['description'] ?? str_replace('_', ' ', $this->instruction->type)),
                    'transaction_reference' => (string) $payload['evidence_reference'],
                    'source_type' => 'club_accounting', 'source_reference' => $this->instruction->reference],
            );
        }
        $this->cashTransactions[] = (int) $transaction->id;
        return ['account_id' => (int) $link->account_id, 'member_user_id' => null,
            'direction' => $direction === 'credit' ? 'debit' : 'credit', 'amount_minor' => $amount];
    }

    public function post(array $entries, string $description): int
    {
        $this->journalId = $this->ledger->post($this->book, $this->instruction, $entries, $description, (string) $this->payload()['evidence_reference']);
        foreach ($this->cashTransactions as $transactionId) {
            DB::table('club_treasury_posts')->insert(['book_id' => $this->book->id, 'treasury_transaction_id' => $transactionId,
                'journal_id' => $this->journalId, 'created_at' => now()]);
        }
        return $this->journalId;
    }
}
