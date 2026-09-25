<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubAsset;
use App\Models\ClubBook;
use App\Models\ClubMember;
use App\Support\ExactAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClubIntegrity
{
    public function __construct(private readonly ClubLedger $ledger) {}

    public function unpostedCashbook(ClubBook $book, ?int $exceptTransactionId = null, ?string $through = null): array
    {
        return DB::table('financial_space_transactions as transaction')
            ->join('financial_space_treasury_accounts as treasury', 'treasury.id', '=', 'transaction.treasury_account_id')
            ->leftJoin('club_treasury_posts as posting', 'posting.treasury_transaction_id', '=', 'transaction.id')
            ->where('transaction.financial_space_id', $book->financial_space_id)
            ->where('transaction.currency', $book->currency)
            ->where('transaction.transaction_date', '>=', $book->cutover_date->toDateString())
            ->when($through, static fn ($query) => $query->where('transaction.transaction_date', '<=', $through))
            ->when($exceptTransactionId, static fn ($query) => $query->where('transaction.id', '<>', $exceptTransactionId))
            ->whereNull('posting.id')->orderBy('transaction.transaction_date')->orderBy('transaction.id')
            ->limit(201)->get(['transaction.id', 'transaction.transaction_date', 'transaction.amount_minor',
                'transaction.direction', 'transaction.treasury_account_id', 'transaction.description'])->map(fn ($row) => (array) $row)->all();
    }

    public function assertNavUsable(ClubBook $book, string $asOf, ?int $exceptTransactionId = null): int
    {
        if ($this->unpostedCashbook($book, $exceptTransactionId, $asOf) !== []) {
            throw new InvalidArgumentException('Post the earlier unmatched cashbook activity before calculating member ownership or distributions.');
        }
        $latest = DB::table('club_nav_snapshots')->where('book_id', $book->id)->max('business_date');
        if ($latest !== null && $asOf < $latest) {
            throw new InvalidArgumentException('Ownership-sensitive accounting must be processed chronologically; retain the original source date in a reviewed correction.');
        }
        $days = (int) ($book->policy['valuation_max_age_days'] ?? 0);
        foreach (ClubAsset::query()->where('book_id', $book->id)->where('quantity_micro', '>', 0)->get() as $asset) {
            if (! $asset->valuation_date || $asset->valuation_date->toDateString() > $asOf
                || $asset->valuation_date->addDays($days)->lessThan(CarbonImmutable::parse($asOf))) {
                throw new InvalidArgumentException('A current, attributable valuation is required before ownership or distribution calculations.');
            }
        }
        $trial = $this->ledger->trialBalance($book, $asOf);
        if (! $trial['balanced']) {
            throw new InvalidArgumentException('The accounting equation does not reconcile.');
        }

        return $trial['position']['net_assets_minor'];
    }

    public function check(ClubBook $book): array
    {
        $trial = $this->ledger->trialBalance($book);
        $issues = [];
        if (! $trial['balanced']) {
            $issues[] = ['code' => 'unbalanced_trial_balance'];
        }
        $memberCapital = (int) ClubMember::where('book_id', $book->id)->sum('capital_minor');
        if ($memberCapital !== -$this->ledger->debitBalance($book, 'MEMBER_CAPITAL')) {
            $issues[] = ['code' => 'member_capital_control_difference'];
        }
        foreach (ClubMember::where('book_id', $book->id)->get() as $member) {
            $movements = DB::table('club_member_movements')->where('member_id', $member->id)
                ->selectRaw('COALESCE(SUM(units_delta_micro), 0) as units, COALESCE(SUM(capital_delta_minor), 0) as capital')->first();
            if ((int) $movements->units !== $member->units_micro || (int) $movements->capital !== $member->capital_minor) {
                $issues[] = ['code' => 'member_movement_difference', 'member_id' => $member->id];
            }
        }
        foreach (ClubAsset::where('book_id', $book->id)->get() as $asset) {
            $account = DB::table('club_accounts')->find($asset->account_id);
            if (! $account || $asset->carrying_value_minor !== $this->ledger->debitBalance($book, $account->code)) {
                $issues[] = ['code' => 'asset_control_difference', 'asset_id' => $asset->id];
            }
        }
        $payable = (int) DB::table('club_distribution_allocations as allocation')
            ->join('club_distributions as distribution', 'distribution.id', '=', 'allocation.distribution_id')
            ->where('distribution.book_id', $book->id)->sum(DB::raw('allocation.amount_minor - allocation.paid_minor'));
        if ($payable !== -$this->ledger->debitBalance($book, 'DISTRIBUTIONS_PAYABLE')) {
            $issues[] = ['code' => 'distribution_payable_difference'];
        }
        $previousHash = null;
        $sequence = 0;
        foreach (DB::table('club_journals')->where('book_id', $book->id)->orderBy('sequence')->cursor() as $journal) {
            $sequence++;
            $entries = DB::table('club_journal_entries')->where('journal_id', $journal->id)->orderBy('id')->get();
            $lines = [];
            $dr = 0;
            $cr = 0;
            foreach ($entries as $entry) {
                $amount = (int) $entry->amount_minor;
                $lines[] = ['account_id' => (int) $entry->account_id,
                    'member_user_id' => $entry->member_user_id === null ? null : (int) $entry->member_user_id,
                    'direction' => $entry->direction, 'amount_minor' => $amount];
                if ($entry->direction === 'debit') {
                    $dr = ExactAllocation::add($dr, $amount);
                } else {
                    $cr = ExactAllocation::add($cr, $amount);
                }
            }
            $document = ['book_id' => $book->id, 'sequence' => $sequence, 'business_date' => $journal->business_date,
                'event_type' => $journal->event_type, 'description' => $journal->description,
                'evidence_reference' => $journal->evidence_reference, 'entries' => $lines];
            $hash = hash('sha256', ($previousHash ?? '')."\n".ClubLedger::canonical($document));
            if ($sequence !== (int) $journal->sequence || $previousHash !== $journal->previous_hash
                || ! hash_equals($hash, $journal->content_hash) || count($lines) < 2 || $dr !== $cr
                || $dr !== (int) $journal->total_debits_minor || $cr !== (int) $journal->total_credits_minor) {
                $issues[] = ['code' => 'journal_integrity_difference', 'journal_id' => (int) $journal->id];
            }
            $previousHash = $journal->content_hash;
        }
        if ($sequence !== $book->journal_sequence || $previousHash !== $book->last_journal_hash) {
            $issues[] = ['code' => 'journal_head_difference'];
        }
        $unposted = $this->unpostedCashbook($book);
        if ($unposted !== []) {
            $issues[] = ['code' => 'unposted_cashbook', 'count_at_least' => count($unposted), 'truncated' => count($unposted) === 201];
        }

        return ['balanced' => $trial['balanced'], 'integrity_passed' => $issues === [], 'issues' => $issues,
            'unposted_cashbook' => $unposted, 'bank_reconciliation_certified' => false];
    }
}
