<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Models\ClubInstruction;
use App\Models\ClubMember;
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
    public const TYPES = ['opening', 'contribution', 'redemption', 'allocate_result', 'ownership_transfer',
        'capital_call', 'contribution_plan', 'contribution_plan_state', 'capital_call_cancel',
        'asset_acquisition', 'asset_valuation', 'asset_disposal', 'asset_split', 'cash_entry', 'journal',
        'reverse_journal', 'distribution_declare', 'distribution_pay', 'account_create', 'treasury_link', 'close_period'];

    private const NON_ECONOMIC = ['capital_call', 'contribution_plan', 'contribution_plan_state',
        'capital_call_cancel', 'account_create', 'treasury_link', 'close_period'];

    public function __construct(private readonly ClubAccess $access, private readonly ClubLedger $ledger,
        private readonly ClubIntegrity $integrity, private readonly ClubCapital $capital,
        private readonly ClubInvestments $investments, private readonly AuditLogger $audit,
        private readonly ClubPayloadValidator $validator, private readonly ClubCorrectionService $corrections) {}

    public function submit(ClubBook $book, User $maker, array $input): ClubInstruction
    {
        $this->access->book($book, $maker, write: true);
        $data = Validator::make($input, ['type' => ['required', 'in:'.implode(',', self::TYPES)],
            'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:160', 'regex:/^[A-Za-z0-9:._-]+$/D'],
            'payload' => ['required', 'array']])->validate();
        if (array_diff(array_keys($input), array_keys($data)) !== []) {
            throw new InvalidArgumentException('Unknown accounting instruction fields were supplied.');
        }
        $payload = $this->validatePayload($data['type'], $data['payload']);
        $this->assertOwnershipInput($book, $data['type'], $payload);
        $hash = hash('sha256', ClubLedger::canonical(['book_id' => $book->id, 'maker_id' => $maker->id,
            'type' => $data['type'], 'business_date' => $data['business_date'], 'payload' => $payload]));
        return DB::transaction(function () use ($book, $maker, $data, $payload, $hash): ClubInstruction {
            $locked = ClubBook::query()->lockForUpdate()->findOrFail($book->id);
            $this->access->book($locked, $maker, write: true);
            $existing = ClubInstruction::where('book_id', $locked->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new InvalidArgumentException('This idempotency key belongs to another actor or instruction.');
                }
                return $existing;
            }
            $this->assertBookState($locked, $data['type'], $data['business_date']);
            $item = ClubInstruction::create(['reference' => (string) Str::uuid(), 'book_id' => $locked->id,
                'type' => $data['type'], 'business_date' => $data['business_date'], 'idempotency_key' => $data['idempotency_key'],
                'payload' => $payload, 'payload_hash' => $hash, 'maker_id' => $maker->id, 'status' => 'pending']);
            $this->audit->record('club.accounting.instruction_submitted', $maker, $item,
                ['book_id' => $locked->id, 'type' => $item->type, 'payload_hash' => $hash]);
            return $item;
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
            if ($item->status === 'approved') { return $item; }
            if ($item->status !== 'pending') { throw new InvalidArgumentException('This instruction has already been decided.'); }
            $maker = User::query()->find($item->maker_id);
            if (! $maker) { throw new AuthorizationException('The instruction maker is no longer active.'); }
            $this->access->book($locked, $maker, write: true);
            $this->assertBookState($locked, $item->type, $item->business_date->toDateString());
            $result = $this->apply($locked, $item, $checker);
            $item->update(['status' => 'approved', 'checker_id' => $checker->id, 'decided_at' => now(), 'result' => $result]);
            $this->audit->record('club.accounting.instruction_approved', $checker, $item, ['book_id' => $locked->id,
                'maker_id' => $item->maker_id, 'type' => $item->type, 'payload_hash' => $item->payload_hash,
                'journal_id' => $result['journal_id'] ?? null]);
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
            if ($item->status !== 'pending') { throw new InvalidArgumentException('Only pending instructions can be simulated.'); }
            $this->assertBookState($locked, $item->type, $item->business_date->toDateString());
            $result = $this->apply($locked, $item, $actor);
            return ['simulation_only' => true, 'committed' => false, 'payload_hash' => $item->payload_hash,
                'result' => $result, 'resulting_trial_balance' => $this->ledger->trialBalance($locked),
                'notice' => 'Simulated identifiers are not committed records. Approval rechecks the current state.'];
        } finally { DB::rollBack(); }
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
            if (($cancel && (int) $item->maker_id !== (int) $actor->id) || (! $cancel && (int) $item->maker_id === (int) $actor->id)) {
                throw new AuthorizationException('A maker may cancel their own instruction; rejection requires a different checker.');
            }
            $state = $cancel ? 'cancelled' : 'rejected';
            if ($item->status === $state) { return $item; }
            if ($item->status !== 'pending') { throw new InvalidArgumentException('This instruction has already been decided.'); }
            $item->update(['status' => $state, 'checker_id' => $actor->id, 'decision_reason' => trim($reason), 'decided_at' => now()]);
            $this->audit->record('club.accounting.instruction_'.$state, $actor, $item, ['book_id' => $locked->id]);
            return $item;
        }, 3);
    }

    public function validatePayload(string $type, array $payload): array { return $this->validator->validate($type, $payload); }

    private function apply(ClubBook $book, ClubInstruction $item, User $checker): array
    {
        $context = new ClubPostingContext($book, $item, $checker, $this->ledger);
        $this->assertOwnershipInput($book, $item->type, $item->payload);
        $before = $this->ledger->trialBalance($book)['position']['net_assets_minor'];
        $unitsBefore = (int) ClubMember::where('book_id', $book->id)->sum('units_micro');
        if (! in_array($item->type, self::NON_ECONOMIC, true) && $item->type !== 'opening') {
            $latest = DB::table('club_nav_snapshots')->where('book_id', $book->id)->max('business_date');
            if ($latest !== null && $context->date() < $latest) {
                throw new InvalidArgumentException('Process financial events chronologically. Use an open-period correction with the original source evidence for an earlier error.');
            }
        }
        $result = match ($item->type) {
            'opening' => $this->capital->opening($context), 'contribution' => $this->capital->contribution($context),
            'redemption' => $this->capital->redemption($context), 'allocate_result' => $this->capital->allocateResult($context),
            'ownership_transfer' => $this->capital->transfer($context), 'capital_call' => $this->capital->capitalCall($context),
            'contribution_plan' => $this->capital->contributionPlan($context), 'contribution_plan_state' => $this->planState($context),
            'capital_call_cancel' => $this->cancelCall($context), 'asset_acquisition' => $this->investments->acquire($context),
            'asset_valuation' => $this->investments->value($context), 'asset_disposal' => $this->investments->dispose($context),
            'asset_split' => $this->investments->split($context), 'cash_entry' => $this->investments->cashEntry($context),
            'journal' => $this->investments->journal($context), 'reverse_journal' => $this->corrections->reverse($context),
            'distribution_declare' => $this->capital->declareDistribution($context), 'distribution_pay' => $this->capital->payDistribution($context),
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
            throw new RuntimeException('Accounting controls did not reconcile; the complete instruction has been rolled back.');
        }
        if (! in_array($item->type, self::NON_ECONOMIC, true)) {
            DB::table('club_nav_snapshots')->insert(['book_id' => $book->id, 'instruction_id' => $item->id,
                'business_date' => $context->date(), 'net_assets_before_minor' => $before,
                'net_assets_after_minor' => $trial['position']['net_assets_minor'], 'units_before_micro' => $unitsBefore,
                'units_after_micro' => (int) ClubMember::where('book_id', $book->id)->sum('units_micro'),
                'external_flow_minor' => $context->externalFlowMinor, 'created_at' => now()]);
        }
        return array_merge($result, ['money_moved_by_opfin' => false, 'currency' => $book->currency,
            'accounting_balanced' => true, 'journal_head' => $book->last_journal_hash,
            'unposted_cashbook_count_at_least' => count($checks['unposted_cashbook'])]);
    }

    private function assertBookState(ClubBook $book, string $type, string $date): void
    {
        if ($date < $book->cutover_date->toDateString() || ($book->closed_through && $date <= $book->closed_through->toDateString())) {
            throw new InvalidArgumentException('The business date is before cutover or within a closed period.');
        }
        if (($type === 'opening' && $book->status !== 'draft') || ($type !== 'opening'
            && ! in_array($type, ['account_create', 'treasury_link'], true) && $book->status !== 'active')) {
            throw new InvalidArgumentException('Approve the opening book before normal financial activity.');
        }
        if (! in_array($book->status, ['draft', 'active'], true)) {
            throw new InvalidArgumentException('This book is not open for accounting instructions.');
        }
    }

    private function assertOwnershipInput(ClubBook $book, string $type, array $payload): void
    {
        if (! in_array($type, ['redemption', 'ownership_transfer'], true)) { return; }
        $required = $book->ownership_model === 'unitised' ? 'units_micro' : 'capital_minor';
        $prohibited = $required === 'units_micro' ? 'capital_minor' : 'units_micro';
        if ((int) ($payload[$required] ?? 0) <= 0 || (isset($payload[$prohibited]) && (int) $payload[$prohibited] !== 0)) {
            throw new InvalidArgumentException('Supply only the positive ownership quantity appropriate to this book: '.$required.'.');
        }
    }

    private function createAccount(ClubPostingContext $context): array
    {
        $data = $context->payload(); $code = strtoupper($data['code']);
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
        if (! $plan || $plan->status === 'closed') { throw new InvalidArgumentException('An existing non-closed plan is required.'); }
        DB::table('club_contribution_plans')->where('id', $plan->id)->update(['status' => $data['status'], 'updated_at' => now()]);
        return ['plan_id' => (int) $plan->id, 'status' => $data['status']];
    }

    private function cancelCall(ClubPostingContext $context): array
    {
        $call = DB::table('club_capital_calls')->where('book_id', $context->book->id)->lockForUpdate()->find((int) $context->payload()['capital_call_id']);
        if (! $call || $call->status !== 'open' || (int) $call->paid_minor !== 0) {
            throw new InvalidArgumentException('Only an unpaid open call can be cancelled.');
        }
        DB::table('club_capital_calls')->where('id', $call->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        return ['capital_call_id' => (int) $call->id, 'status' => 'cancelled'];
    }

    private function closePeriod(ClubPostingContext $context): array
    {
        $date = $context->payload()['closed_through']; $book = $context->book;
        if ($date > $context->date() || $date < $book->cutover_date->toDateString()
            || ($book->closed_through && $date <= $book->closed_through->toDateString())) {
            throw new InvalidArgumentException('Choose a new completed period no later than the instruction date.');
        }
        if ($this->integrity->unpostedCashbook($book, null, $date) !== []) {
            throw new InvalidArgumentException('Post all cashbook activity through the closing date first.');
        }
        if (ClubInstruction::where('book_id', $book->id)->where('id', '<>', $context->instruction->id)
            ->where('status', 'pending')->where('business_date', '<=', $date)->exists()) {
            throw new InvalidArgumentException('Decide all pending instructions in the period before closure.');
        }
        $trial = $this->ledger->trialBalance($book, $date);
        if (! $trial['balanced']) { throw new RuntimeException('An unbalanced period cannot be closed.'); }
        $hash = hash('sha256', ClubLedger::canonical($trial));
        $id = DB::table('club_period_closures')->insertGetId(['book_id' => $book->id, 'instruction_id' => $context->instruction->id,
            'closed_through' => $date, 'trial_balance' => ClubLedger::canonical($trial), 'content_hash' => $hash, 'created_at' => now()]);
        $book->update(['closed_through' => $date]);
        return ['period_closure_id' => $id, 'closed_through' => $date, 'content_hash' => $hash];
    }
}
