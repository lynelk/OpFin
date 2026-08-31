<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use App\Models\Otp;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LongRangeFinancialActionService
{
    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createIntent(User $user, string $actionType, string $sourceType, int $sourceId, int $amountMinor, string $idempotencyKey): object
    {
        $existing = DB::table('financial_action_intents')->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->user_id !== $user->id || $existing->amount_minor !== $amountMinor || $existing->source_type !== $sourceType || $existing->source_id !== $sourceId) {
                throw ValidationException::withMessages(['idempotency_key' => ['Idempotency key was already used for a different financial instruction.']]);
            }
            return $existing;
        }

        $reference = (string) Str::uuid();
        $id = DB::table('financial_action_intents')->insertGetId([
            'reference' => $reference,
            'user_id' => $user->id,
            'action_type' => $actionType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount_minor' => $amountMinor,
            'currency' => 'UGX',
            'status' => 'awaiting_step_up',
            'idempotency_key' => $idempotencyKey,
            'evidence' => json_encode(['created_from_authenticated_session' => true, 'step_up_required' => true, 'money_platform' => 'cpay']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.financial_intent.created', $user, null, ['reference' => $reference, 'action_type' => $actionType, 'source_type' => $sourceType]);

        return DB::table('financial_action_intents')->find($id);
    }

    public function confirm(User $user, string $reference, string $verificationToken): object
    {
        $intent = DB::table('financial_action_intents')->where('reference', $reference)->where('user_id', $user->id)->first();
        if (! $intent) {
            throw ValidationException::withMessages(['reference' => ['Financial instruction was not found.']]);
        }
        if ($intent->status !== 'awaiting_step_up') {
            return $intent;
        }
        if (! $this->validStepUp($user, $verificationToken)) {
            throw ValidationException::withMessages(['verification_token' => ['A fresh verified OTP step-up is required.']]);
        }

        $transaction = $this->mobileMoney->collect([
            'user_id' => $user->id,
            'amount_minor' => (int) $intent->amount_minor,
            'currency' => $intent->currency,
            'phone' => $user->phone,
            'idempotency_key' => 'long-range-'.$intent->idempotency_key,
            'internal_reference' => $intent->reference,
            'purpose' => $intent->action_type,
            'source_type' => $intent->source_type,
            'source_id' => $intent->source_id,
        ], 'cpay');

        $status = match ($transaction->status) {
            MobileMoneyTransaction::STATUS_SUCCESSFUL => 'settled',
            MobileMoneyTransaction::STATUS_FAILED, MobileMoneyTransaction::STATUS_REVERSED => 'failed',
            default => 'provider_processing',
        };

        DB::table('financial_action_intents')->where('id', $intent->id)->update([
            'status' => $status,
            'cpay_reference' => $transaction->internal_reference,
            'confirmed_at' => now(),
            'settled_at' => $status === 'settled' ? now() : null,
            'evidence' => json_encode([
                'step_up_verified_at' => now()->toIso8601String(),
                'cpay_transaction_id' => $transaction->id,
                'provider_status' => $transaction->status,
                'provider_reference' => $transaction->provider_reference,
            ]),
            'updated_at' => now(),
        ]);

        if ($status === 'settled') {
            $this->applySettlement($intent);
        }

        $this->auditLogger->record('long_range.financial_intent.confirmed', $user, null, ['reference' => $reference, 'provider_status' => $transaction->status]);

        return DB::table('financial_action_intents')->where('id', $intent->id)->first();
    }

    public function reconcile(): array
    {
        $processed = 0;
        $settled = 0;
        $failed = 0;

        DB::table('financial_action_intents')->where('status', 'provider_processing')->orderBy('id')->chunkById(100, function ($intents) use (&$processed, &$settled, &$failed) {
            foreach ($intents as $intent) {
                $transaction = MobileMoneyTransaction::query()->where('internal_reference', $intent->reference)->latest('id')->first();
                if (! $transaction) {
                    continue;
                }
                $processed++;
                if ($transaction->status === MobileMoneyTransaction::STATUS_SUCCESSFUL) {
                    DB::transaction(function () use ($intent) {
                        $current = DB::table('financial_action_intents')->where('id', $intent->id)->lockForUpdate()->first();
                        if (! $current || $current->status === 'settled') {
                            return;
                        }
                        DB::table('financial_action_intents')->where('id', $intent->id)->update(['status' => 'settled', 'settled_at' => now(), 'updated_at' => now()]);
                        $this->applySettlement($current);
                    });
                    $settled++;
                } elseif (in_array($transaction->status, [MobileMoneyTransaction::STATUS_FAILED, MobileMoneyTransaction::STATUS_REVERSED], true)) {
                    DB::table('financial_action_intents')->where('id', $intent->id)->update(['status' => 'failed', 'updated_at' => now()]);
                    $failed++;
                }
            }
        });

        return compact('processed', 'settled', 'failed');
    }

    private function validStepUp(User $user, string $verificationToken): bool
    {
        $otp = Otp::query()->where('phone', $user->phone)->first();
        if (! $otp || ! $otp->verified_at || ! $otp->verification_token_hash || $otp->verified_at->lt(now()->subMinutes(10))) {
            return false;
        }

        return hash_equals((string) $otp->verification_token_hash, hash('sha256', $verificationToken));
    }

    private function applySettlement(object $intent): void
    {
        if ($intent->source_type === 'participatory_commitment') {
            $commitment = DB::table('participatory_finance_commitments')->where('id', $intent->source_id)->lockForUpdate()->first();
            if (! $commitment || $commitment->status === 'settled') {
                return;
            }
            DB::table('participatory_finance_commitments')->where('id', $commitment->id)->update(['status' => 'settled', 'payment_reference' => $intent->reference, 'settled_at' => now(), 'updated_at' => now()]);
            DB::table('participatory_finance_listings')->where('id', $commitment->listing_id)->increment('funded_amount_minor', $commitment->amount_minor, ['updated_at' => now()]);
        }

        if ($intent->source_type === 'asset_finance_deposit') {
            DB::table('asset_finance_requests')->where('id', $intent->source_id)->where('status', 'approved')->update(['status' => 'deposit_settled', 'updated_at' => now()]);
        }
    }
}
