<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use App\Models\SavingsGoal;
use App\Models\SavingsMovement;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SavingsService
{
    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly SaveProtectionLedgerService $ledger,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function activeProducts(string $countryCode): mixed
    {
        return SavingsProduct::query()
            ->where('country_code', strtoupper($countryCode))
            ->where('status', SavingsProduct::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('effective_at')->orWhere('effective_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('name')
            ->get();
    }

    public function goalsFor(User $user): mixed
    {
        return SavingsGoal::query()
            ->with('product')
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (SavingsGoal $goal) => $this->presentGoal($goal));
    }

    public function presentGoal(SavingsGoal $goal): array
    {
        $goal->loadMissing('product');

        return [
            'id' => $goal->id,
            'goal_reference' => $goal->goal_reference,
            'name' => $goal->name,
            'status' => $goal->status,
            'target_amount_minor' => $goal->target_amount_minor,
            'target_date' => $goal->target_date?->toDateString(),
            'confirmed_balance_minor' => $goal->confirmedBalanceMinor(),
            'reserved_withdrawal_minor' => $goal->reservedWithdrawalMinor(),
            'available_balance_minor' => $goal->availableBalanceMinor(),
            'scheduled_amount_minor' => $goal->scheduled_amount_minor,
            'contribution_frequency' => $goal->contribution_frequency,
            'autopilot_enabled' => $goal->autopilot_enabled,
            'product' => $goal->product,
        ];
    }

    public function createGoal(User $user, SavingsProduct $product, array $attributes): SavingsGoal
    {
        $this->assertProductAvailable($product);

        $goal = SavingsGoal::create([
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'savings_product_id' => $product->id,
            'goal_reference' => 'OPF-SAV-'.Str::upper(Str::random(16)),
            'name' => trim((string) $attributes['name']),
            'target_amount_minor' => $attributes['target_amount_minor'] ?? null,
            'target_date' => $attributes['target_date'] ?? null,
            'status' => SavingsGoal::STATUS_ACTIVE,
            'scheduled_amount_minor' => $attributes['scheduled_amount_minor'] ?? null,
            'contribution_frequency' => $attributes['contribution_frequency'] ?? null,
            'autopilot_enabled' => false,
        ]);

        $this->auditLogger->record('savings.goal.created', $user, $goal, [
            'savings_product_id' => $product->id,
            'custody_model' => $product->custody_model,
        ]);

        return $goal->fresh('product');
    }

    public function updateSchedule(SavingsGoal $goal, User $user, array $attributes): SavingsGoal
    {
        $this->assertOwnedGoal($goal, $user);

        if (($attributes['autopilot_enabled'] ?? false) === true) {
            throw new InvalidArgumentException('Automatic debit is not enabled until a certified savings mandate contract is available.');
        }

        $goal->update([
            'scheduled_amount_minor' => $attributes['scheduled_amount_minor'] ?? null,
            'contribution_frequency' => $attributes['contribution_frequency'] ?? null,
            'autopilot_enabled' => false,
        ]);

        $this->auditLogger->record('savings.schedule.updated', $user, $goal, [
            'scheduled_amount_minor' => $goal->scheduled_amount_minor,
            'contribution_frequency' => $goal->contribution_frequency,
            'collection_mode' => 'reminder_manual_until_mandate_certified',
        ]);

        return $goal->fresh('product');
    }

    public function pause(SavingsGoal $goal, User $user): SavingsGoal
    {
        $this->assertOwnedGoal($goal, $user);
        if ($goal->status === SavingsGoal::STATUS_CLOSED) {
            throw new InvalidArgumentException('A closed savings goal cannot be paused.');
        }

        $goal->update([
            'status' => SavingsGoal::STATUS_PAUSED,
            'paused_at' => now(),
            'autopilot_enabled' => false,
        ]);
        $this->auditLogger->record('savings.goal.paused', $user, $goal);

        return $goal->fresh('product');
    }

    public function resume(SavingsGoal $goal, User $user): SavingsGoal
    {
        $this->assertOwnedGoal($goal, $user);
        if ($goal->status !== SavingsGoal::STATUS_PAUSED) {
            throw new InvalidArgumentException('Only a paused savings goal can be resumed.');
        }

        $goal->update([
            'status' => SavingsGoal::STATUS_ACTIVE,
            'paused_at' => null,
        ]);
        $this->auditLogger->record('savings.goal.resumed', $user, $goal);

        return $goal->fresh('product');
    }

    public function contribute(
        SavingsGoal $goal,
        User $user,
        int $amountMinor,
        string $clientIdempotencyKey,
    ): SavingsMovement {
        $this->assertOwnedGoal($goal, $user);
        $goal->loadMissing('product');
        $this->assertProductAvailable($goal->product);

        if ($goal->status !== SavingsGoal::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Savings contributions require an active goal.');
        }

        $this->assertContributionAmount($goal->product, $amountMinor);
        $idempotencyKey = "savings:goal:{$goal->id}:contribution:{$clientIdempotencyKey}";
        $existing = SavingsMovement::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->load(['goal.product', 'mobileMoneyTransaction']);
        }

        $movement = SavingsMovement::create([
            'savings_goal_id' => $goal->id,
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'movement_reference' => 'OPF-SVC-'.Str::upper(Str::random(16)),
            'movement_type' => SavingsMovement::TYPE_CONTRIBUTION,
            'status' => SavingsMovement::STATUS_COLLECTION_PENDING,
            'amount_minor' => $amountMinor,
            'currency' => $goal->product->currency,
            'idempotency_key' => $idempotencyKey,
            'requested_at' => now(),
            'metadata' => [
                'partner_name' => $goal->product->partner_name,
                'custody_model' => $goal->product->custody_model,
            ],
        ]);

        try {
            $mobileMoney = $this->mobileMoney->collect([
                'user_id' => $user->id,
                'institution_id' => $user->institution_id,
                'amount_minor' => $amountMinor,
                'currency' => $goal->product->currency,
                'phone' => $user->phone,
                'idempotency_key' => $idempotencyKey,
                'internal_reference' => $movement->movement_reference,
                'description' => 'OpFin savings contribution',
                'purpose' => 'savings_contribution',
                'savings_movement_id' => $movement->id,
            ]);
            $movement->update(['mobile_money_transaction_id' => $mobileMoney->id]);
            $this->syncMobileMoney($mobileMoney->fresh());
        } catch (\Throwable $exception) {
            $movement->update([
                'status' => SavingsMovement::STATUS_FAILED,
                'metadata' => array_merge($movement->metadata ?? [], ['failure' => $exception->getMessage()]),
            ]);
            throw $exception;
        }

        $this->auditLogger->record('savings.contribution.requested', $user, $movement, [
            'amount_minor' => $amountMinor,
            'mobile_money_transaction_id' => $movement->fresh()->mobile_money_transaction_id,
        ]);

        return $movement->fresh(['goal.product', 'mobileMoneyTransaction']);
    }

    public function requestWithdrawal(
        SavingsGoal $goal,
        User $user,
        int $amountMinor,
        string $clientIdempotencyKey,
    ): SavingsMovement {
        $this->assertOwnedGoal($goal, $user);
        $goal->loadMissing('product');

        if (! in_array($goal->status, [SavingsGoal::STATUS_ACTIVE, SavingsGoal::STATUS_COMPLETED], true)) {
            throw new InvalidArgumentException('Savings withdrawals are unavailable for this goal state.');
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be a positive integer minor-unit amount.');
        }
        if ($amountMinor < $goal->product->minimum_withdrawal_minor) {
            throw new InvalidArgumentException('Withdrawal amount is below the product minimum.');
        }
        if ($goal->product->lock_days > 0 && $goal->created_at->copy()->addDays($goal->product->lock_days)->isFuture()) {
            throw new InvalidArgumentException('This savings product is still within its disclosed lock period.');
        }
        if ($amountMinor > $goal->availableBalanceMinor()) {
            throw new InvalidArgumentException('Withdrawal amount exceeds the confirmed available savings position.');
        }

        $idempotencyKey = "savings:goal:{$goal->id}:withdrawal:{$clientIdempotencyKey}";
        $existing = SavingsMovement::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->load('goal.product');
        }

        $movement = SavingsMovement::create([
            'savings_goal_id' => $goal->id,
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'movement_reference' => 'OPF-SVW-'.Str::upper(Str::random(16)),
            'movement_type' => SavingsMovement::TYPE_WITHDRAWAL,
            'status' => SavingsMovement::STATUS_WITHDRAWAL_REQUESTED,
            'amount_minor' => $amountMinor,
            'currency' => $goal->product->currency,
            'idempotency_key' => $idempotencyKey,
            'requested_at' => now(),
            'metadata' => [
                'notice_days' => $goal->product->notice_days,
                'eligible_release_at' => now()->addDays($goal->product->notice_days)->toIso8601String(),
            ],
        ]);

        $this->auditLogger->record('savings.withdrawal.requested', $user, $movement, [
            'amount_minor' => $amountMinor,
            'available_balance_minor_before_reservation' => $goal->availableBalanceMinor() + $amountMinor,
        ]);

        return $movement->fresh('goal.product');
    }

    public function confirmContribution(
        SavingsMovement $movement,
        User $actor,
        string $partnerReference,
        string $evidenceHash,
    ): SavingsMovement {
        $movement->loadMissing(['goal.product', 'mobileMoneyTransaction']);
        if ($movement->movement_type !== SavingsMovement::TYPE_CONTRIBUTION || $movement->status !== SavingsMovement::STATUS_COLLECTED_PENDING_PARTNER) {
            throw new InvalidArgumentException('Only a collected savings contribution awaiting partner confirmation can be confirmed.');
        }

        DB::transaction(function () use ($movement, $actor, $partnerReference, $evidenceHash) {
            $locked = SavingsMovement::query()->lockForUpdate()->findOrFail($movement->id);
            if ($locked->status === SavingsMovement::STATUS_CONFIRMED) {
                return;
            }
            if ($locked->status !== SavingsMovement::STATUS_COLLECTED_PENDING_PARTNER) {
                throw new InvalidArgumentException('Savings contribution state changed before partner confirmation.');
            }

            $locked->update([
                'status' => SavingsMovement::STATUS_CONFIRMED,
                'partner_reference' => $partnerReference,
                'partner_evidence_hash' => strtolower($evidenceHash),
                'partner_confirmed_at' => now(),
                'completed_at' => now(),
            ]);
            $this->ledger->postSavingsPartnerSettlement($locked->fresh(['goal.product', 'mobileMoneyTransaction']), $actor);
            $goal = $locked->goal;
            if ($goal->target_amount_minor && $goal->confirmedBalanceMinor() >= $goal->target_amount_minor) {
                $goal->update([
                    'status' => SavingsGoal::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'autopilot_enabled' => false,
                ]);
            }
        });

        $movement = $movement->fresh(['goal.product', 'mobileMoneyTransaction']);
        $this->auditLogger->record('savings.contribution.partner_confirmed', $actor, $movement, [
            'partner_reference' => $partnerReference,
            'partner_evidence_hash' => strtolower($evidenceHash),
        ]);

        return $movement;
    }

    public function releaseWithdrawal(
        SavingsMovement $movement,
        User $actor,
        string $partnerReference,
        string $evidenceHash,
    ): SavingsMovement {
        $movement->loadMissing('goal.product');
        if ($movement->movement_type !== SavingsMovement::TYPE_WITHDRAWAL || $movement->status !== SavingsMovement::STATUS_WITHDRAWAL_REQUESTED) {
            throw new InvalidArgumentException('Only a requested savings withdrawal can be released by the partner.');
        }

        $eligibleAt = $movement->requested_at->copy()->addDays($movement->goal->product->notice_days);
        if ($eligibleAt->isFuture()) {
            throw new InvalidArgumentException('The disclosed savings withdrawal notice period has not elapsed.');
        }

        $movement->update([
            'status' => SavingsMovement::STATUS_PARTNER_RELEASED,
            'partner_reference' => $partnerReference,
            'partner_evidence_hash' => strtolower($evidenceHash),
            'partner_confirmed_at' => now(),
        ]);
        $this->ledger->postSavingsWithdrawalRelease($movement->fresh('goal.product'), $actor);

        return $this->startWithdrawalPayout($movement->fresh('goal.product'), $actor);
    }

    public function retryWithdrawalPayout(SavingsMovement $movement, User $actor): SavingsMovement
    {
        if ($movement->movement_type !== SavingsMovement::TYPE_WITHDRAWAL || $movement->status !== SavingsMovement::STATUS_PARTNER_RELEASED) {
            throw new InvalidArgumentException('Only a partner-released withdrawal without a successful payout can be retried.');
        }

        return $this->startWithdrawalPayout($movement, $actor, true);
    }

    public function syncMobileMoney(MobileMoneyTransaction $mobileMoney): ?SavingsMovement
    {
        $movementId = (int) ($mobileMoney->metadata['savings_movement_id'] ?? 0);
        if ($movementId <= 0) {
            return null;
        }

        $movement = SavingsMovement::query()->with('goal.product')->find($movementId);
        if (! $movement) {
            return null;
        }

        if ($mobileMoney->status === MobileMoneyTransaction::STATUS_FAILED) {
            if ($movement->movement_type === SavingsMovement::TYPE_WITHDRAWAL && $movement->partner_confirmed_at) {
                $movement->update([
                    'status' => SavingsMovement::STATUS_PARTNER_RELEASED,
                    'metadata' => array_merge($movement->metadata ?? [], [
                        'last_payout_failure' => $mobileMoney->failure_reason,
                        'failed_mobile_money_transaction_id' => $mobileMoney->id,
                    ]),
                ]);
            } else {
                $movement->update(['status' => SavingsMovement::STATUS_FAILED]);
            }

            return $movement->fresh(['goal.product', 'mobileMoneyTransaction']);
        }

        if ($mobileMoney->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            return $movement->fresh(['goal.product', 'mobileMoneyTransaction']);
        }

        if ($movement->movement_type === SavingsMovement::TYPE_CONTRIBUTION) {
            if ($movement->status !== SavingsMovement::STATUS_CONFIRMED) {
                $movement->update([
                    'status' => SavingsMovement::STATUS_COLLECTED_PENDING_PARTNER,
                    'provider_completed_at' => $movement->provider_completed_at ?: now(),
                ]);
                $this->ledger->postSavingsCollection($movement->fresh(['goal.product', 'mobileMoneyTransaction']), $mobileMoney);
            }
        } elseif ($movement->status !== SavingsMovement::STATUS_PAID) {
            $movement->update([
                'status' => SavingsMovement::STATUS_PAID,
                'provider_completed_at' => now(),
                'completed_at' => now(),
            ]);
            $this->ledger->postSavingsPayout($movement->fresh(['goal.product', 'mobileMoneyTransaction']), $mobileMoney);
        }

        $this->auditLogger->record('savings.money_movement.synchronized', null, $movement, [
            'mobile_money_transaction_id' => $mobileMoney->id,
            'mobile_money_status' => $mobileMoney->status,
        ]);

        return $movement->fresh(['goal.product', 'mobileMoneyTransaction']);
    }

    private function startWithdrawalPayout(SavingsMovement $movement, User $actor, bool $retry = false): SavingsMovement
    {
        $user = User::findOrFail($movement->user_id);
        $attempt = (int) (($movement->metadata['payout_attempts'] ?? 0) + 1);
        $idempotencyKey = $movement->idempotency_key.':payout:'.$attempt;

        $mobileMoney = $this->mobileMoney->disburse([
            'user_id' => $movement->user_id,
            'institution_id' => $movement->institution_id,
            'amount_minor' => $movement->amount_minor,
            'currency' => $movement->currency,
            'phone' => $user->phone,
            'idempotency_key' => $idempotencyKey,
            'internal_reference' => $movement->movement_reference.'-P'.$attempt,
            'description' => 'OpFin savings withdrawal',
            'purpose' => 'savings_withdrawal',
            'savings_movement_id' => $movement->id,
        ]);

        $movement->update([
            'mobile_money_transaction_id' => $mobileMoney->id,
            'status' => SavingsMovement::STATUS_PAYOUT_PENDING,
            'metadata' => array_merge($movement->metadata ?? [], [
                'payout_attempts' => $attempt,
                'last_retry_by' => $retry ? $actor->id : null,
            ]),
        ]);
        $this->syncMobileMoney($mobileMoney->fresh());
        $this->auditLogger->record($retry ? 'savings.withdrawal.payout_retried' : 'savings.withdrawal.payout_started', $actor, $movement, [
            'mobile_money_transaction_id' => $mobileMoney->id,
            'attempt' => $attempt,
        ]);

        return $movement->fresh(['goal.product', 'mobileMoneyTransaction']);
    }

    private function assertOwnedGoal(SavingsGoal $goal, User $user): void
    {
        if ($goal->user_id !== $user->id) {
            throw new InvalidArgumentException('This savings goal does not belong to the authenticated customer.');
        }
    }

    private function assertProductAvailable(SavingsProduct $product): void
    {
        if ($product->status !== SavingsProduct::STATUS_ACTIVE) {
            throw new InvalidArgumentException('This savings product is not currently available.');
        }
        if ($product->custody_model !== 'partner_held') {
            throw new InvalidArgumentException('Savings products must use an explicitly approved partner-held custody model.');
        }
        if ($product->effective_at && $product->effective_at->isFuture()) {
            throw new InvalidArgumentException('This savings product is not effective yet.');
        }
        if ($product->expires_at && $product->expires_at->isPast()) {
            throw new InvalidArgumentException('This savings product has expired.');
        }
    }

    private function assertContributionAmount(SavingsProduct $product, int $amountMinor): void
    {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Contribution amount must be a positive integer minor-unit amount.');
        }
        if ($amountMinor < $product->minimum_contribution_minor) {
            throw new InvalidArgumentException('Contribution amount is below the product minimum.');
        }
        if ($product->maximum_contribution_minor && $amountMinor > $product->maximum_contribution_minor) {
            throw new InvalidArgumentException('Contribution amount exceeds the product maximum.');
        }
    }
}
