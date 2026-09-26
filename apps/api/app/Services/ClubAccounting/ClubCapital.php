<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubAsset;
use App\Models\ClubMember;
use App\Models\FinancialSpaceTreasuryAccount;
use App\Support\ExactAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ClubCapital
{
    public function __construct(private readonly ClubIntegrity $integrity) {}

    public function opening(ClubPostingContext $context): array
    {
        $book = $context->book;
        $data = $context->payload();
        if ($book->status !== 'draft' || $book->journal_sequence !== 0
            || $context->date() !== $book->cutover_date->toDateString()) {
            throw new InvalidArgumentException('Opening balances must be approved once at the book cutover date.');
        }
        $lines = [];
        $members = [];
        foreach ($data['members'] as $row) {
            $member = $context->member((int) $row['user_id']);
            if (isset($members[$member->user_id])) {
                throw new InvalidArgumentException('Opening member allocations must be unique.');
            }
            $capital = (int) $row['capital_minor'];
            $units = (int) ($row['units_micro'] ?? 0);
            if ($book->ownership_model === 'capital_accounts' && $units !== 0) {
                throw new InvalidArgumentException('A capital-account book does not issue ownership units.');
            }
            $members[$member->user_id] = [$member, $capital, $units];
            if ($capital > 0) {
                $lines[] = $context->ledger->line($book, 'MEMBER_CAPITAL', 'credit', $capital, $member->user_id);
            }
        }
        foreach ($data['balances'] as $row) {
            $account = $context->ledger->account($book, $row['account_code']);
            if (! in_array($account->kind, ['asset', 'liability', 'equity'], true)
                || in_array($account->code, ['MEMBER_CAPITAL', 'DISTRIBUTIONS_PAYABLE'], true)
                || str_starts_with($account->code, 'INV-')) {
                throw new InvalidArgumentException('Opening capital and investment positions must use their explicit allocation records.');
            }
            $lines[] = $context->ledger->line($book, $account->code, $row['direction'], (int) $row['amount_minor']);
        }
        $assets = [];
        foreach ($data['assets'] ?? [] as $row) {
            $asset = ClubAsset::create([
                'reference' => (string) Str::uuid(), 'book_id' => $book->id,
                'account_id' => DB::table('club_accounts')->insertGetId([
                    'book_id' => $book->id, 'code' => 'INV-'.Str::upper(Str::random(12)), 'name' => $row['name'],
                    'kind' => 'asset', 'controlled' => true, 'created_at' => now(), 'updated_at' => now(),
                ]),
                'name' => $row['name'], 'asset_class' => $row['asset_class'],
                'quantity_micro' => (int) $row['quantity_micro'], 'cost_minor' => (int) $row['cost_minor'],
                'carrying_value_minor' => (int) $row['carrying_value_minor'],
                'valuation_date' => $context->date(), 'valuation_reference' => $data['evidence_reference'], 'status' => 'active',
            ]);
            $assets[] = $asset;
            $lines[] = ['account_id' => $asset->account_id, 'direction' => 'debit',
                'amount_minor' => $asset->carrying_value_minor, 'member_user_id' => null];
        }
        if ($lines !== []) {
            $context->post($lines, 'Approved opening balances and member allocations');
        }
        foreach (DB::table('club_treasury_links')->where('book_id', $book->id)->get() as $link) {
            $account = FinancialSpaceTreasuryAccount::findOrFail($link->treasury_account_id);
            if ($account->balance_as_of && $account->balance_as_of->toDateString() > $context->date()) {
                throw new InvalidArgumentException('The book cutover cannot precede a linked treasury opening baseline.');
            }
            $receipts = (int) DB::table('financial_space_transactions')->where('treasury_account_id', $account->id)
                ->where('transaction_date', '<', $context->date())->where('direction', 'credit')->sum('amount_minor');
            $payments = (int) DB::table('financial_space_transactions')->where('treasury_account_id', $account->id)
                ->where('transaction_date', '<', $context->date())->where('direction', 'debit')->sum('amount_minor');
            $expected = ExactAllocation::add($account->opening_balance_minor, $receipts) - $payments;
            if ($context->ledger->debitBalance($book, 'CASH-'.$account->id) !== $expected) {
                throw new InvalidArgumentException('Opening cash must reconcile to the original treasury baseline and pre-cutover transactions; no balance is inferred.');
            }
        }
        foreach ($members as [$member, $capital, $units]) {
            $context->movement($member, 'opening', $units, $capital, 0, ['source' => $data['evidence_reference']]);
        }
        foreach ($assets as $asset) {
            DB::table('club_asset_movements')->insert([
                'book_id' => $book->id, 'asset_id' => $asset->id, 'instruction_id' => $context->instruction->id,
                'journal_id' => $context->journalId, 'type' => 'opening', 'business_date' => $context->date(),
                'quantity_delta_micro' => $asset->quantity_micro, 'cost_delta_minor' => $asset->cost_minor,
                'carrying_delta_minor' => $asset->carrying_value_minor, 'cash_flow_minor' => 0, 'created_at' => now(),
            ]);
        }
        $net = $context->ledger->trialBalance($book)['position']['net_assets_minor'];
        $units = (int) ClubMember::where('book_id', $book->id)->sum('units_micro');
        if ($net < 0 || ($book->ownership_model === 'unitised' && (($net > 0 && $units <= 0) || ($net === 0 && $units > 0)))) {
            throw new InvalidArgumentException('Opening net assets and ownership allocations are inconsistent.');
        }
        $book->update(['status' => 'active']);

        return ['book_status' => 'active', 'journal_id' => $context->journalId, 'net_assets_minor' => $net, 'units_micro' => $units];
    }

    public function contribution(ClubPostingContext $context): array
    {
        $book = $context->book;
        $data = $context->payload();
        $amount = (int) $data['amount_minor'];
        $member = $context->member((int) $data['member_user_id']);
        $net = $this->integrity->assertNavUsable($book, $context->date(), isset($data['treasury_transaction_id']) ? (int) $data['treasury_transaction_id'] : null);
        $totalUnits = (int) ClubMember::where('book_id', $book->id)->sum('units_micro');
        $units = 0;
        $remainder = 0;
        $divisor = 1;
        if ($book->ownership_model === 'unitised') {
            if ($totalUnits === 0) {
                if ($net !== 0) {
                    throw new InvalidArgumentException('Existing unallocated net assets require approved opening ownership before a new subscription.');
                }
                $divisor = $book->initial_unit_price_minor;
                [$units, $remainder] = ExactAllocation::multiplyDivide($amount, $book->unit_scale, $divisor);
            } else {
                if ($net <= 0) {
                    throw new InvalidArgumentException('A non-positive NAV requires a reviewed recapitalisation policy, not ordinary unit issuance.');
                }
                $divisor = $net;
                [$units, $remainder] = ExactAllocation::multiplyDivide($amount, $totalUnits, $net);
            }
            if ($units <= 0 || ExactAllocation::add($totalUnits, $units) > 9007199254740991) {
                throw new InvalidArgumentException('Contribution is below one micro-unit or exceeds the supported unit range.');
            }
        } else {
            $capital = (int) ClubMember::where('book_id', $book->id)->sum('capital_minor');
            if ($net !== $capital) {
                throw new InvalidArgumentException('Allocate the accrued surplus or loss to existing member capital before accepting a new capital-account contribution.');
            }
        }
        $call = null;
        if (! empty($data['capital_call_id'])) {
            $call = DB::table('club_capital_calls')->where('book_id', $book->id)
                ->where('member_user_id', $member->user_id)->where('status', 'open')
                ->lockForUpdate()->find((int) $data['capital_call_id']);
            if (! $call || $amount > (int) $call->amount_minor - (int) $call->paid_minor) {
                throw new InvalidArgumentException('Contribution exceeds the selected call balance or belongs to another member.');
            }
        }
        $context->post([$context->cash('credit', $amount),
            $context->ledger->line($book, 'MEMBER_CAPITAL', 'credit', $amount, $member->user_id)], 'Member contribution');
        $context->movement($member, 'contribution', $units, $amount, $amount, [
            'nav_before_minor' => $net, 'units_before_micro' => $totalUnits,
            'rounding_numerator' => $remainder, 'rounding_denominator' => $divisor,
        ]);
        if ($call) {
            $paid = (int) $call->paid_minor + $amount;
            DB::table('club_capital_calls')->where('id', $call->id)
                ->update(['paid_minor' => $paid, 'status' => $paid === (int) $call->amount_minor ? 'paid' : 'open', 'updated_at' => now()]);
        }
        $context->externalFlowMinor = $amount;

        return ['journal_id' => $context->journalId, 'member_user_id' => $member->user_id,
            'amount_minor' => $amount, 'units_issued_micro' => $units,
            'rounding_numerator' => $remainder, 'rounding_denominator' => $divisor];
    }

    public function allocateResult(ClubPostingContext $context): array
    {
        $book = $context->book;
        if ($book->ownership_model !== 'capital_accounts') {
            throw new InvalidArgumentException('Unitised ownership reflects returns in NAV instead of capital-account appropriation.');
        }
        $net = $this->integrity->assertNavUsable($book, $context->date());
        $members = ClubMember::where('book_id', $book->id)->where('capital_minor', '>', 0)->orderBy('user_id')->get();
        $total = (int) $members->sum('capital_minor');
        $change = $net - $total;
        if ($net < 0 || $total <= 0 || $change === 0) {
            throw new InvalidArgumentException('There is no allocable result, or capital requires a separate insolvency/recapitalisation review.');
        }
        $weights = $members->pluck('capital_minor', 'user_id')->all();
        $allocations = ExactAllocation::proportional(abs($change), $weights);
        $lines = [$context->ledger->line($book, 'RETAINED_EARNINGS', $change > 0 ? 'debit' : 'credit', abs($change))];
        foreach ($members as $member) {
            $lines[] = $context->ledger->line($book, 'MEMBER_CAPITAL', $change > 0 ? 'credit' : 'debit', $allocations[$member->user_id], $member->user_id);
        }
        $context->post($lines, 'Allocate recorded surplus or loss to existing member capital');
        foreach ($members as $member) {
            $delta = $change > 0 ? $allocations[$member->user_id] : -$allocations[$member->user_id];
            $context->movement($member, 'result_allocation', 0, $delta, 0, ['net_assets_minor' => $net]);
        }

        return ['journal_id' => $context->journalId, 'allocated_result_minor' => $change, 'allocations_minor' => $allocations];
    }

    public function redemption(ClubPostingContext $context): array
    {
        $book = $context->book;
        $data = $context->payload();
        $member = $context->member((int) $data['member_user_id'], false);
        $net = $this->integrity->assertNavUsable($book, $context->date(), isset($data['treasury_transaction_id']) ? (int) $data['treasury_transaction_id'] : null);
        if ($net <= 0) {
            throw new InvalidArgumentException('The book has no positive redeemable net assets.');
        }
        $units = 0;
        if ($book->ownership_model === 'unitised') {
            $units = (int) ($data['units_micro'] ?? 0);
            $totalUnits = (int) ClubMember::where('book_id', $book->id)->sum('units_micro');
            if ($units <= 0 || $units > $member->units_micro || $totalUnits <= 0) {
                throw new InvalidArgumentException('Redemption exceeds the member ownership units.');
            }
            [$gross] = ExactAllocation::multiplyDivide($net, $units, $totalUnits);
            [$capital] = ExactAllocation::multiplyDivide($member->capital_minor, $units, $member->units_micro);
        } else {
            $capital = (int) ($data['capital_minor'] ?? 0);
            $totalCapital = (int) ClubMember::where('book_id', $book->id)->sum('capital_minor');
            if ($capital <= 0 || $capital > $member->capital_minor || $totalCapital <= 0) {
                throw new InvalidArgumentException('Redemption exceeds the member capital interest.');
            }
            [$gross] = ExactAllocation::multiplyDivide($net, $capital, $totalCapital);
        }
        $tax = (int) ($data['withholding_minor'] ?? 0);
        if ($gross <= 0 || $tax < 0 || $tax >= $gross) {
            throw new InvalidArgumentException('Redemption must leave a positive recorded net payment.');
        }
        $cash = $gross - $tax;
        $lines = [$context->cash('debit', $cash),
            $context->ledger->line($book, 'MEMBER_CAPITAL', 'debit', $capital, $member->user_id)];
        $result = $gross - $capital;
        if ($result !== 0) {
            $lines[] = $context->ledger->line($book, 'RETAINED_EARNINGS', $result > 0 ? 'debit' : 'credit', abs($result));
        }
        if ($tax > 0) {
            $lines[] = $context->ledger->line($book, 'TAX_PAYABLE', 'credit', $tax);
        }
        $context->post($lines, 'Member capital redemption');
        $context->movement($member, 'redemption', -$units, -$capital, -$cash,
            ['gross_minor' => $gross, 'withholding_minor' => $tax, 'nav_before_minor' => $net]);
        $context->externalFlowMinor = -$gross;

        return ['journal_id' => $context->journalId, 'gross_minor' => $gross,
            'net_payment_minor' => $cash, 'withholding_minor' => $tax, 'units_redeemed_micro' => $units, 'capital_redeemed_minor' => $capital];
    }

    public function transfer(ClubPostingContext $context): array
    {
        $book = $context->book;
        $data = $context->payload();
        if ((int) $data['from_user_id'] === (int) $data['to_user_id']) {
            throw new InvalidArgumentException('Ownership transfer requires two different members.');
        }
        $from = $context->member((int) $data['from_user_id'], false);
        $to = $context->member((int) $data['to_user_id']);
        $units = 0;
        if ($book->ownership_model === 'unitised') {
            $units = (int) ($data['units_micro'] ?? 0);
            if ($units <= 0 || $units > $from->units_micro) {
                throw new InvalidArgumentException('Transfer exceeds owned units.');
            }
            [$capital] = ExactAllocation::multiplyDivide($from->capital_minor, $units, $from->units_micro);
        } else {
            $capital = (int) ($data['capital_minor'] ?? 0);
            if ($capital <= 0 || $capital > $from->capital_minor) {
                throw new InvalidArgumentException('Transfer exceeds owned capital.');
            }
        }
        if ($capital > 0) {
            $context->post([
                $context->ledger->line($book, 'MEMBER_CAPITAL', 'debit', $capital, $from->user_id),
                $context->ledger->line($book, 'MEMBER_CAPITAL', 'credit', $capital, $to->user_id),
            ], 'Approved transfer of existing member ownership');
        }
        $context->movement($from, 'ownership_transfer_out', -$units, -$capital, 0, ['counterparty_user_id' => $to->user_id]);
        $context->movement($to, 'ownership_transfer_in', $units, $capital, 0, ['counterparty_user_id' => $from->user_id]);

        return ['journal_id' => $context->journalId, 'units_transferred_micro' => $units, 'capital_transferred_minor' => $capital, 'money_moved_by_opfin' => false];
    }

    public function declareDistribution(ClubPostingContext $context): array
    {
        $book = $context->book;
        $data = $context->payload();
        $this->integrity->assertNavUsable($book, $context->date());
        $trial = $context->ledger->trialBalance($book, $context->date());
        $available = $trial['position']['surplus_minor'] - $context->ledger->debitBalance($book, 'RETAINED_EARNINGS');
        $amount = (int) $data['amount_minor'];
        if ($amount > $available) {
            throw new InvalidArgumentException('The declared distribution exceeds recorded undistributed earnings.');
        }
        $weightField = $book->ownership_model === 'unitised' ? 'units_micro' : 'capital_minor';
        // Economic ownership survives removal from an operational membership.
        $members = ClubMember::where('book_id', $book->id)->where($weightField, '>', 0)->orderBy('user_id')->get();
        $weights = [];
        foreach ($members as $member) {
            $weights[$member->user_id] = (int) $member->{$weightField};
        }
        $allocations = ExactAllocation::proportional($amount, $weights);
        $lines = [$context->ledger->line($book, 'RETAINED_EARNINGS', 'debit', $amount)];
        foreach ($members as $member) {
            $lines[] = $context->ledger->line($book, 'DISTRIBUTIONS_PAYABLE', 'credit', $allocations[$member->user_id], $member->user_id);
        }
        $context->post($lines, 'Declared member income distribution');
        $id = DB::table('club_distributions')->insertGetId([
            'reference' => (string) Str::uuid(), 'book_id' => $book->id, 'instruction_id' => $context->instruction->id,
            'journal_id' => $context->journalId, 'record_date' => $context->date(), 'amount_minor' => $amount,
            'allocation_basis' => $weightField, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($members as $member) {
            DB::table('club_distribution_allocations')->insert([
                'distribution_id' => $id, 'member_id' => $member->id, 'amount_minor' => $allocations[$member->user_id],
                'paid_minor' => 0, 'weight' => $weights[$member->user_id], 'created_at' => now(), 'updated_at' => now(),
            ]);
            $context->movement($member, 'distribution_declared', 0, 0, 0,
                ['distribution_id' => $id, 'entitlement_minor' => $allocations[$member->user_id]]);
        }
        $context->externalFlowMinor = -$amount;

        return ['journal_id' => $context->journalId, 'distribution_id' => $id, 'amount_minor' => $amount, 'allocations_minor' => $allocations];
    }

    public function payDistribution(ClubPostingContext $context): array
    {
        $book = $context->book;
        $data = $context->payload();
        $member = $context->member((int) $data['member_user_id'], false);
        $distribution = DB::table('club_distributions')->where('book_id', $book->id)->find((int) $data['distribution_id']);
        if (! $distribution) {
            throw new InvalidArgumentException('The distribution does not belong to this accounting book.');
        }
        $allocation = DB::table('club_distribution_allocations')->where('distribution_id', $distribution->id)
            ->where('member_id', $member->id)->lockForUpdate()->first();
        $gross = (int) $data['amount_minor'];
        $tax = (int) ($data['withholding_minor'] ?? 0);
        if (! $allocation || $gross > (int) $allocation->amount_minor - (int) $allocation->paid_minor
            || $tax < 0 || $tax >= $gross) {
            throw new InvalidArgumentException('Payment exceeds the outstanding distribution or has invalid withholding.');
        }
        $net = $gross - $tax;
        $lines = [$context->cash('debit', $net), $context->ledger->line($book, 'DISTRIBUTIONS_PAYABLE', 'debit', $gross, $member->user_id)];
        if ($tax > 0) {
            $lines[] = $context->ledger->line($book, 'TAX_PAYABLE', 'credit', $tax);
        }
        $context->post($lines, 'Recorded payment of member distribution');
        DB::table('club_distribution_allocations')->where('id', $allocation->id)
            ->update(['paid_minor' => (int) $allocation->paid_minor + $gross, 'updated_at' => now()]);
        $context->movement($member, 'distribution_paid', 0, 0, -$net,
            ['distribution_id' => $distribution->id, 'gross_minor' => $gross, 'withholding_minor' => $tax]);

        return ['journal_id' => $context->journalId, 'gross_minor' => $gross, 'net_payment_minor' => $net, 'withholding_minor' => $tax];
    }

    public function capitalCall(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $ids = [];
        foreach (array_unique($data['member_user_ids']) as $userId) {
            $context->member((int) $userId);
            $ids[] = DB::table('club_capital_calls')->insertGetId([
                'reference' => (string) Str::uuid(), 'book_id' => $context->book->id,
                'member_user_id' => (int) $userId, 'instruction_id' => $context->instruction->id,
                'due_date' => $data['due_date'], 'amount_minor' => (int) $data['amount_minor'],
                'paid_minor' => 0, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['capital_call_ids' => $ids, 'amount_per_member_minor' => (int) $data['amount_minor'], 'creates_loan_or_receivable' => false];
    }

    public function contributionPlan(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $context->member((int) $data['member_user_id']);
        $start = CarbonImmutable::parse($data['first_due_month'].'-01');
        $day = (int) $data['due_day'];
        $due = $start->day(min($day, $start->daysInMonth));
        if (! empty($data['end_date']) && $due->greaterThan(CarbonImmutable::parse($data['end_date']))) {
            throw new InvalidArgumentException('The plan ends before its first due date.');
        }
        $id = DB::table('club_contribution_plans')->insertGetId([
            'book_id' => $context->book->id, 'member_user_id' => (int) $data['member_user_id'],
            'instruction_id' => $context->instruction->id, 'amount_minor' => (int) $data['amount_minor'],
            'due_day' => $day, 'next_due_date' => $due->toDateString(), 'end_date' => $data['end_date'] ?? null,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['contribution_plan_id' => $id, 'next_due_date' => $due->toDateString(), 'creates_loan_or_receivable' => false];
    }
}
