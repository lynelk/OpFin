<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Models\ClubInstruction;
use App\Models\ClubMember;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceTreasuryAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ClubInstructions
{
    public const TYPES = [
        'opening', 'contribution', 'redemption', 'allocate_result', 'ownership_transfer',
        'capital_call', 'contribution_plan', 'contribution_plan_state', 'capital_call_cancel',
        'asset_acquisition', 'asset_valuation', 'asset_disposal', 'asset_split',
        'cash_entry', 'journal', 'reverse_journal', 'distribution_declare', 'distribution_pay',
        'account_create', 'treasury_link', 'close_period',
    ];

    private const NON_ECONOMIC = ['capital_call', 'contribution_plan', 'contribution_plan_state',
        'capital_call_cancel', 'account_create', 'treasury_link', 'close_period'];

    public function __construct(
        private readonly ClubAccess $access,
        private readonly ClubLedger $ledger,
        private readonly ClubIntegrity $integrity,
        private readonly ClubCapital $capital,
        private readonly ClubInvestments $investments,
        private readonly AuditLogger $audit,
    ) {}

    public function submit(ClubBook $book, User $maker, array $input): ClubInstruction
    {
        $this->access->book($book, $maker, write: true);
        $envelope = Validator::make($input, [
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:160', 'regex:/^[A-Za-z0-9:._-]+$/D'],
            'payload' => ['required', 'array'],
        ])->validate();
        if (array_diff(array_keys($input), array_keys($envelope)) !== []) {
            throw new InvalidArgumentException('Unknown accounting instruction fields were supplied.');
        }
        $payload = $this->validatePayload($envelope['type'], $envelope['payload']);
        $hash = hash('sha256', ClubLedger::canonical([
            'book_id' => $book->id, 'maker_id' => $maker->id,
            'type' => $envelope['type'], 'business_date' => $envelope['business_date'], 'payload' => $payload,
        ]));

        return DB::transaction(function () use ($book, $maker, $envelope, $payload, $hash): ClubInstruction {
            $locked = ClubBook::query()->lockForUpdate()->findOrFail($book->id);
            $this->access->book($locked, $maker, write: true);
            $existing = ClubInstruction::where('book_id', $locked->id)->where('idempotency_key', $envelope['idempotency_key'])->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new InvalidArgumentException('This idempotency key belongs to another actor or accounting instruction.');
                }

                return $existing;
            }
            $this->assertBookState($locked, $envelope['type'], $envelope['business_date']);
            $instruction = ClubInstruction::create([
                'reference' => (string) Str::uuid(), 'book_id' => $locked->id, 'type' => $envelope['type'],
                'business_date' => $envelope['business_date'], 'idempotency_key' => $envelope['idempotency_key'],
                'payload' => $payload, 'payload_hash' => $hash, 'maker_id' => $maker->id, 'status' => 'pending',
            ]);
            $this->audit->record('club.accounting.instruction_submitted', $maker, $instruction, [
                'book_id' => $locked->id, 'type' => $instruction->type, 'payload_hash' => $hash,
            ]);

            return $instruction;
        }, 3);
    }

    public function approve(ClubBook $book, ClubInstruction $instruction, User $checker, string $payloadHash): ClubInstruction
    {
        return DB::transaction(function () use ($book, $instruction, $checker, $payloadHash): ClubInstruction {
            $locked = ClubBook::query()->lockForUpdate()->findOrFail($book->id);
            $this->access->book($locked, $checker, approve: true);
            $item = ClubInstruction::where('book_id', $locked->id)->lockForUpdate()->findOrFail($instruction->id);
            if ((int) $item->maker_id === (int) $checker->id) {
                throw new AuthorizationException('The maker cannot approve their own accounting instruction.');
            }
            if (! hash_equals($item->payload_hash, $payloadHash)) {
                throw new InvalidArgumentException('Approval must reference the exact submitted instruction hash.');
            }
            if ($item->status === 'approved') {
                return $item;
            }
            if ($item->status !== 'pending') {
                throw new InvalidArgumentException('This instruction has already been rejected or cancelled.');
            }
            $maker = User::query()->find($item->maker_id);
            if (! $maker) {
                throw new AuthorizationException('The instruction maker is no longer active.');
            }
            $this->access->book($locked, $maker, write: true);
            $this->assertBookState($locked, $item->type, $item->business_date->toDateString());
            $result = $this->apply($locked, $item, $checker);
            $item->update(['status' => 'approved', 'checker_id' => $checker->id, 'decided_at' => now(), 'result' => $result]);
            $this->audit->record('club.accounting.instruction_approved', $checker, $item, [
                'book_id' => $locked->id, 'maker_id' => $item->maker_id, 'type' => $item->type,
                'payload_hash' => $item->payload_hash, 'journal_id' => $result['journal_id'] ?? null,
            ]);

            return $item->fresh();
        }, 3);
    }

    public function preview(ClubBook $book, ClubInstruction $instruction, User $actor): array
    {
        $this->access->book($book, $actor, write: true);
        DB::beginTransaction();
        try {
            $locked = ClubBook::query()->lockForUpdate()->findOrFail($book->id);
            $item = ClubInstruction::where('book_id', $book->id)->findOrFail($instruction->id);
            if ($item->status !== 'pending') {
                throw new InvalidArgumentException('Only a pending instruction can be simulated.');
            }
            $this->assertBookState($locked, $item->type, $item->business_date->toDateString());
            $result = $this->apply($locked, $item, $actor);
            $trial = $this->ledger->trialBalance($locked);

            return ['simulation_only' => true, 'committed' => false, 'payload_hash' => $item->payload_hash,
                'result' => $result, 'resulting_trial_balance' => $trial,
                'notice' => 'Illustrative result from current recorded data. Simulated record identifiers are not committed records. Approval rechecks the current state.'];
        } finally {
            DB::rollBack();
        }
    }

    public function reject(ClubBook $book, ClubInstruction $instruction, User $actor, string $reason, bool $cancel = false): ClubInstruction
    {
        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A decision reason of 1–500 characters is required.');
        }

        return DB::transaction(function () use ($book, $instruction, $actor, $reason, $cancel): ClubInstruction {
            $locked = ClubBook::query()->lockForUpdate()->findOrFail($book->id);
            $this->access->book($locked, $actor, write: $cancel, approve: ! $cancel);
            $item = ClubInstruction::where('book_id', $locked->id)->lockForUpdate()->findOrFail($instruction->id);
            if (($cancel && (int) $item->maker_id !== (int) $actor->id)
                || (! $cancel && (int) $item->maker_id === (int) $actor->id)) {
                throw new AuthorizationException('A maker may cancel their instruction; rejection requires a different checker.');
            }
            $state = $cancel ? 'cancelled' : 'rejected';
            if ($item->status === $state) {
                return $item;
            }
            if ($item->status !== 'pending') {
                throw new InvalidArgumentException('A decided instruction cannot be cancelled or rejected.');
            }
            $item->update(['status' => $state, 'checker_id' => $actor->id, 'decision_reason' => trim($reason), 'decided_at' => now()]);
            $this->audit->record('club.accounting.instruction_'.$state, $actor, $item, ['book_id' => $locked->id]);

            return $item;
        }, 3);
    }

    private function apply(ClubBook $book, ClubInstruction $item, User $checker): array
    {
        $context = new ClubPostingContext($book, $item, $checker, $this->ledger);
        $before = $this->ledger->trialBalance($book)['position']['net_assets_minor'];
        $unitsBefore = (int) ClubMember::where('book_id', $book->id)->sum('units_micro');
        if (! in_array($item->type, self::NON_ECONOMIC, true) && $item->type !== 'opening') {
            $latest = DB::table('club_nav_snapshots')->where('book_id', $book->id)->max('business_date');
            if ($latest !== null && $context->date() < $latest) {
                throw new InvalidArgumentException('Process dated financial events chronologically. Correct prior periods with a current open-period adjustment and retain the original source date in its evidence.');
            }
        }
        $result = match ($item->type) {
            'opening' => $this->capital->opening($context),
            'contribution' => $this->capital->contribution($context),
            'redemption' => $this->capital->redemption($context),
            'allocate_result' => $this->capital->allocateResult($context),
            'ownership_transfer' => $this->capital->transfer($context),
            'capital_call' => $this->capital->capitalCall($context),
            'contribution_plan' => $this->capital->contributionPlan($context),
            'contribution_plan_state' => $this->planState($context),
            'capital_call_cancel' => $this->cancelCall($context),
            'asset_acquisition' => $this->investments->acquire($context),
            'asset_valuation' => $this->investments->value($context),
            'asset_disposal' => $this->investments->dispose($context),
            'asset_split' => $this->investments->split($context),
            'cash_entry' => $this->investments->cashEntry($context),
            'journal' => $this->investments->journal($context),
            'reverse_journal' => $this->investments->reverseJournal($context),
            'distribution_declare' => $this->capital->declareDistribution($context),
            'distribution_pay' => $this->capital->payDistribution($context),
            'account_create' => $this->createAccount($context),
            'treasury_link' => ['treasury_link' => (array) app(ClubBooks::class)->linkTreasury($book,
                FinancialSpaceTreasuryAccount::findOrFail((int) $item->payload['treasury_account_id']))],
            'close_period' => $this->closePeriod($context),
        };
        $book->refresh();
        $trial = $this->ledger->trialBalance($book);
        $checks = $this->integrity->check($book);
        $fatal = array_values(array_filter($checks['issues'], static fn (array $issue): bool => $issue['code'] !== 'unposted_cashbook'));
        if (! $trial['balanced'] || $fatal !== []) {
            throw new RuntimeException('The accounting controls did not reconcile; the complete instruction has been rolled back.');
        }
        if (! in_array($item->type, self::NON_ECONOMIC, true)) {
            DB::table('club_nav_snapshots')->insert([
                'book_id' => $book->id, 'instruction_id' => $item->id, 'business_date' => $context->date(),
                'net_assets_before_minor' => $before, 'net_assets_after_minor' => $trial['position']['net_assets_minor'],
                'units_before_micro' => $unitsBefore, 'units_after_micro' => (int) ClubMember::where('book_id', $book->id)->sum('units_micro'),
                'external_flow_minor' => $context->externalFlowMinor, 'created_at' => now(),
            ]);
        }

        return array_merge($result, ['money_moved_by_opfin' => false, 'currency' => $book->currency,
            'accounting_balanced' => true, 'journal_head' => $book->last_journal_hash,
            'unposted_cashbook_count_at_least' => count($checks['unposted_cashbook'])]);
    }

    private function assertBookState(ClubBook $book, string $type, string $date): void
    {
        if ($date < $book->cutover_date->toDateString() || ($book->closed_through && $date <= $book->closed_through->toDateString())) {
            throw new InvalidArgumentException('The business date is before cutover or within a closed accounting period.');
        }
        if (($type === 'opening' && $book->status !== 'draft')
            || ($type !== 'opening' && ! in_array($type, ['account_create', 'treasury_link'], true) && $book->status !== 'active')) {
            throw new InvalidArgumentException('Approve the opening book before recording normal financial activity.');
        }
        if (! in_array($book->status, ['draft', 'active'], true)) {
            throw new InvalidArgumentException('This book is not open for accounting instructions.');
        }
    }

    private function createAccount(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $code = strtoupper($data['code']);
        if (isset(ClubLedger::CHART[$code]) || str_starts_with($code, 'CASH-') || str_starts_with($code, 'INV-')) {
            throw new InvalidArgumentException('Reserved accounting codes cannot be replaced.');
        }
        $id = DB::table('club_accounts')->insertGetId(['book_id' => $context->book->id, 'code' => $code,
            'name' => $data['name'], 'kind' => $data['kind'], 'controlled' => false, 'created_at' => now(), 'updated_at' => now()]);

        return ['account_id' => $id, 'code' => $code];
    }

    private function planState(ClubPostingContext $context): array
    {
        $data = $context->payload();
        $plan = DB::table('club_contribution_plans')->where('book_id', $context->book->id)->lockForUpdate()->find((int) $data['plan_id']);
        if (! $plan || $plan->status === 'closed') {
            throw new InvalidArgumentException('An existing non-closed contribution plan is required.');
        }
        DB::table('club_contribution_plans')->where('id', $plan->id)->update(['status' => $data['status'], 'updated_at' => now()]);

        return ['plan_id' => (int) $plan->id, 'status' => $data['status']];
    }

    private function cancelCall(ClubPostingContext $context): array
    {
        $call = DB::table('club_capital_calls')->where('book_id', $context->book->id)->lockForUpdate()->find((int) $context->payload()['capital_call_id']);
        if (! $call || $call->status !== 'open' || (int) $call->paid_minor !== 0) {
            throw new InvalidArgumentException('Only an unpaid open capital call can be cancelled.');
        }
        DB::table('club_capital_calls')->where('id', $call->id)->update(['status' => 'cancelled', 'updated_at' => now()]);

        return ['capital_call_id' => (int) $call->id, 'status' => 'cancelled'];
    }

    private function closePeriod(ClubPostingContext $context): array
    {
        $date = $context->payload()['closed_through'];
        if ($date > $context->date() || $date < $context->book->cutover_date->toDateString()
            || ($context->book->closed_through && $date <= $context->book->closed_through->toDateString())) {
            throw new InvalidArgumentException('Choose a new completed accounting period no later than the instruction date.');
        }
        if ($this->integrity->unpostedCashbook($context->book, null, $date) !== []) {
            throw new InvalidArgumentException('All cashbook activity through the closing date must be posted first.');
        }
        if (ClubInstruction::where('book_id', $context->book->id)->where('id', '<>', $context->instruction->id)
            ->where('status', 'pending')->where('business_date', '<=', $date)->exists()) {
            throw new InvalidArgumentException('Decide all pending instructions in the period before closure.');
        }
        $trial = $context->ledger->trialBalance($context->book, $date);
        if (! $trial['balanced']) {
            throw new RuntimeException('An unbalanced period cannot be closed.');
        }
        $hash = hash('sha256', ClubLedger::canonical($trial));
        $id = DB::table('club_period_closures')->insertGetId([
            'book_id' => $context->book->id, 'instruction_id' => $context->instruction->id,
            'closed_through' => $date, 'trial_balance' => ClubLedger::canonical($trial), 'content_hash' => $hash, 'created_at' => now(),
        ]);
        $context->book->update(['closed_through' => $date]);

        return ['period_closure_id' => $id, 'closed_through' => $date, 'content_hash' => $hash];
    }

    public function validatePayload(string $type, array $payload): array
    {
        $positive = ['required', 'integer', 'min:1', 'max:9007199254740991'];
        $nonnegative = ['nullable', 'integer', 'min:0', 'max:9007199254740991'];
        $id = ['required', 'integer', 'min:1'];
        $cash = ['treasury_account_id' => $id, 'treasury_transaction_id' => ['nullable', 'integer', 'min:1']];
        $rules = ['evidence_reference' => ['required', 'string', 'min:3', 'max:255', 'not_regex:/[\x00-\x1F]/'],
            'description' => ['nullable', 'string', 'max:300']];
        $specific = match ($type) {
            'opening' => [
                'members' => ['required', 'array', 'max:1000'], 'members.*' => ['array:user_id,capital_minor,units_micro'],
                'members.*.user_id' => $id, 'members.*.capital_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
                'members.*.units_micro' => $nonnegative, 'balances' => ['required', 'array', 'max:500'],
                'balances.*' => ['array:account_code,direction,amount_minor'], 'balances.*.account_code' => ['required', 'string', 'max:80'],
                'balances.*.direction' => ['required', 'in:debit,credit'], 'balances.*.amount_minor' => $positive,
                'assets' => ['nullable', 'array', 'max:250'], 'assets.*' => ['array:name,asset_class,quantity_micro,cost_minor,carrying_value_minor'],
                'assets.*.name' => ['required', 'string', 'max:160'], 'assets.*.asset_class' => ['required', 'string', 'max:32'],
                'assets.*.quantity_micro' => $positive, 'assets.*.cost_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
                'assets.*.carrying_value_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
            ],
            'contribution' => $cash + ['member_user_id' => $id, 'amount_minor' => $positive, 'capital_call_id' => ['nullable', 'integer', 'min:1']],
            'redemption' => $cash + ['member_user_id' => $id, 'units_micro' => $nonnegative, 'capital_minor' => $nonnegative, 'withholding_minor' => $nonnegative],
            'ownership_transfer' => ['from_user_id' => $id, 'to_user_id' => $id, 'units_micro' => $nonnegative, 'capital_minor' => $nonnegative],
            'allocate_result' => [],
            'capital_call' => ['member_user_ids' => ['required', 'array', 'min:1', 'max:1000'], 'member_user_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
                'amount_minor' => $positive, 'due_date' => ['required', 'date_format:Y-m-d']],
            'contribution_plan' => ['member_user_id' => $id, 'amount_minor' => $positive, 'due_day' => ['required', 'integer', 'min:1', 'max:31'],
                'first_due_month' => ['required', 'date_format:Y-m'], 'end_date' => ['nullable', 'date_format:Y-m-d']],
            'contribution_plan_state' => ['plan_id' => $id, 'status' => ['required', 'in:active,paused,closed']],
            'capital_call_cancel' => ['capital_call_id' => $id],
            'asset_acquisition' => $cash + ['asset_id' => ['nullable', 'integer', 'min:1'], 'name' => ['required_without:asset_id', 'string', 'max:160'],
                'asset_class' => ['required_without:asset_id', 'string', 'max:32'], 'quantity_micro' => $positive, 'amount_minor' => $positive, 'fee_minor' => $nonnegative],
            'asset_valuation' => ['asset_id' => $id, 'carrying_value_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991']],
            'asset_disposal' => $cash + ['asset_id' => $id, 'quantity_micro' => $positive, 'amount_minor' => $positive, 'fee_minor' => $nonnegative],
            'asset_split' => ['asset_id' => $id, 'numerator' => ['required', 'integer', 'min:1', 'max:1000000'], 'denominator' => ['required', 'integer', 'min:1', 'max:1000000']],
            'cash_entry' => $cash + ['amount_minor' => $positive, 'direction' => ['required', 'in:credit,debit'],
                'allocations' => ['required', 'array', 'min:1', 'max:250'], 'allocations.*' => ['array:account_code,amount_minor,member_user_id'],
                'allocations.*.account_code' => ['required', 'string', 'max:80'], 'allocations.*.amount_minor' => $positive,
                'allocations.*.member_user_id' => ['nullable', 'integer', 'min:1']],
            'journal' => ['description' => ['required', 'string', 'max:300'], 'entries' => ['required', 'array', 'min:2', 'max:250'],
                'entries.*' => ['array:account_code,direction,amount_minor'], 'entries.*.account_code' => ['required', 'string', 'max:80'],
                'entries.*.direction' => ['required', 'in:debit,credit'], 'entries.*.amount_minor' => $positive],
            'reverse_journal' => ['original_journal_id' => $id, 'description' => ['required', 'string', 'min:5', 'max:300']],
            'distribution_declare' => ['amount_minor' => $positive],
            'distribution_pay' => $cash + ['member_user_id' => $id, 'distribution_id' => $id, 'amount_minor' => $positive, 'withholding_minor' => $nonnegative],
            'account_create' => ['code' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9_-]{2,39}$/D'],
                'name' => ['required', 'string', 'max:160'], 'kind' => ['required', 'in:asset,liability,equity,income,expense']],
            'treasury_link' => ['treasury_account_id' => $id],
            'close_period' => ['closed_through' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']],
            default => throw new InvalidArgumentException('Unknown club accounting instruction type.'),
        };
        $rules = array_replace($rules, $specific);
        $allowed = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], array_keys($rules)));
        if (array_diff(array_keys($payload), $allowed) !== []) {
            throw new InvalidArgumentException('The payload contains fields not supported by this instruction type.');
        }
        $clean = Validator::make($payload, $rules)->validate();
        $normalise = function (array $values) use (&$normalise): array {
            foreach ($values as $key => &$value) {
                if (is_array($value)) {
                    $value = $normalise($value);
                } elseif ($value !== null && (str_ends_with((string) $key, '_minor') || str_ends_with((string) $key, '_micro')
                    || str_ends_with((string) $key, '_id') || in_array($key, ['due_day', 'numerator', 'denominator'], true))) {
                    if (is_bool($value) || is_float($value) || ! preg_match('/^[0-9]+$/D', (string) $value)) {
                        throw new InvalidArgumentException('Amounts, quantities and identifiers must be integers, never floating-point values.');
                    }
                    $value = (int) $value;
                }
            }
            unset($value);

            return $values;
        };

        return $normalise($clean);
    }
}
