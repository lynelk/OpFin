<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Models\ClubMember;
use App\Models\ClubStatement;
use App\Models\FinancialSpace;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ExactAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ClubReports
{
    public function __construct(private readonly ClubAccess $access, private readonly ClubLedger $ledger,
        private readonly ClubIntegrity $integrity, private readonly AuditLogger $audit) {}

    public function report(ClubBook $book, User $actor, string $start, string $end, ?int $memberUserId = null): array
    {
        $this->authorise($book, $actor, $memberUserId);
        $this->period($book, $start, $end);
        $trial = $this->ledger->trialBalance($book, $end);
        $opening = $this->ledger->trialBalance($book, CarbonImmutable::parse($start)->subDay()->toDateString());
        $members = $this->memberPositions($book, $end);
        $assets = $this->assetPositions($book, $end);
        $net = $trial['position']['net_assets_minor'];
        $weights = [];
        foreach ($members as $member) {
            $weight = $book->ownership_model === 'unitised' ? $member['units_micro'] : $member['capital_minor'];
            if ($weight > 0) {
                $weights[$member['user_id']] = $weight;
            }
        }
        $shares = $weights !== [] && $net >= 0 ? ExactAllocation::proportional($net, $weights) : [];
        foreach ($members as &$member) {
            $member['indicative_net_asset_share_minor'] = $shares[$member['user_id']] ?? null;
            $member['ownership_basis'] = $book->ownership_model;
        }
        unset($member);
        $calls = DB::table('club_capital_calls')->where('book_id', $book->id)
            ->when($memberUserId !== null, static fn ($q) => $q->where('member_user_id', $memberUserId))
            ->where('due_date', '<=', $end)->orderBy('due_date')->orderBy('id')->get()
            ->map(static fn ($call): array => ['id' => (int) $call->id, 'reference' => $call->reference,
                'member_user_id' => (int) $call->member_user_id, 'due_date' => $call->due_date,
                'amount_minor' => (int) $call->amount_minor, 'paid_minor' => (int) $call->paid_minor,
                'remaining_minor' => max(0, (int) $call->amount_minor - (int) $call->paid_minor),
                'status' => $call->status, 'is_receivable_or_loan' => false])->all();
        $movements = DB::table('club_member_movements as movement')->join('club_members as member', 'member.id', '=', 'movement.member_id')
            ->where('movement.book_id', $book->id)->whereBetween('movement.business_date', [$start, $end])
            ->when($memberUserId !== null, static fn ($q) => $q->where('member.user_id', $memberUserId))
            ->orderBy('movement.business_date')->orderBy('movement.id')
            ->select('movement.*', 'member.user_id');
        if ((clone $movements)->count() > 10000) {
            throw new InvalidArgumentException('This period has more than 10,000 ownership events. Select a narrower period; no events have been silently omitted.');
        }
        $memberMovements = $movements->get()->map(static fn ($row): array => [
            'id' => (int) $row->id, 'user_id' => (int) $row->user_id, 'type' => $row->type,
            'business_date' => $row->business_date, 'units_delta_micro' => (int) $row->units_delta_micro,
            'capital_delta_minor' => (int) $row->capital_delta_minor, 'cash_flow_minor' => (int) $row->cash_flow_minor,
            'instruction_id' => (int) $row->instruction_id, 'journal_id' => $row->journal_id === null ? null : (int) $row->journal_id,
            'evidence' => json_decode($row->evidence, true, 64, JSON_THROW_ON_ERROR),
        ])->all();
        $space = FinancialSpace::withTrashed()->findOrFail($book->financial_space_id);
        $common = [
            'report_type' => $memberUserId === null ? 'club_accounts' : 'member_capital_statement',
            'space' => ['id' => $space->id, 'public_id' => $space->public_id, 'name' => $space->name, 'type' => $space->type],
            'book' => ['id' => $book->id, 'public_id' => $book->public_id, 'currency' => $book->currency,
                'ownership_model' => $book->ownership_model, 'unit_scale' => $book->unit_scale,
                'cutover_date' => $book->cutover_date->toDateString(), 'policy_version' => $book->policy_version,
                'closed_through' => $book->closed_through?->toDateString()],
            'period_start' => $start, 'period_end' => $end,
            'member_positions' => $memberUserId === null ? $members : array_values(array_filter($members, static fn ($m): bool => $m['user_id'] === $memberUserId)),
            'member_movements' => $memberMovements, 'capital_calls' => $calls,
            'basis' => 'Recorded, approved Financial Space bookkeeping. Not a bank statement, independently verified market valuation, custody certificate or executed payout.',
            'provider_payment_execution' => false,
            'currency_conversion' => 'Not applied; each currency is a separate accounting book and ownership class.',
        ];
        if ($memberUserId !== null) {
            $before = array_values(array_filter($this->memberPositions($book, CarbonImmutable::parse($start)->subDay()->toDateString()),
                static fn ($m): bool => $m['user_id'] === $memberUserId));
            $common['member_user_id'] = $memberUserId;
            $common['opening_member_position'] = $before[0] ?? ['user_id' => $memberUserId, 'units_micro' => 0, 'capital_minor' => 0];
            $common['distributions'] = $this->distributions($book, $start, $end, $memberUserId);

            return $common;
        }
        $income = [];
        $openingAccounts = array_column($opening['accounts'], null, 'account_id');
        foreach ($trial['accounts'] as $account) {
            if (! in_array($account['kind'], ['income', 'expense'], true)) {
                continue;
            }
            $before = $openingAccounts[$account['account_id']] ?? ['debits_minor' => 0, 'credits_minor' => 0];
            $dr = $account['debits_minor'] - $before['debits_minor'];
            $cr = $account['credits_minor'] - $before['credits_minor'];
            $income[] = ['code' => $account['code'], 'name' => $account['name'], 'kind' => $account['kind'],
                'amount_minor' => $account['kind'] === 'income' ? $cr - $dr : $dr - $cr];
        }
        $units = array_sum(array_column($members, 'units_micro'));
        $navUnit = null;
        if ($book->ownership_model === 'unitised' && $units > 0 && $net >= 0) {
            [$floor, $remainder] = ExactAllocation::multiplyDivide($net, $book->unit_scale, $units);
            $navUnit = ['amount_minor_floor_per_unit' => $floor, 'remainder_numerator' => $remainder,
                'remainder_denominator' => $units, 'total_units_micro' => $units];
        }

        return $common + [
            'opening_position' => $opening['position'], 'trial_balance' => $trial,
            'balance_sheet' => $trial['position'], 'income_statement' => $income,
            'cash_flow' => $this->cashFlow($book, $start, $end),
            'investment_register' => $assets, 'net_asset_value' => ['net_assets_minor' => $net, 'per_unit' => $navUnit],
            'distributions' => $this->distributions($book, $start, $end),
            'performance' => $this->performance($book, $start, $end),
            'unposted_cashbook' => $this->integrity->unpostedCashbook($book, null, $end),
            'bank_statement_reconciliation' => ['independently_certified' => false,
                'instruction' => 'Read the linked treasury import, matching and confirmation records. A balanced journal is not a bank/provider match.'],
        ];
    }

    public function issue(ClubBook $book, User $actor, array $input): ClubStatement
    {
        $data = Validator::make($input, [
            'period_start' => ['required', 'date_format:Y-m-d'], 'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start', 'before_or_equal:today'],
            'member_user_id' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:160', 'regex:/^[A-Za-z0-9:._-]+$/D'],
        ])->validate();
        $memberId = isset($data['member_user_id']) ? (int) $data['member_user_id'] : null;
        $this->authorise($book, $actor, $memberId);
        $hash = hash('sha256', ClubLedger::canonical(['period_start' => $data['period_start'], 'period_end' => $data['period_end'],
            'member_user_id' => $memberId, 'actor_id' => $actor->id]));

        return DB::transaction(function () use ($book, $actor, $data, $memberId, $hash): ClubStatement {
            $locked = ClubBook::query()->lockForUpdate()->findOrFail($book->id);
            $this->authorise($locked, $actor, $memberId);
            $existing = ClubStatement::where('book_id', $book->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new InvalidArgumentException('The statement idempotency key belongs to a different request.');
                }

                return $existing;
            }
            if ($locked->status !== 'active') {
                throw new InvalidArgumentException('Opening accounting must be approved before issuing a statement.');
            }
            $checks = $this->integrity->check($locked);
            $critical = array_values(array_filter($checks['issues'], static fn ($issue): bool => $issue['code'] !== 'unposted_cashbook'));
            if ($critical !== []) {
                throw new RuntimeException('Accounting integrity exceptions must be resolved before statement issue.');
            }
            $snapshot = $this->report($locked, $actor, $data['period_start'], $data['period_end'], $memberId);
            $snapshot['issued_at'] = now()->toIso8601String();
            $snapshot['statement_reference'] = (string) Str::uuid();
            $snapshot['integrity_at_issue'] = ['balanced' => true, 'journal_head' => $locked->last_journal_hash,
                'unposted_cashbook_count_at_least' => count($this->integrity->unpostedCashbook($locked, null, $data['period_end'])),
                'bank_statement_reconciliation_certified' => false];
            $statement = ClubStatement::create([
                'reference' => $snapshot['statement_reference'], 'book_id' => $locked->id, 'member_user_id' => $memberId,
                'issued_by' => $actor->id, 'period_start' => $data['period_start'], 'period_end' => $data['period_end'],
                'snapshot' => $snapshot, 'content_hash' => hash('sha256', ClubLedger::canonical($snapshot)),
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
            ]);
            $this->audit->record('club.accounting.statement_issued', $actor, $statement, [
                'book_id' => $locked->id, 'member_user_id' => $memberId, 'content_hash' => $statement->content_hash,
            ]);

            return $statement;
        }, 3);
    }

    public function read(ClubBook $book, ClubStatement $statement, User $actor): ClubStatement
    {
        if ((int) $statement->book_id !== (int) $book->id) {
            throw new AuthorizationException('The statement does not belong to this book.');
        }
        $this->authorise($book, $actor, $statement->member_user_id === null ? null : (int) $statement->member_user_id);
        if (! hash_equals($statement->content_hash, hash('sha256', ClubLedger::canonical($statement->snapshot)))) {
            throw new RuntimeException('The frozen statement integrity hash does not match.');
        }
        $this->audit->record('club.accounting.statement_viewed', $actor, $statement, ['book_id' => $book->id]);

        return $statement;
    }

    public function authorise(ClubBook $book, User $actor, ?int $memberUserId): void
    {
        if ($actor->deleted_at !== null) {
            throw new AuthorizationException('An active account is required.');
        }
        if ($memberUserId !== null && $memberUserId === (int) $actor->id
            && ClubMember::where('book_id', $book->id)->where('user_id', $actor->id)->exists()) {
            // Former group members retain access to their own financial history,
            // never the whole club's records or other members' private allocations.
            return;
        }
        if (! $this->access->isOfficer($book, $actor)) {
            throw new AuthorizationException('A member may read only their own capital statement; wider accounting requires an authorised officer.');
        }
        if ($memberUserId !== null && ! ClubMember::where('book_id', $book->id)->where('user_id', $memberUserId)->exists()) {
            throw new InvalidArgumentException('The selected member has no position in this book.');
        }
    }

    public function memberPositions(ClubBook $book, string $date): array
    {
        return DB::table('club_member_movements as movement')->join('club_members as member', 'member.id', '=', 'movement.member_id')
            ->join('users as person', 'person.id', '=', 'member.user_id')
            ->where('movement.book_id', $book->id)->where('movement.business_date', '<=', $date)
            ->groupBy('member.id', 'member.user_id', 'person.name')->orderBy('member.user_id')
            ->select('member.id', 'member.user_id', 'person.name')
            ->selectRaw('SUM(movement.units_delta_micro) as units, SUM(movement.capital_delta_minor) as capital')
            ->get()->map(static fn ($member): array => ['member_id' => (int) $member->id, 'user_id' => (int) $member->user_id,
                'name' => $member->name, 'units_micro' => (int) $member->units, 'capital_minor' => (int) $member->capital])->all();
    }

    private function assetPositions(ClubBook $book, string $date): array
    {
        $rows = DB::table('club_asset_movements as movement')->join('club_assets as asset', 'asset.id', '=', 'movement.asset_id')
            ->where('movement.book_id', $book->id)->where('movement.business_date', '<=', $date)
            ->groupBy('asset.id', 'asset.reference', 'asset.name', 'asset.asset_class')->orderBy('asset.id')
            ->select('asset.id', 'asset.reference', 'asset.name', 'asset.asset_class')
            ->selectRaw('SUM(movement.quantity_delta_micro) as quantity, SUM(movement.cost_delta_minor) as cost, SUM(movement.carrying_delta_minor) as carrying')
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $valuation = DB::table('club_asset_movements as movement')->join('club_instructions as instruction', 'instruction.id', '=', 'movement.instruction_id')
                ->where('movement.asset_id', $row->id)->where('movement.business_date', '<=', $date)
                ->whereIn('movement.type', ['opening', 'valuation'])->orderByDesc('movement.business_date')->orderByDesc('movement.id')
                ->first(['movement.business_date', 'instruction.payload']);
            if (! $valuation) {
                $valuation = DB::table('club_asset_movements as movement')->join('club_instructions as instruction', 'instruction.id', '=', 'movement.instruction_id')
                    ->where('movement.asset_id', $row->id)->where('movement.business_date', '<=', $date)
                    ->where('movement.type', 'acquisition')->orderBy('movement.business_date')->orderBy('movement.id')
                    ->first(['movement.business_date', 'instruction.payload']);
            }
            $valuationDate = $valuation?->business_date;
            $payload = $valuation ? json_decode($valuation->payload, true, 64, JSON_THROW_ON_ERROR) : [];
            $result[] = ['asset_id' => (int) $row->id, 'reference' => $row->reference, 'name' => $row->name, 'asset_class' => $row->asset_class,
                'quantity_micro' => (int) $row->quantity, 'cost_minor' => (int) $row->cost,
                'carrying_value_minor' => (int) $row->carrying, 'unrealised_gain_loss_minor' => (int) $row->carrying - (int) $row->cost,
                'valuation_date' => $valuationDate, 'valuation_reference' => $payload['evidence_reference'] ?? null,
                'valuation_stale' => (int) $row->quantity > 0 && (! $valuationDate || CarbonImmutable::parse($valuationDate)
                    ->addDays((int) $book->policy['valuation_max_age_days'])->lessThan(CarbonImmutable::parse($date)))];
        }

        return $result;
    }

    private function cashFlow(ClubBook $book, string $start, string $end): array
    {
        $rows = DB::table('club_journal_entries as entry')->join('club_journals as journal', 'journal.id', '=', 'entry.journal_id')
            ->join('club_treasury_links as link', 'link.account_id', '=', 'entry.account_id')
            ->where('entry.book_id', $book->id)->whereBetween('journal.business_date', [$start, $end])
            ->groupBy('journal.event_type', 'entry.direction')->select('journal.event_type', 'entry.direction')
            ->selectRaw('SUM(entry.amount_minor) as amount')->get();
        $buckets = ['operating' => 0, 'investing' => 0, 'financing' => 0, 'opening_and_corrections' => 0];
        $items = [];
        foreach ($rows as $row) {
            $category = match ($row->event_type) {
                'asset_acquisition', 'asset_disposal' => 'investing',
                'contribution', 'redemption', 'distribution_pay' => 'financing',
                'opening', 'reverse_journal' => 'opening_and_corrections',
                default => 'operating',
            };
            $amount = $row->direction === 'debit' ? (int) $row->amount : -(int) $row->amount;
            $buckets[$category] = ExactAllocation::add($buckets[$category], $amount);
            $items[] = ['event_type' => $row->event_type, 'cash_direction' => $row->direction === 'debit' ? 'in' : 'out', 'amount_minor' => abs($amount), 'category' => $category];
        }

        return ['net_changes_minor' => $buckets, 'events' => $items, 'classification_policy' => 'Recorded cash movement by approved event type; review correction and opening items separately.'];
    }

    private function distributions(ClubBook $book, string $start, string $end, ?int $memberUserId = null): array
    {
        return DB::table('club_distribution_allocations as allocation')
            ->join('club_distributions as distribution', 'distribution.id', '=', 'allocation.distribution_id')
            ->join('club_members as member', 'member.id', '=', 'allocation.member_id')
            ->where('distribution.book_id', $book->id)->whereBetween('distribution.record_date', [$start, $end])
            ->when($memberUserId !== null, static fn ($query) => $query->where('member.user_id', $memberUserId))
            ->orderBy('distribution.record_date')->orderBy('allocation.id')
            ->get(['allocation.*', 'distribution.reference', 'distribution.record_date', 'distribution.allocation_basis', 'member.user_id'])
            ->map(static fn ($row): array => ['distribution_id' => (int) $row->distribution_id, 'reference' => $row->reference,
                'record_date' => $row->record_date, 'member_user_id' => (int) $row->user_id,
                'amount_minor' => (int) $row->amount_minor, 'paid_to_date_minor' => (int) $row->paid_minor,
                'allocation_basis' => $row->allocation_basis, 'weight_at_record_date' => (int) $row->weight])->all();
    }

    private function performance(ClubBook $book, string $start, string $end): array
    {
        $snapshots = DB::table('club_nav_snapshots')->where('book_id', $book->id)
            ->whereBetween('business_date', [$start, $end])->orderBy('business_date')->orderBy('id')->get();
        $factor = 1.0;
        $usable = false;
        $previous = null;
        foreach ($snapshots as $snapshot) {
            $before = (int) $snapshot->net_assets_before_minor;
            $after = (int) $snapshot->net_assets_after_minor;
            $flow = (int) $snapshot->external_flow_minor;
            if ($previous !== null && $before !== $previous) {
                return ['available' => false, 'reason' => 'NAV snapshots are not continuous; no return has been invented.'];
            }
            if ($before > 0) {
                $factor *= ($after - $flow) / $before;
                $usable = true;
            } elseif ($before < 0) {
                return ['available' => false, 'reason' => 'Non-positive capital requires a separate performance methodology.'];
            }
            $previous = $after;
        }
        if (! $usable || ! is_finite($factor)) {
            return ['available' => false, 'reason' => 'Insufficient positive-capital valuation history.'];
        }

        return ['available' => true, 'time_weighted_return_percent' => round(($factor - 1.0) * 100.0, 6),
            'method' => 'Chain-linked recorded NAV changes, excluding approved external subscriptions, redemptions and declared distributions.',
            'valuation_basis' => 'Recorded carrying values; stale or independently unverified values limit interpretation.',
            'is_annualised' => false, 'is_independent_performance_certification' => false];
    }

    private function period(ClubBook $book, string $start, string $end): void
    {
        Validator::make(['start' => $start, 'end' => $end], ['start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start', 'before_or_equal:today']])->validate();
        if ($start < $book->cutover_date->toDateString()) {
            throw new InvalidArgumentException('Select a reporting period on or after the approved cutover.');
        }
    }
}
