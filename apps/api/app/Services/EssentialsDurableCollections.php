<?php

namespace App\Services;

use App\Domain\Essentials\AccountingRules as Rules;
use App\Models\CreditProfile;
use App\Models\CustomerWallet;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsCollectionInstruction;
use App\Models\EssentialsCreditLine;
use App\Models\EssentialsQuote;
use App\Models\EssentialsRepayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** No database transaction spans a provider request. */
class EssentialsDurableCollections
{
    public function __construct(private readonly EssentialsCustomerMutex $mutex,
        private readonly CpayEssentialsClient $cpay, private readonly AuditLogger $audit) {}

    public function request(User $user, int $advanceId, int $amount, string $key, ?int $walletId): EssentialsRepayment
    {
        Rules::amount($amount, true);
        if (! preg_match('/^[A-Za-z0-9:._-]{8,160}$/D', $key)) {
            throw new InvalidArgumentException('Use a stable repayment key of 8–160 letters, digits or :._- characters.');
        }
        return $this->mutex->run($user->id, function (User $current) use ($advanceId, $amount, $key, $walletId): EssentialsRepayment {
            $instruction = DB::transaction(function () use ($current, $advanceId, $amount, $key, $walletId): EssentialsCollectionInstruction {
                $advance = EssentialsAdvance::where('user_id', $current->id)->lockForUpdate()->findOrFail($advanceId);
                $existing = EssentialsRepayment::where('idempotency_key', $key)->first();
                if ($existing) {
                    $saved = EssentialsCollectionInstruction::where('repayment_id', $existing->id)->first();
                    if (! $saved || (int) $saved->user_id !== (int) $current->id
                        || (int) $saved->advance_id !== $advanceId || $saved->amount_minor !== $amount
                        || ($saved->snapshot['requested_wallet_id'] ?? null) !== $walletId) {
                        throw new InvalidArgumentException('This repayment key is unavailable for the supplied instruction.');
                    }
                    return $saved;
                }
                if (! in_array($advance->status, ['active', 'overdue'], true)) {
                    throw new InvalidArgumentException('Only an active outstanding Essentials obligation can be repaid.');
                }
                if (EssentialsCollectionInstruction::where('advance_id', $advanceId)->where('status', 'exception')->exists()) {
                    throw new InvalidArgumentException('Resolve the existing financial exception before requesting another collection.');
                }
                if (EssentialsRepayment::where('advance_id', $advanceId)->whereIn('status', ['pending', 'pending_provider_confirmation'])
                    ->whereNotIn('id', EssentialsCollectionInstruction::select('repayment_id'))->exists()) {
                    throw new InvalidArgumentException('An earlier collection requires reconciliation before another request.');
                }
                $reservations = EssentialsCollectionInstruction::where('advance_id', $advanceId)->get()
                    ->map(static fn ($row): array => ['reference' => (string) $row->id, 'status' => $row->status, 'amount_minor' => $row->amount_minor])->all();
                Rules::assertCanCollect($amount, $advance->outstanding_minor, $reservations);
                $wallets = CustomerWallet::where('user_id', $current->id)->where('status', 'active')->whereNotNull('verified_at');
                $wallet = $walletId !== null ? $wallets->whereKey($walletId)->first() : $wallets->where('is_default_repayment', true)->first();
                if (! $wallet || trim((string) $wallet->msisdn) === '') {
                    throw new InvalidArgumentException('Select an active verified repayment wallet. An unverified profile phone is not a substitute.');
                }
                if (! $this->cpay->configured('lender_repayment_path') || ! $this->cpay->configured('transaction_status_path')) {
                    throw new InvalidArgumentException('The approved collection and reconciliation routes must both be configured.');
                }
                $repayment = EssentialsRepayment::create(['reference' => (string) Str::uuid(), 'advance_id' => $advanceId,
                    'user_id' => $current->id, 'amount_minor' => $amount, 'principal_applied_minor' => 0,
                    'currency' => $advance->currency, 'status' => 'pending', 'idempotency_key' => $key]);
                $snapshot = ['user_id' => $current->id, 'advance_id' => $advanceId, 'repayment_id' => $repayment->id,
                    'financial_space_id' => $advance->financial_space_id, 'currency' => $advance->currency, 'amount_minor' => $amount,
                    'requested_wallet_id' => $walletId, 'wallet_id' => $wallet->id, 'payer' => $wallet->msisdn,
                    'payer_provider' => $wallet->provider, 'lender_partner_id' => $advance->lender_partner_id,
                    'partner_product_id' => $advance->partner_product_id,
                    'beneficiary_reference' => $advance->lender_contract_reference ?: $advance->reference,
                    'request_reference' => $repayment->reference, 'advance_reference' => $advance->reference,
                    'allocation_policy' => Rules::ALLOCATION_VERSION];
                $instruction = EssentialsCollectionInstruction::create(['repayment_id' => $repayment->id,
                    'advance_id' => $advanceId, 'user_id' => $current->id, 'wallet_id' => $wallet->id,
                    'amount_minor' => $amount, 'currency' => $advance->currency, 'environment' => $this->environment(),
                    'merchant_scope' => hash('sha256', (string) config('services.cpay.merchant_number')),
                    'instruction_hash' => hash('sha256', Rules::canonical($snapshot)), 'route_hash' => $this->routeHash(),
                    'snapshot' => $snapshot, 'status' => 'prepared']);
                $this->audit->record('essentials.collection.prepared', $current, $repayment,
                    ['instruction_id' => $instruction->id, 'instruction_hash' => $instruction->instruction_hash]);
                return $instruction;
            });
            if ($instruction->status === 'prepared') { $this->dispatch($instruction); }
            $this->applyObserved($instruction->fresh());
            return EssentialsRepayment::findOrFail($instruction->repayment_id);
        });
    }

