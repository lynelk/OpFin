<?php

namespace App\Services;

use App\Models\FinancialSpace;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use App\Services\CommunityFinance\CommunityFinanceReadinessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FinancialSpaceActionService
{
    public function __construct(
        private readonly CommercialPricingService $pricing,
        private readonly MobileMoneyService $money,
        private readonly AuditLogger $audit,
        private readonly CommunityFinanceReadinessService $communityFinance,
    ) {}

    public function create(FinancialSpace $space, User $actor, array $data): object
    {
        $this->assertSupportedSpace($space);
        $this->assertMember($space, $actor);

        $existing = DB::table('financial_space_action_intents')->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            $this->assertSameAction($existing, $space, $actor, $data);
            return $existing;
        }

        $quote = $this->pricing->quote(
            $space, $data['action_type'], (int) $data['amount_minor'],
            $data['partner_id'] ?? null, $data['partner_product_id'] ?? null
        );
        $payer = $quote['payer'];
        $total = (int) $data['amount_minor'] + ($payer === 'customer' ? (int) $quote['platform_fee_minor'] : 0);

        $id = DB::table('financial_space_action_intents')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'financial_space_id' => $space->id,
            'initiated_by_user_id' => $actor->id,
            'pricing_rule_id' => $quote['pricing_rule_id'],
            'action_type' => $data['action_type'],
            'direction' => $data['direction'],
            'principal_amount_minor' => (int) $data['amount_minor'],
            'platform_fee_minor' => (int) $quote['platform_fee_minor'],
            'total_amount_minor' => $total,
            'currency' => strtoupper($space->currency ?: 'UGX'),
            'counterparty_phone' => $data['counterparty_phone'] ?? null,
            'status' => 'pending_approval',
            'idempotency_key' => $data['idempotency_key'],
            'source_type' => $data['source_type'] ?? null,
            'source_reference' => $data['source_reference'] ?? null,
            'metadata' => json_encode(['pricing' => $quote, 'partner_id' => $data['partner_id'] ?? null, 'partner_product_id' => $data['partner_product_id'] ?? null], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $intent = DB::table('financial_space_action_intents')->find($id);
        $this->audit->record('financial_space.action.created', $actor, $space, ['action_intent_id' => $id, 'action_type' => $data['action_type']]);
        return $intent;
    }

    /**
     * Claim the approved instruction before invoking an external financial API.
     * Another approval or timeout never constitutes authority to resubmit.
     */
    public function approveAndSubmit(FinancialSpace $space, object $intent, User $checker): object
    {
        $this->assertSupportedSpace($space);
        $this->assertOfficer($space, $checker);
        if ((int) $intent->initiated_by_user_id === (int) $checker->id) {
            throw new InvalidArgumentException('Maker and checker must be different users.');
        }

        // Live club, investor and employer payouts require explicit evidence of
        // safeguarding/custody, mandate and settlement routing. Never infer this
        // from membership approval or an external gateway's HTTP 202.
        if ($intent->direction === 'disbursement') {
            app(FinancialSpacePayoutMandateService::class)->assertAccepted(
                $space, (string) $intent->currency
            );
        }

        $claimed = DB::transaction(function () use ($space, $intent, $checker) {
            $locked = DB::table('financial_space_action_intents')->where('financial_space_id', $space->id)
                ->where('id', $intent->id)->lockForUpdate()->first();
            if (! $locked) {
                throw new InvalidArgumentException('The financial action is not available in this Space.');
            }
            if ($locked->status !== 'pending_approval') {
                return false;
            }
            if ((int) $locked->initiated_by_user_id === (int) $checker->id) {
                throw new InvalidArgumentException('Maker and checker must be different users.');
            }
            $phone = trim((string) $locked->counterparty_phone);
            if ($phone === '') {
                throw new InvalidArgumentException('A verified payment destination is required before money movement.');
            }
            // Persist the claim before network I/O. On unknown outcome the claim
            // remains, and recovery must query the original canonical reference.
            DB::table('financial_space_action_intents')->where('id', $locked->id)->update([
                'approved_by_user_id' => $checker->id,
                'approved_at' => now(),
                'status' => 'submission_started',
                'updated_at' => now(),
            ]);
            return true;
        });
        if (! $claimed) {
            return DB::table('financial_space_action_intents')->find($intent->id);
        }

        $lockedIntent = DB::table('financial_space_action_intents')->find($intent->id);
        $fields = [
            'user_id' => $lockedIntent->initiated_by_user_id,
            'amount_minor' => $lockedIntent->direction === 'collection'
                ? (int) $lockedIntent->total_amount_minor
                : (int) $lockedIntent->principal_amount_minor,
            'currency' => $lockedIntent->currency,
            'phone' => trim((string) $lockedIntent->counterparty_phone),
            'idempotency_key' => 'space-action:'.$lockedIntent->public_id,
            'internal_reference' => 'OPF-SPACE-'.$lockedIntent->public_id,
            'purpose' => 'financial_space_action',
            'source_type' => 'financial_space_action',
            'source_id' => $lockedIntent->id,
        ];

        try {
            $tx = $lockedIntent->direction === 'collection'
                ? $this->money->collect($fields)
                : $this->money->disburse($fields);
            DB::table('financial_space_action_intents')->where('id', $lockedIntent->id)->update([
                'submitted_at' => now(),
                'mobile_money_transaction_id' => $tx->id,
                'status' => 'submitted',
                'updated_at' => now(),
            ]);
            $this->audit->record('financial_space.action.submitted', $checker, $space, [
                'action_intent_id' => $lockedIntent->id, 'mobile_money_transaction_id' => $tx->id,
                'provider_submission_state' => $tx->provider_submission_state,
            ]);
        } catch (\Throwable $exception) {
            // Ambiguous, not terminal. Never loop the payout/collection call.
            DB::table('financial_space_action_intents')->where('id', $lockedIntent->id)->update([
                'status' => 'submission_unknown',
                'updated_at' => now(),
            ]);
            $this->audit->record('financial_space.action.submission_unknown', $checker, $space, [
                'action_intent_id' => $lockedIntent->id,
                'exception_class' => $exception::class,
            ]);
        }

        return DB::table('financial_space_action_intents')->find($lockedIntent->id);
    }

    private function assertSameAction(object $existing, FinancialSpace $space, User $actor, array $data): void
    {
        $same = (int) $existing->financial_space_id === (int) $space->id
            && (int) $existing->initiated_by_user_id === (int) $actor->id
            && (string) $existing->action_type === (string) $data['action_type']
            && (string) $existing->direction === (string) $data['direction']
            && (int) $existing->principal_amount_minor === (int) $data['amount_minor']
            && (string) $existing->counterparty_phone === (string) ($data['counterparty_phone'] ?? '')
            && (string) ($existing->source_type ?? '') === (string) ($data['source_type'] ?? '')
            && (string) ($existing->source_reference ?? '') === (string) ($data['source_reference'] ?? '');
        $metadata = json_decode((string) ($existing->metadata ?? '{}'), true) ?: [];
        $same = $same
            && (int) ($metadata['partner_id'] ?? 0) === (int) ($data['partner_id'] ?? 0)
            && (int) ($metadata['partner_product_id'] ?? 0) === (int) ($data['partner_product_id'] ?? 0);
        if (! $same) {
            throw new InvalidArgumentException('The idempotency key belongs to a different financial action.');
        }
    }

    private function assertSupportedSpace(FinancialSpace $space): void {
        if (! in_array($space->type, ['investment_club','sacco','savings_group'], true)) throw new InvalidArgumentException('This action layer is limited to governed community Financial Spaces.');
        if ($space->type === 'sacco') $this->communityFinance->assertCanActivate();
    }
    private function assertMember(FinancialSpace $space, User $user): void {
        if (! DB::table('financial_space_memberships')->where('financial_space_id',$space->id)->where('user_id',$user->id)->where('status','active')->whereNull('deleted_at')->exists()) throw new InvalidArgumentException('Active Space membership is required.');
    }
    private function assertOfficer(FinancialSpace $space, User $user): void {
        if (! DB::table('financial_space_memberships')->where('financial_space_id',$space->id)->where('user_id',$user->id)->where('status','active')->whereNull('deleted_at')->whereIn('role',['owner','administrator','chairperson','treasurer','director'])->exists()) throw new InvalidArgumentException('An authorised Space officer must approve this action.');
    }
}
