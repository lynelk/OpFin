<?php

namespace App\Services;

use App\Models\CustomerWallet;
use App\Models\EssentialsAccount;
use App\Models\EssentialsAffordabilityAssessment;
use App\Models\EssentialsBillPlan;
use App\Models\EssentialsBiller;
use App\Models\EssentialsOwnMoneyPayment;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class EssentialsBillPlanningService
{
    private const FREQUENCIES = ['once', 'weekly', 'fortnightly', 'monthly', 'quarterly', 'annually'];

    private const PAYMENT_OPEN_STATUSES = [
        'prepared',
        'collection_pending',
        'collection_unknown',
        'fulfilment_pending',
        'refund_pending',
    ];

    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly CpayEssentialsClient $cpay,
        private readonly AuditLogger $audit,
    ) {}

    public function plans(User $user, ?int $financialSpaceId = null): array
    {
        $spaceId = $this->resolveSpaceId($user, $financialSpaceId);

        return EssentialsBillPlan::query()
            ->where('user_id', $user->id)
            ->where('financial_space_id', $spaceId)
            ->orderByDesc('active')
            ->orderBy('next_due_date')
            ->get()
            ->all();
    }

    public function createPlan(User $user, array $data): EssentialsBillPlan
    {
        $spaceId = $this->resolveSpaceId($user, isset($data['financial_space_id']) ? (int) $data['financial_space_id'] : null);
        $account = $this->account($user, (int) $data['essentials_account_id'], $spaceId);
        $frequency = strtolower(trim((string) $data['frequency']));

        if (! in_array($frequency, self::FREQUENCIES, true)) {
            throw new InvalidArgumentException('Choose a supported bill frequency.');
        }

        $plan = EssentialsBillPlan::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'expected_amount_minor' => (int) $data['expected_amount_minor'],
            'currency' => strtoupper((string) ($data['currency'] ?? 'UGX')),
            'frequency' => $frequency,
            'next_due_date' => Carbon::parse((string) $data['next_due_date'])->toDateString(),
            'necessary' => (bool) ($data['necessary'] ?? true),
            'reminder_days_before' => (int) ($data['reminder_days_before'] ?? 3),
            'active' => true,
            'metadata' => (array) ($data['metadata'] ?? []),
        ]);

        $this->audit->record('essentials.bill_plan.created', $user, $plan, [
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
        ]);

        return $plan->fresh();
    }

    public function updatePlan(User $user, int $planId, array $data): EssentialsBillPlan
    {
        $plan = EssentialsBillPlan::query()->where('user_id', $user->id)->findOrFail($planId);
        $this->resolveSpaceId($user, (int) $plan->financial_space_id);

        if (isset($data['frequency'])) {
            $frequency = strtolower(trim((string) $data['frequency']));
            if (! in_array($frequency, self::FREQUENCIES, true)) {
                throw new InvalidArgumentException('Choose a supported bill frequency.');
            }
            $data['frequency'] = $frequency;
        }

        if (isset($data['essentials_account_id'])) {
            $account = $this->account($user, (int) $data['essentials_account_id'], (int) $plan->financial_space_id);
            $data['essentials_account_id'] = $account->id;
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }
        if (isset($data['next_due_date'])) {
            $data['next_due_date'] = Carbon::parse((string) $data['next_due_date'])->toDateString();
        }

        $plan->update($data);
        $this->audit->record('essentials.bill_plan.updated', $user, $plan, [
            'changed_fields' => array_keys($data),
        ]);

        return $plan->fresh();
    }

    public function deletePlan(User $user, int $planId): void
    {
        $plan = EssentialsBillPlan::query()->where('user_id', $user->id)->findOrFail($planId);
        $this->resolveSpaceId($user, (int) $plan->financial_space_id);

        $openPayment = EssentialsOwnMoneyPayment::query()
            ->where('bill_plan_id', $plan->id)
            ->whereIn('status', self::PAYMENT_OPEN_STATUSES)
            ->exists();

        if ($openPayment) {
            throw new InvalidArgumentException('This bill plan has a payment in progress. Finish or resolve it before removing the plan.');
        }

        $plan->delete();
        $this->audit->record('essentials.bill_plan.deleted', $user, $plan, [
            'financial_space_id' => $plan->financial_space_id,
        ]);
    }

    public function assess(User $user, array $data): EssentialsAffordabilityAssessment
    {
        $spaceId = $this->resolveSpaceId($user, isset($data['financial_space_id']) ? (int) $data['financial_space_id'] : null);
        $account = $this->account($user, (int) $data['essentials_account_id'], $spaceId);
        $plan = isset($data['bill_plan_id'])
            ? EssentialsBillPlan::query()
                ->where('user_id', $user->id)
                ->where('financial_space_id', $spaceId)
                ->where('essentials_account_id', $account->id)
                ->findOrFail((int) $data['bill_plan_id'])
            : null;

        $amountMinor = (int) $data['bill_amount_minor'];
        $dueDate = Carbon::parse((string) $data['bill_due_date'])->startOfDay();
        $horizonEnd = Carbon::parse((string) ($data['horizon_end'] ?? $dueDate->copy()->addDays(90)->toDateString()))->endOfDay();
        $currency = strtoupper((string) ($data['currency'] ?? $plan?->currency ?? 'UGX'));
        $proposedRepayment = max(0, (int) ($data['proposed_repayment_minor'] ?? 0));

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Bill amount must be positive.');
        }
        if ($horizonEnd->lt($dueDate)) {
            throw new InvalidArgumentException('Affordability horizon cannot end before the bill due date.');
        }
        if ($horizonEnd->diffInDays($dueDate) > 366) {
            throw new InvalidArgumentException('Affordability horizon cannot exceed 366 days.');
        }

        $recordedAvailable = (int) DB::table('financial_accounts')
            ->where('user_id', $user->id)
            ->where('financial_space_id', $spaceId)
            ->where('currency', $currency)
            ->where('active', true)
            ->sum('balance_minor');

        $projectedIncome = (int) DB::table('financial_calendar_events')
            ->where('user_id', $user->id)
            ->where('financial_space_id', $spaceId)
            ->where('currency', $currency)
            ->where('status', 'upcoming')
            ->where('direction', 'income')
            ->whereBetween('scheduled_for', [$dueDate->copy()->startOfDay(), $horizonEnd])
            ->sum('amount_minor');

        $calendarOutflows = (int) DB::table('financial_calendar_events')
            ->where('user_id', $user->id)
            ->where('financial_space_id', $spaceId)
            ->where('currency', $currency)
            ->where('status', 'upcoming')
            ->where('direction', 'expense')
            ->whereBetween('scheduled_for', [$dueDate->copy()->startOfDay(), $horizonEnd])
            ->sum('amount_minor');

        $plannedBills = $this->plannedBillOutflows(
            $user,
            $spaceId,
            $currency,
            $dueDate,
            $horizonEnd,
            $plan?->id,
        );

        if ($plan && $plan->active && $plan->frequency !== 'once') {
            $plannedBills += $this->futureOccurrences(
                (int) $plan->expected_amount_minor,
                (string) $plan->frequency,
                $dueDate,
                $horizonEnd,
            );
        }

        $scheduledRepayments = (int) DB::table('essentials_repayment_schedule_items as schedule')
            ->join('essentials_advances as advances', 'advances.id', '=', 'schedule.advance_id')
            ->where('advances.user_id', $user->id)
            ->where('advances.financial_space_id', $spaceId)
            ->where('advances.currency', $currency)
            ->whereIn('advances.status', ['active', 'overdue'])
            ->where('schedule.total_outstanding_minor', '>', 0)
            ->whereBetween('schedule.due_date', [$dueDate->toDateString(), $horizonEnd->toDateString()])
            ->sum('schedule.total_outstanding_minor');

        $scheduledOutflows = $calendarOutflows + $plannedBills + $scheduledRepayments;
        $cashAfterProtectedOutflows = max(0, $recordedAvailable - $scheduledOutflows);
        $ownMoneyCapacity = min($amountMinor, $cashAfterProtectedOutflows);
        $gap = max(0, $amountMinor - $ownMoneyCapacity);
        $minimumProjectedBalance = $recordedAvailable + $projectedIncome - $scheduledOutflows - $amountMinor - $proposedRepayment;

        $classification = match (true) {
            $minimumProjectedBalance < 0 => 'not_affordable_on_recorded_data',
            $gap === 0 => 'own_money_ready',
            $ownMoneyCapacity > 0 => 'partial_gap',
            default => 'financing_gap',
        };

        $snapshot = [
            'recorded_data_only' => true,
            'financial_accounts_minor' => $recordedAvailable,
            'calendar_income_minor' => $projectedIncome,
            'calendar_expenses_minor' => $calendarOutflows,
            'other_bill_plans_minor' => $plannedBills,
            'existing_repayments_minor' => $scheduledRepayments,
            'proposed_repayment_minor' => $proposedRepayment,
            'calculated_at' => now()->toISOString(),
            'warning' => 'This assessment uses recorded OpFin data. Missing or stale income and obligations remain unknown rather than being invented.',
        ];

        $assessment = EssentialsAffordabilityAssessment::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'bill_plan_id' => $plan?->id,
            'bill_amount_minor' => $amountMinor,
            'bill_due_date' => $dueDate->toDateString(),
            'horizon_end' => $horizonEnd->toDateString(),
            'currency' => $currency,
            'recorded_available_minor' => $recordedAvailable,
            'own_money_capacity_minor' => $ownMoneyCapacity,
            'financing_gap_minor' => $gap,
            'projected_income_minor' => $projectedIncome,
            'scheduled_outflows_minor' => $scheduledOutflows,
            'proposed_repayment_minor' => $proposedRepayment,
            'minimum_projected_balance_minor' => $minimumProjectedBalance,
            'classification' => $classification,
            'snapshot' => $snapshot,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->audit->record('essentials.affordability.assessed', $user, $assessment, [
            'financial_space_id' => $spaceId,
            'classification' => $classification,
            'financing_gap_minor' => $gap,
        ]);

        return $assessment->fresh();
    }

    public function payments(User $user, ?int $financialSpaceId = null): array
    {
        $spaceId = $this->resolveSpaceId($user, $financialSpaceId);

        return EssentialsOwnMoneyPayment::query()
            ->where('user_id', $user->id)
            ->where('financial_space_id', $spaceId)
            ->latest()
            ->limit(100)
            ->get()
            ->all();
    }

    public function startOwnMoneyPayment(User $user, array $data): EssentialsOwnMoneyPayment
    {
        $spaceId = $this->resolveSpaceId($user, isset($data['financial_space_id']) ? (int) $data['financial_space_id'] : null);
        $account = $this->account($user, (int) $data['essentials_account_id'], $spaceId);
        if ($account->verification_status !== 'verified') {
            throw new InvalidArgumentException('Verify the bill account before paying it.');
        }

        $assessment = EssentialsAffordabilityAssessment::query()
            ->where('user_id', $user->id)
            ->where('financial_space_id', $spaceId)
            ->where('essentials_account_id', $account->id)
            ->findOrFail((int) $data['affordability_assessment_id']);

        if ($assessment->expires_at->isPast()) {
            throw new InvalidArgumentException('This affordability check has expired. Refresh it before paying.');
        }

        $amountMinor = (int) $data['amount_minor'];
        if ($amountMinor <= 0 || $amountMinor > (int) $assessment->own_money_capacity_minor) {
            throw new InvalidArgumentException('The payment amount exceeds the own-money capacity in the current affordability check.');
        }

        $wallet = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('verified_at')
            ->find((int) $data['wallet_id']);
        if (! $wallet) {
            throw new InvalidArgumentException('Choose a verified wallet that belongs to your OpFin profile.');
        }

        $key = trim((string) $data['idempotency_key']);
        if ($key === '') {
            throw new InvalidArgumentException('A payment idempotency key is required.');
        }

        $instruction = [
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'bill_plan_id' => $assessment->bill_plan_id,
            'affordability_assessment_id' => $assessment->id,
            'wallet_id' => $wallet->id,
            'amount_minor' => $amountMinor,
            'currency' => $assessment->currency,
        ];
        $instructionHash = hash('sha256', json_encode($instruction, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $existing = EssentialsOwnMoneyPayment::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            $evidence = (array) $existing->evidence;
            if (($evidence['instruction_hash'] ?? null) !== $instructionHash) {
                throw new InvalidArgumentException('This payment idempotency key was already used for a different instruction.');
            }

            return $existing;
        }

        if (EssentialsOwnMoneyPayment::query()
            ->where('user_id', $user->id)
            ->where('essentials_account_id', $account->id)
            ->whereIn('status', self::PAYMENT_OPEN_STATUSES)
            ->exists()) {
            throw new InvalidArgumentException('A payment is already in progress for this bill account.');
        }

        $payment = EssentialsOwnMoneyPayment::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'bill_plan_id' => $assessment->bill_plan_id,
            'affordability_assessment_id' => $assessment->id,
            'wallet_id' => $wallet->id,
            'amount_minor' => $amountMinor,
            'currency' => $assessment->currency,
            'status' => 'prepared',
            'idempotency_key' => $key,
            'evidence' => [
                'instruction_hash' => $instructionHash,
                'affordability_reference' => $assessment->reference,
                'prepared_at' => now()->toISOString(),
            ],
        ]);

        $this->event($payment, 'prepared', ['instruction_hash' => $instructionHash]);

        try {
            $collection = $this->mobileMoney->collect([
                'user_id' => $user->id,
                'amount_minor' => $amountMinor,
                'currency' => $assessment->currency,
                'phone' => $wallet->msisdn,
                'idempotency_key' => 'essentials-own:'.$key,
                'internal_reference' => $payment->reference,
                'purpose' => 'opfin_essentials_own_money',
                'source_type' => 'essentials_own_money_payment',
                'source_id' => $payment->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $payment->update([
                'status' => 'collection_unknown',
                'evidence' => array_merge((array) $payment->evidence, [
                    'collection_state' => 'unknown',
                    'collection_error_type' => $exception::class,
                ]),
            ]);
            $this->event($payment->fresh(), 'collection_unknown', ['error_type' => $exception::class]);

            return $payment->fresh();
        }

        $payment->update([
            'collection_transaction_id' => $collection->id,
            'status' => $collection->status === MobileMoneyTransaction::STATUS_SUCCESSFUL ? 'collection_confirmed' : 'collection_pending',
            'evidence' => array_merge((array) $payment->evidence, [
                'collection_reference' => $collection->internal_reference,
                'collection_provider_reference' => $collection->provider_reference,
                'collection_status' => $collection->status,
            ]),
        ]);
        $this->event($payment->fresh(), 'collection_recorded', [
            'mobile_money_transaction_id' => $collection->id,
            'status' => $collection->status,
        ]);

        if ($collection->status === MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            return $this->reconcileOwnMoneyPayment($user, $payment->id);
        }

        return $payment->fresh();
    }

    public function reconcileOwnMoneyPayment(User $user, int $paymentId): EssentialsOwnMoneyPayment
    {
        $payment = EssentialsOwnMoneyPayment::query()->where('user_id', $user->id)->findOrFail($paymentId);
        $this->resolveSpaceId($user, (int) $payment->financial_space_id);

        if (in_array($payment->status, ['successful', 'refunded'], true)) {
            return $payment;
        }

        $collection = $payment->collection_transaction_id
            ? MobileMoneyTransaction::query()->where('user_id', $user->id)->find($payment->collection_transaction_id)
            : null;

        if (! $collection) {
            throw new RuntimeException('The payment collection evidence is missing and requires operations review.');
        }

        if (in_array($collection->status, [MobileMoneyTransaction::STATUS_PROCESSING, MobileMoneyTransaction::STATUS_PENDING], true)) {
            try {
                $collection = $this->mobileMoney->lookupStatus($collection);
            } catch (Throwable $exception) {
                report($exception);
                $payment->update(['status' => 'collection_pending']);

                return $payment->fresh();
            }
        }

        if (in_array($collection->status, [MobileMoneyTransaction::STATUS_FAILED, MobileMoneyTransaction::STATUS_REVERSED], true)) {
            $payment->update([
                'status' => $collection->status === MobileMoneyTransaction::STATUS_REVERSED ? 'refunded' : 'collection_failed',
                'evidence' => array_merge((array) $payment->evidence, [
                    'collection_status' => $collection->status,
                    'collection_provider_reference' => $collection->provider_reference,
                ]),
            ]);
            $this->event($payment->fresh(), 'collection_terminal', ['status' => $collection->status]);

            return $payment->fresh();
        }

        if ($collection->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            $payment->update(['status' => 'collection_pending']);

            return $payment->fresh();
        }

        $account = $this->account($user, (int) $payment->essentials_account_id, (int) $payment->financial_space_id);
        $biller = EssentialsBiller::query()->findOrFail($account->biller_id);
        if (! $this->cpay->configuredForBiller($biller)) {
            throw new RuntimeException('The bill-payment route is not configured.');
        }

        $evidence = (array) $payment->evidence;
        try {
            if ($payment->provider_reference) {
                $result = $this->cpay->status($payment->provider_reference, 'provider');
            } elseif (isset($evidence['fulfilment_started_at'])) {
                $result = $this->cpay->status($payment->reference, 'internal');
            } else {
                $payment->update([
                    'status' => 'fulfilment_pending',
                    'evidence' => array_merge($evidence, [
                        'fulfilment_started_at' => now()->toISOString(),
                        'collection_provider_reference' => $collection->provider_reference,
                    ]),
                ]);
                $this->event($payment->fresh(), 'fulfilment_started', [
                    'request_reference' => $payment->reference,
                ]);
                $result = $this->cpay->payOwnMoneyBill($payment->fresh(), $account, $biller, (string) ($collection->provider_reference ?: $collection->internal_reference));
            }
        } catch (Throwable $exception) {
            report($exception);
            $payment->update([
                'status' => 'fulfilment_pending',
                'evidence' => array_merge((array) $payment->fresh()->evidence, [
                    'fulfilment_state' => 'unknown',
                    'fulfilment_error_type' => $exception::class,
                    'reconcile_by' => 'request_reference',
                ]),
            ]);
            $this->event($payment->fresh(), 'fulfilment_unknown', ['error_type' => $exception::class]);

            return $payment->fresh();
        }

        $status = strtoupper((string) ($result['status'] ?? ''));
        $providerReference = (string) ($result['providerReference'] ?? $result['reference'] ?? $payment->provider_reference ?? '');
        $payment->update([
            'provider_reference' => $providerReference ?: $payment->provider_reference,
            'evidence' => array_merge((array) $payment->evidence, [
                'fulfilment_status' => $status ?: 'UNKNOWN',
                'fulfilment_provider_reference' => $providerReference ?: null,
            ]),
        ]);

        if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED'], true)) {
            $payment->update([
                'status' => 'successful',
                'settled_at' => now(),
            ]);
            $this->event($payment->fresh(), 'settled', [
                'provider_reference' => $providerReference ?: null,
            ]);
            $this->audit->record('essentials.own_money_payment.settled', $user, $payment->fresh());

            return $payment->fresh();
        }

        if (in_array($status, ['FAILED', 'REVERSED', 'CANCELLED'], true)) {
            return $this->refundFailedFulfilment($user, $payment->fresh(), $collection, $status);
        }

        $payment->update(['status' => 'fulfilment_pending']);

        return $payment->fresh();
    }

    private function refundFailedFulfilment(
        User $user,
        EssentialsOwnMoneyPayment $payment,
        MobileMoneyTransaction $collection,
        string $providerStatus,
    ): EssentialsOwnMoneyPayment {
        $payment->update([
            'status' => 'refund_pending',
            'evidence' => array_merge((array) $payment->evidence, [
                'refund_required' => true,
                'fulfilment_terminal_status' => $providerStatus,
            ]),
        ]);
        $this->event($payment->fresh(), 'refund_required', ['provider_status' => $providerStatus]);

        try {
            $reversal = $this->mobileMoney->reverse($collection, 'Essentials bill fulfilment failed after customer collection.');
            $refunded = $reversal->status === MobileMoneyTransaction::STATUS_REVERSED;
            $payment->update([
                'status' => $refunded ? 'refunded' : 'refund_pending',
                'evidence' => array_merge((array) $payment->evidence, [
                    'refund_transaction_status' => $reversal->status,
                    'refund_provider_reference' => $reversal->provider_reference,
                ]),
            ]);
            $this->event($payment->fresh(), $refunded ? 'refunded' : 'refund_pending', [
                'mobile_money_transaction_id' => $reversal->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $payment->update([
                'evidence' => array_merge((array) $payment->evidence, [
                    'refund_state' => 'unknown',
                    'refund_error_type' => $exception::class,
                ]),
            ]);
            $this->event($payment->fresh(), 'refund_unknown', ['error_type' => $exception::class]);
        }

        $this->audit->record('essentials.own_money_payment.fulfilment_failed', $user, $payment->fresh(), [
            'provider_status' => $providerStatus,
        ]);

        return $payment->fresh();
    }

    private function plannedBillOutflows(
        User $user,
        int $spaceId,
        string $currency,
        Carbon $from,
        Carbon $to,
        ?int $excludePlanId,
    ): int {
        return EssentialsBillPlan::query()
            ->where('user_id', $user->id)
            ->where('financial_space_id', $spaceId)
            ->where('currency', $currency)
            ->where('active', true)
            ->when($excludePlanId, fn ($query) => $query->whereKeyNot($excludePlanId))
            ->get()
            ->sum(fn (EssentialsBillPlan $plan) => $this->occurrenceTotal($plan, $from, $to));
    }

    private function occurrenceTotal(EssentialsBillPlan $plan, Carbon $from, Carbon $to): int
    {
        $date = Carbon::parse($plan->next_due_date)->startOfDay();
        if ($date->gt($to)) {
            return 0;
        }

        $total = 0;
        $guard = 0;
        while ($date->lte($to) && $guard < 400) {
            if ($date->gte($from)) {
                $total += (int) $plan->expected_amount_minor;
            }
            if ($plan->frequency === 'once') {
                break;
            }
            $date = $this->nextOccurrence($date, (string) $plan->frequency);
            $guard++;
        }

        return $total;
    }

    private function futureOccurrences(int $amountMinor, string $frequency, Carbon $currentDue, Carbon $to): int
    {
        $date = $this->nextOccurrence($currentDue->copy(), $frequency);
        $total = 0;
        $guard = 0;

        while ($date->lte($to) && $guard < 400) {
            $total += $amountMinor;
            $date = $this->nextOccurrence($date, $frequency);
            $guard++;
        }

        return $total;
    }

    private function nextOccurrence(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->copy()->addWeek(),
            'fortnightly' => $date->copy()->addWeeks(2),
            'monthly' => $date->copy()->addMonthNoOverflow(),
            'quarterly' => $date->copy()->addMonthsNoOverflow(3),
            'annually' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addYears(100),
        };
    }

    private function account(User $user, int $accountId, int $spaceId): EssentialsAccount
    {
        $account = EssentialsAccount::query()
            ->where('user_id', $user->id)
            ->where('financial_space_id', $spaceId)
            ->find($accountId);

        if (! $account) {
            throw new InvalidArgumentException('Choose an Essentials account that belongs to this Financial Space.');
        }

        return $account;
    }

    private function resolveSpaceId(User $user, ?int $spaceId): int
    {
        if ($spaceId) {
            $member = DB::table('financial_space_memberships')
                ->where('financial_space_id', $spaceId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();
            if (! $member) {
                throw new InvalidArgumentException('You do not have active access to that Financial Space.');
            }

            return $spaceId;
        }

        $personal = DB::table('financial_space_memberships as membership')
            ->join('financial_spaces as space', 'space.id', '=', 'membership.financial_space_id')
            ->where('membership.user_id', $user->id)
            ->where('membership.status', 'active')
            ->where('space.type', 'personal')
            ->where('space.status', 'active')
            ->value('space.id');

        if (! $personal) {
            throw new InvalidArgumentException('A Personal Financial Space is required before using OpFin Essentials.');
        }

        return (int) $personal;
    }

    private function event(EssentialsOwnMoneyPayment $payment, string $type, array $evidence): void
    {
        $payload = [
            'payment_reference' => $payment->reference,
            'event_type' => $type,
            'amount_minor' => (int) $payment->amount_minor,
            'currency' => (string) $payment->currency,
            'evidence' => $evidence,
        ];

        DB::table('essentials_payment_events')->insert([
            'own_money_payment_id' => $payment->id,
            'event_key' => (string) Str::uuid(),
            'event_type' => $type,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'created_at' => now(),
        ]);
    }
}