    public function reconcile(EssentialsRepayment $repayment): EssentialsRepayment
    {
        return $this->mutex->runRetainedServicing($repayment->user_id, function () use ($repayment): EssentialsRepayment {
            $instruction = EssentialsCollectionInstruction::where('repayment_id', $repayment->id)->first();
            if (! $instruction) {
                throw new InvalidArgumentException('This older repayment has no durable instruction. Reconcile its original provider and allocation evidence; do not resubmit it.');
            }
            if (in_array($instruction->status, ['prepared', 'exception'], true)) { return $repayment->fresh(); }
            if ($instruction->status === 'confirmed_unapplied') {
                $this->applyObserved($instruction);
                return $repayment->fresh();
            }
            try {
                $this->assertRoute($instruction);
                $result = $this->cpay->status($instruction->provider_reference ?: $instruction->snapshot['request_reference'],
                    $instruction->provider_reference ? 'provider' : 'internal');
            } catch (Throwable) {
                return $repayment->fresh();
            }
            $this->observe($instruction, $result);
            $this->applyObserved($instruction->fresh());
            return $repayment->fresh();
        });
    }

    private function dispatch(EssentialsCollectionInstruction $instruction): void
    {
        $this->assertRoute($instruction);
        DB::transaction(function () use ($instruction): void {
            $locked = EssentialsCollectionInstruction::lockForUpdate()->findOrFail($instruction->id);
            if ($locked->status !== 'prepared') {
                throw new RuntimeException('This collection is already submitted; reconcile its existing identity.');
            }
            $wallet = CustomerWallet::where('user_id', $locked->user_id)->where('status', 'active')
                ->whereNotNull('verified_at')->find($locked->wallet_id);
            if (! $wallet || $wallet->msisdn !== $locked->snapshot['payer'] || $wallet->provider !== $locked->snapshot['payer_provider']) {
                throw new InvalidArgumentException('The verified source wallet changed before submission. No collection was sent.');
            }
            $locked->update(['status' => 'submitting', 'submitted_at' => now()]);
        });
        $snapshot = $instruction->snapshot;
        $advance = new EssentialsAdvance;
        $advance->forceFill(['reference' => $snapshot['advance_reference'], 'currency' => $snapshot['currency'],
            'lender_contract_reference' => $snapshot['beneficiary_reference'], 'lender_partner_id' => $snapshot['lender_partner_id'],
            'partner_product_id' => $snapshot['partner_product_id']]);
        $repayment = new EssentialsRepayment;
        $repayment->forceFill(['reference' => $snapshot['request_reference'], 'amount_minor' => $snapshot['amount_minor']]);
        try {
            $response = $this->cpay->collectRepayment($advance, $repayment, $snapshot['payer'], $snapshot['payer_provider']);
        } catch (Throwable) {
            EssentialsCollectionInstruction::whereKey($instruction->id)->where('status', 'submitting')->update(['status' => 'pending', 'updated_at' => now()]);
            EssentialsRepayment::whereKey($instruction->repayment_id)->update(['status' => 'pending_provider_confirmation']);
            return;
        }
        $this->observe($instruction->fresh(), $response);
    }

