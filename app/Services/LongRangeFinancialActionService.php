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

    public function createForSource(User $user, string $sourceType, int $sourceId, int $amountMinor, string $idempotencyKey): object
    {
        if ($sourceType === 'participatory_commitment') {
            $source = DB::table('participatory_finance_commitments')->where('id', $sourceId)->where('investor_user_id', $user->id)->first();
            if (! $source || $source->status !== 'awaiting_step_up') {
                throw ValidationException::withMessages(['source_id' => ['Participatory commitment is not eligible for payment.']]);
            }
            if ((int) $source->amount_minor !== $amountMinor) {
                throw ValidationException::withMessages(['amount_minor' => ['Amount must match the approved commitment exactly.']]);
            }

            return $this->createIntent($user, 'participatory_fund', $sourceType, $sourceId, $amountMinor, $idempotencyKey);
        }

        if ($sourceType === 'asset_finance_deposit') {
            $source = DB::table('asset_finance_requests')->where('id', $sourceId)->where('user_id', $user->id)->first();
            if (! $source || $source->status !== 'approved') {
                throw ValidationException::withMessages(['source_id' => ['Asset-finance request is not approved for deposit collection.']]);
            }
            if ((int) $source->deposit_minor <= 0 || (int) $source->deposit_minor !== $amountMinor) {
                throw ValidationException::withMessages(['amount_minor' => ['Amount must match the approved asset-finance deposit.']]);
            }
            if ((int) $source->deposit_minor >= (int) $source->asset_price_minor) {
                throw ValidationException::withMessages(['amount_minor' => ['Asset-finance deposit must remain below the asset price.']]);
            }

            return $this->createIntent($user, 'asset_finance_deposit', $sourceType, $sourceId, $amountMinor, $idempotencyKey);
        }

        throw ValidationException::withMessages(['source_type' => ['Unsupported long-range financial source.']]);
    }

    public function createIntent(User $user, string $actionType, string $sourceType, int $sourceId, int $amountMinor, string $idempotencyKey): object
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount_minor' => ['Financial action amount must be positive.']]);
        }
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['idempotency_key' => ['A financial action idempotency key is required.']]);
        }

        $existing = DB::table('financial_action_intents')->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id || (int) $existing->amount_minor !== $amountMinor || $existing->source_type !== $sourceType || (int) $existing->source_id !== $sourceId || $existing->action_type !== $actionType) {
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

        DB::transaction(function () use ($intent, $transaction, $status) {
            $current = DB::table('financial_action_intents')->where('id', $intent->id)->lockForUpdate()->first();
            if (! $current || $current->status !== 'awaiting_step_up') {
                return;
            }

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
                $this->applySettlement($current);
                $transaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);
            } elseif ($status === 'failed') {
                $this->releaseFailedSource($current);
                if ($transaction->status === MobileMoneyTransaction::STATUS_FAILED) {
                    $transaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);
                }
            }
        });

        $this->auditLogger->record('long_range.financial_intent.confirmed', $user, null, ['reference' => $reference, 'provider_status' => $transaction->status]);

        return DB::table('financial_action_intents')->where('id', $intent->id)->first();
    }

    public function reconcile(): array
    {
        $processed = 0;
        $settled = 0;
        $failed = 0;
        $reversed = 0;

        DB::table('financial_action_intents')
            ->whereIn('status', ['provider_processing', 'settled'])
            ->orderBy('id')
            ->chunkById(100, function ($intents) use (&$processed, &$settled, &$failed, &$reversed) {
                foreach ($intents as $intent) {
                    $transaction = MobileMoneyTransaction::query()->where('internal_reference', $intent->reference)->latest('id')->first();
                    if (! $transaction) {
                        continue;
                    }
                    $processed++;

                    if ($transaction->status === MobileMoneyTransaction::STATUS_REVERSED) {
                        DB::transaction(function () use ($intent, $transaction, &$reversed, &$failed) {
                            $current = DB::table('financial_action_intents')->where('id', $intent->id)->lockForUpdate()->first();
                            if (! $current) {
                                return;
                            }
                            if ($current->status === 'settled') {
                                $this->applyReversal($current);
                                DB::table('financial_action_intents')->where('id', $intent->id)->update([
                                    'status' => 'reversed',
                                    'updated_at' => now(),
                                ]);
                                $transaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);
                                $this->auditLogger->record('long_range.financial_intent.reversed', null, $transaction, [
                                    'intent_reference' => $current->reference,
                                    'action_type' => $current->action_type,
                                    'source_type' => $current->source_type,
                                    'source_id' => (int) $current->source_id,
                                    'amount_minor' => (int) $current->amount_minor,
                                    'provider_reference' => $transaction->provider_reference,
                                ]);
                                $reversed++;

                                return;
                            }
                            if ($current->status === 'provider_processing') {
                                DB::table('financial_action_intents')->where('id', $intent->id)->update(['status' => 'failed', 'updated_at' => now()]);
                                $this->releaseFailedSource($current);
                                $transaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);
                                $this->auditLogger->record('long_range.financial_intent.provider_reversed_before_settlement', null, $transaction, [
                                    'intent_reference' => $current->reference,
                                    'action_type' => $current->action_type,
                                    'source_type' => $current->source_type,
                                    'source_id' => (int) $current->source_id,
                                    'amount_minor' => (int) $current->amount_minor,
                                    'provider_reference' => $transaction->provider_reference,
                                ]);
                                $failed++;
                            }
                        });

                        continue;
                    }

                    if ($transaction->status === MobileMoneyTransaction::STATUS_SUCCESSFUL) {
                        DB::transaction(function () use ($intent, $transaction, &$settled) {
                            $current = DB::table('financial_action_intents')->where('id', $intent->id)->lockForUpdate()->first();
                            if (! $current) {
                                return;
                            }
                            if ($current->status === 'settled') {
                                $transaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);

                                return;
                            }
                            if ($current->status !== 'provider_processing') {
                                return;
                            }
                            $this->applySettlement($current);
                            DB::table('financial_action_intents')->where('id', $intent->id)->update(['status' => 'settled', 'settled_at' => now(), 'updated_at' => now()]);
                            $transaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);
                            $settled++;
                        });
                    } elseif ($transaction->status === MobileMoneyTransaction::STATUS_FAILED) {
                        DB::transaction(function () use ($intent, $transaction, &$failed) {
                            $current = DB::table('financial_action_intents')->where('id', $intent->id)->lockForUpdate()->first();
                            if (! $current || $current->status !== 'provider_processing') {
                                return;
                            }
                            DB::table('financial_action_intents')->where('id', $intent->id)->update(['status' => 'failed', 'updated_at' => now()]);
                            $this->releaseFailedSource($current);
                            $transaction->update(['reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED]);
                            $failed++;
                        });
                    }
                }
            });

        return compact('processed', 'settled', 'failed', 'reversed');
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
            if ($commitment->status !== 'awaiting_step_up') {
                throw ValidationException::withMessages(['listing' => ['Commitment reservation is no longer active.']]);
            }
            $listing = DB::table('participatory_finance_listings')->where('id', $commitment->listing_id)->lockForUpdate()->first();
            if (! $listing || ! in_array($listing->status, ['approved', 'funding'], true) || ((int) $listing->funded_amount_minor + (int) $commitment->amount_minor) > (int) $listing->target_amount_minor) {
                throw ValidationException::withMessages(['listing' => ['Settlement would exceed or violate the approved funding state and requires operations review.']]);
            }
            DB::table('participatory_finance_commitments')->where('id', $commitment->id)->update(['status' => 'settled', 'payment_reference' => $intent->reference, 'settled_at' => now(), 'updated_at' => now()]);
            $newFunded = (int) $listing->funded_amount_minor + (int) $commitment->amount_minor;
            DB::table('participatory_finance_listings')->where('id', $commitment->listing_id)->update([
                'funded_amount_minor' => $newFunded,
                'status' => $newFunded === (int) $listing->target_amount_minor ? 'funded' : 'funding',
                'updated_at' => now(),
            ]);
        }

        if ($intent->source_type === 'asset_finance_deposit') {
            $asset = DB::table('asset_finance_requests')->where('id', $intent->source_id)->lockForUpdate()->first();
            if (! $asset || $asset->status !== 'approved') {
                throw ValidationException::withMessages(['asset' => ['Asset-finance request is no longer approved for deposit settlement.']]);
            }
            if ((int) $asset->deposit_minor !== (int) $intent->amount_minor || (int) $asset->deposit_minor <= 0 || (int) $asset->deposit_minor >= (int) $asset->asset_price_minor) {
                throw ValidationException::withMessages(['asset' => ['Asset-finance deposit no longer reconciles to the approved asset economics.']]);
            }
            DB::table('asset_finance_requests')->where('id', $intent->source_id)->update(['status' => 'deposit_settled', 'updated_at' => now()]);
        }
    }

    private function applyReversal(object $intent): void
    {
        if ($intent->source_type === 'participatory_commitment') {
            $commitment = DB::table('participatory_finance_commitments')->where('id', $intent->source_id)->lockForUpdate()->first();
            if (! $commitment || $commitment->status === 'reversed') {
                return;
            }
            if ($commitment->status !== 'settled') {
                throw ValidationException::withMessages(['listing' => ['Only a settled commitment can be reversed automatically.']]);
            }
            $listing = DB::table('participatory_finance_listings')->where('id', $commitment->listing_id)->lockForUpdate()->first();
            if (! $listing || (int) $listing->funded_amount_minor < (int) $commitment->amount_minor) {
                throw ValidationException::withMessages(['listing' => ['Participatory reversal would make funded amount negative and requires operations review.']]);
            }
            $newFunded = (int) $listing->funded_amount_minor - (int) $commitment->amount_minor;
            DB::table('participatory_finance_commitments')->where('id', $commitment->id)->update(['status' => 'reversed', 'updated_at' => now()]);
            DB::table('participatory_finance_listings')->where('id', $listing->id)->update([
                'funded_amount_minor' => $newFunded,
                'status' => 'funding',
                'updated_at' => now(),
            ]);
        }

        if ($intent->source_type === 'asset_finance_deposit') {
            $asset = DB::table('asset_finance_requests')->where('id', $intent->source_id)->lockForUpdate()->first();
            if (! $asset || $asset->status === 'approved') {
                return;
            }
            if ($asset->status !== 'deposit_settled') {
                throw ValidationException::withMessages(['asset' => ['Asset deposit reversal requires a settled deposit state.']]);
            }
            DB::table('asset_finance_requests')->where('id', $intent->source_id)->update(['status' => 'approved', 'updated_at' => now()]);
        }
    }

    private function releaseFailedSource(object $intent): void
    {
        if ($intent->source_type === 'participatory_commitment') {
            DB::table('participatory_finance_commitments')
                ->where('id', $intent->source_id)
                ->where('status', 'awaiting_step_up')
                ->update(['status' => 'failed', 'updated_at' => now()]);
        }
    }
}
