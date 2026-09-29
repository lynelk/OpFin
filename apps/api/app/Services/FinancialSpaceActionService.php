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
        if ($existing) return $existing;

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

    public function approveAndSubmit(FinancialSpace $space, object $intent, User $checker): object
    {
        $this->assertSupportedSpace($space);
        $this->assertOfficer($space, $checker);
        if ((int) $intent->initiated_by_user_id === (int) $checker->id) throw new InvalidArgumentException('Maker and checker must be different users.');
        if ($intent->status !== 'pending_approval') return $intent;

        $phone = trim((string) $intent->counterparty_phone);
        if ($phone === '') throw new InvalidArgumentException('A verified payment destination is required before money movement.');

        $tx = $intent->direction === 'collection'
            ? $this->money->collect([
                'user_id' => $intent->initiated_by_user_id, 'amount_minor' => (int) $intent->total_amount_minor,
                'currency' => $intent->currency, 'phone' => $phone, 'idempotency_key' => 'space-action:'.$intent->public_id,
                'internal_reference' => 'OPF-SPACE-'.$intent->public_id, 'purpose' => 'financial_space_action',
                'source_type' => 'financial_space_action', 'source_id' => $intent->id,
              ])
            : $this->money->disburse([
                'user_id' => $intent->initiated_by_user_id, 'amount_minor' => (int) $intent->principal_amount_minor,
                'currency' => $intent->currency, 'phone' => $phone, 'idempotency_key' => 'space-action:'.$intent->public_id,
                'internal_reference' => 'OPF-SPACE-'.$intent->public_id, 'purpose' => 'financial_space_action',
                'source_type' => 'financial_space_action', 'source_id' => $intent->id,
              ]);

        DB::table('financial_space_action_intents')->where('id', $intent->id)->update([
            'approved_by_user_id' => $checker->id, 'approved_at' => now(), 'submitted_at' => now(),
            'mobile_money_transaction_id' => $tx->id, 'status' => 'submitted', 'updated_at' => now(),
        ]);
        $this->audit->record('financial_space.action.submitted', $checker, $space, ['action_intent_id' => $intent->id, 'mobile_money_transaction_id' => $tx->id]);
        return DB::table('financial_space_action_intents')->find($intent->id);
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