    private function observe(EssentialsCollectionInstruction $instruction, array $response): void
    {
        DB::transaction(function () use ($instruction, $response): void {
            $row = EssentialsCollectionInstruction::lockForUpdate()->findOrFail($instruction->id);
            $state = Rules::providerState('collection', (string) ($response['status'] ?? ''));
            $reference = trim((string) ($response['providerReference'] ?? $response['reference'] ?? ''));
            $error = null;
            if (strlen($reference) > 160 || preg_match('/[\x00-\x1F]/', $reference)) { $error = 'invalid_provider_reference'; }
            if (in_array($state, ['success', 'reversed'], true) && $reference === '') { $error = 'unattributed_provider_finality'; }
            if ($row->provider_reference && $reference !== '' && ! hash_equals($row->provider_reference, $reference)) { $error = 'provider_reference_changed'; }
            if (isset($response['currency']) && (string) $response['currency'] !== $row->currency) { $error = 'provider_currency_mismatch'; }
            if (isset($response['amountMinor']) && (! is_int($response['amountMinor']) || $response['amountMinor'] !== $row->amount_minor)) { $error = 'provider_amount_mismatch'; }
            if (isset($response['requestReference']) && (string) $response['requestReference'] !== $row->snapshot['request_reference']) { $error = 'provider_request_mismatch'; }
            $previous = match ($row->status) { 'applied', 'confirmed_unapplied' => 'success', default => $row->status };
            $state = $error ? 'exception' : Rules::transition($previous, $state);
            if ($reference !== '' && $state !== 'exception') {
                try {
                    DB::transaction(function () use ($row, $reference): void { $row->update(['provider_reference' => $reference]); });
                } catch (\Illuminate\Database\QueryException $exception) {
                    if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) { throw $exception; }
                    $state = 'exception'; $error = 'provider_reference_reused'; $row->refresh();
                }
            }
            $evidence = ['status' => $state, 'provider_reference' => $row->provider_reference,
                'request_reference' => $row->snapshot['request_reference'], 'exception_code' => $error];
            DB::table('essentials_collection_observations')->insertOrIgnore(['instruction_id' => $row->id,
                'evidence_hash' => hash('sha256', Rules::canonical($evidence)), 'evidence' => Rules::canonical($evidence), 'observed_at' => now()]);
            $storedState = $state === 'success' ? ($row->status === 'applied' ? 'applied' : 'confirmed_unapplied') : $state;
            $row->update(['status' => $storedState,
                'exception_code' => $state === 'exception' ? ($error ?? 'contradictory_terminal_state') : null, 'observed_at' => now()]);
            if ($state === 'exception') {
                $this->audit->record('essentials.collection.exception', null, $row, ['code' => $row->exception_code]);
            }
        });
    }

    private function applyObserved(EssentialsCollectionInstruction $instruction): void
    {
        if (in_array($instruction->status, ['prepared', 'submitting', 'pending', 'exception'], true)) { return; }
        DB::transaction(function () use ($instruction): void {
            $advance = EssentialsAdvance::lockForUpdate()->findOrFail($instruction->advance_id);
            $repayment = EssentialsRepayment::lockForUpdate()->findOrFail($instruction->repayment_id);
            $row = EssentialsCollectionInstruction::lockForUpdate()->findOrFail($instruction->id);
            if ($row->status === 'applied' || ($row->status === 'reversed' && $repayment->status === 'reversed')) { return; }
            if ($row->status === 'failed') {
                if ($repayment->status === 'successful') { throw new RuntimeException('A settled collection cannot become failed without reversal evidence.'); }
                $repayment->update(['status' => 'failed', 'provider_reference' => $row->provider_reference]);
                return;
            }
            if ($row->status === 'reversed' && $repayment->status !== 'successful') {
                $repayment->update(['status' => 'reversed', 'provider_reference' => $row->provider_reference]);
                return;
            }
            $schedule = $this->schedule($advance->id);
            $reversal = $row->status === 'reversed';
            if ($reversal) {
                $allocations = DB::table('essentials_collection_allocations')->where('instruction_id', $row->id)->get()
                    ->map(static fn ($item): array => ['schedule_item_id' => (int) $item->schedule_item_id,
                        'principal_minor' => (int) $item->principal_minor, 'interest_minor' => (int) $item->interest_minor, 'fees_minor' => (int) $item->fees_minor])->all();
                $updated = Rules::reverse($schedule, $allocations, $row->amount_minor);
                $principal = array_sum(array_column($allocations, 'principal_minor'));
                $interest = array_sum(array_column($allocations, 'interest_minor'));
                $fees = array_sum(array_column($allocations, 'fees_minor'));
            } else {
                if ($row->status !== 'confirmed_unapplied' || ! in_array($advance->status, ['active', 'overdue'], true)) {
                    throw new RuntimeException('Provider finality does not match an active repayment obligation.');
                }
                if ($advance->outstanding_minor !== array_sum(array_column($schedule, 'total_outstanding_minor'))) {
                    throw new RuntimeException('The advance and schedule disagree; no partial collection application is allowed.');
                }
                $allocation = Rules::allocate($schedule, $row->amount_minor, $row->snapshot['allocation_policy']);
                $updated = $allocation['rows']; $principal = $allocation['principal_minor'];
                $interest = $allocation['interest_minor']; $fees = $allocation['fees_minor'];
                foreach ($allocation['allocations'] as $item) {
                    DB::table('essentials_collection_allocations')->insert($item + ['instruction_id' => $row->id,
                        'policy_version' => $allocation['policy'], 'created_at' => now()]);
                }
            }
            $entries = [];
            foreach (['principal' => $principal, 'interest' => $interest, 'fees' => $fees] as $component => $amount) {
                if ($amount === 0) { continue; }
                $entries[] = ['account' => 'lender_'.$component.'_settlement_control', 'direction' => $reversal ? 'credit' : 'debit', 'amount_minor' => $amount];
                $entries[] = ['account' => 'borrower_'.$component.'_receivable_control', 'direction' => $reversal ? 'debit' : 'credit', 'amount_minor' => $amount];
            }
            $journal = ['advance_id' => $advance->id, 'repayment_id' => $repayment->id,
                'lender_partner_id' => $advance->lender_partner_id, 'event_type' => $reversal ? 'collection_reversed' : 'collection_applied',
                'currency' => $advance->currency, 'debits_minor' => $row->amount_minor, 'credits_minor' => $row->amount_minor,
                'entries' => $entries, 'evidence' => ['instruction_id' => $row->id, 'instruction_hash' => $row->instruction_hash,
                    'provider_reference' => $row->provider_reference, 'book_type' => 'lender_servicing_control_not_opfin_corporate_income']];
            DB::table('essentials_servicing_journals')->insert([
                'event_key' => $repayment->reference.($reversal ? ':reversed' : ':applied'),
                'content_hash' => hash('sha256', Rules::canonical($journal)), 'created_at' => now(),
            ] + array_replace($journal, ['entries' => Rules::canonical($entries), 'evidence' => Rules::canonical($journal['evidence'])]));
            foreach ($updated as $item) {
                $amounts = array_intersect_key($item, array_flip(['principal_outstanding_minor', 'interest_outstanding_minor', 'fees_outstanding_minor', 'total_outstanding_minor']));
                DB::table('essentials_repayment_schedule_items')->where('id', $item['id'])->update($amounts + [
                    'status' => $item['total_outstanding_minor'] === 0 ? 'paid' : 'partially_paid',
                    'settled_at' => $item['total_outstanding_minor'] === 0 ? now() : null, 'updated_at' => now()]);
            }
            if ($advance->funding_pool_id && $principal > 0) {
                $pool = DB::table('capital_mandates')->where('id', $advance->funding_pool_id)->lockForUpdate()->first();
                if (! $pool || (int) $pool->partner_id !== (int) $advance->lender_partner_id
                    || (! $reversal && (int) $pool->deployed_capital_minor < $principal)) {
                    throw new RuntimeException('The lender funding position does not reconcile; no balance was clamped.');
                }
                $deployed = $reversal ? Rules::add((int) $pool->deployed_capital_minor, $principal) : (int) $pool->deployed_capital_minor - $principal;
                DB::table('capital_mandates')->where('id', $pool->id)->update(['deployed_capital_minor' => $deployed, 'updated_at' => now()]);
                if ($deployed + (int) $pool->reserved_capital_minor > (int) $pool->committed_capital_minor) {
                    $this->audit->record('essentials.funding.reversal_capacity_exception', null, $advance, ['funding_pool_id' => $pool->id]);
                }
            }
            $newTotal = array_sum(array_column($updated, 'total_outstanding_minor'));
            $newPrincipal = array_sum(array_column($updated, 'principal_outstanding_minor'));
            $dueRows = array_values(array_filter($updated, static fn ($item): bool => $item['total_outstanding_minor'] > 0));
            $nextDue = $dueRows[0]['due_date'] ?? null;
            $newRepaid = $reversal ? $advance->repaid_minor - $row->amount_minor : Rules::add($advance->repaid_minor, $row->amount_minor);
            if ($newRepaid < 0 || $newRepaid > $advance->total_repayment_minor) { throw new RuntimeException('The cumulative repayment amount does not reconcile.'); }
            $advance->update(['outstanding_minor' => $newTotal, 'principal_outstanding_minor' => $newPrincipal,
                'repaid_minor' => $newRepaid, 'next_due_date' => $nextDue,
                'status' => $newTotal === 0 ? 'settled' : ($nextDue < now()->toDateString() ? 'overdue' : 'active'),
                'settled_at' => $newTotal === 0 ? now() : null]);
            if ($advance->financial_obligation_id) {
                DB::table('financial_obligations')->where('id', $advance->financial_obligation_id)->update([
                    'outstanding_amount_minor' => $newTotal, 'status' => $newTotal === 0 ? 'settled' : 'open', 'due_date' => $nextDue, 'updated_at' => now()]);
            }
            $quote = EssentialsQuote::findOrFail($advance->quote_id);
            $line = EssentialsCreditLine::lockForUpdate()->findOrFail($quote->credit_line_id);
            $lineOutstanding = $reversal ? Rules::add($line->outstanding_minor, $principal) : $line->outstanding_minor - $principal;
            if ($lineOutstanding < 0) { throw new RuntimeException('The lender line and principal allocation disagree.'); }
            $valid = $line->status === 'active' && (! $line->expires_at || $line->expires_at->isFuture());
            $available = ! $valid ? 0 : ($reversal ? max(0, $line->available_limit_minor - $principal)
                : min($line->approved_limit_minor - $lineOutstanding, Rules::add($line->available_limit_minor, $principal)));
            $line->update(['outstanding_minor' => $lineOutstanding, 'available_limit_minor' => max(0, $available)]);
            $repayment->update(['status' => $reversal ? 'reversed' : 'successful', 'principal_applied_minor' => $principal,
                'provider_reference' => $row->provider_reference, 'cpay_reference' => $row->provider_reference,
                'paid_at' => $reversal ? $repayment->paid_at : now(), 'metadata' => [
                    'allocation_policy' => $row->snapshot['allocation_policy'], 'instruction_id' => $row->id,
                    'interest_applied_minor' => $interest, 'fees_applied_minor' => $fees]]);
            $row->update(['status' => $reversal ? 'reversed' : 'applied', 'applied_at' => now()]);
            $profile = CreditProfile::where('user_id', $advance->user_id)->lockForUpdate()->first();
            if ($profile && $reversal) {
                $profile->update(['available_to_borrow_minor' => max(0, $profile->available_to_borrow_minor - $row->amount_minor),
                    'current_exposure_minor' => Rules::add($profile->current_exposure_minor, $row->amount_minor),
                    'total_outstanding_minor' => Rules::add($profile->total_outstanding_minor, $row->amount_minor)]);
            }
            $this->audit->record($reversal ? 'essentials.collection.reversed' : 'essentials.collection.applied', null, $repayment,
                ['instruction_id' => $row->id, 'principal_minor' => $principal, 'interest_minor' => $interest, 'fees_minor' => $fees]);
        });
    }

    private function schedule(int $advanceId): array
    {
        return DB::table('essentials_repayment_schedule_items')->where('advance_id', $advanceId)
            ->orderBy('due_date')->orderBy('id')->lockForUpdate()->get()->map(static function ($item): array {
                $row = (array) $item;
                foreach (['id', 'principal_original_minor', 'interest_original_minor', 'fees_original_minor',
                    'principal_outstanding_minor', 'interest_outstanding_minor', 'fees_outstanding_minor', 'total_outstanding_minor'] as $field) {
                    $row[$field] = (int) $row[$field];
                }
                return $row;
            })->all();
    }

    private function environment(): string
    {
        $environment = strtoupper((string) config('services.cpay.environment', 'sandbox'));
        if (! in_array($environment, ['SANDBOX', 'LIVE'], true)) { throw new InvalidArgumentException('The configured CPay environment is invalid.'); }
        if (app()->environment('production') && ($environment !== 'LIVE'
            || parse_url((string) config('services.cpay.base_url'), PHP_URL_SCHEME) !== 'https')) {
            throw new InvalidArgumentException('Production collection requires the approved live HTTPS route.');
        }
        return $environment;
    }

    private function routeHash(): string
    {
        return hash('sha256', Rules::canonical(['environment' => $this->environment(),
            'origin' => rtrim((string) config('services.cpay.base_url'), '/'), 'merchant' => (string) config('services.cpay.merchant_number'),
            'collection_path' => (string) config('services.cpay.lender_repayment_path'), 'status_path' => (string) config('services.cpay.transaction_status_path')]));
    }

    private function assertRoute(EssentialsCollectionInstruction $instruction): void
    {
        if (! hash_equals($instruction->route_hash, $this->routeHash())) {
            throw new RuntimeException('The provider route or environment changed. Reconcile through the original approved route; do not send a second collection.');
        }
        if (! hash_equals($instruction->instruction_hash, hash('sha256', Rules::canonical($instruction->snapshot)))) {
            throw new RuntimeException('The frozen collection identity does not match its integrity hash.');
        }
    }
}
