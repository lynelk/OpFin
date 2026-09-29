<?php

namespace App\Services;

use App\Models\FinancialSpace;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommercialPricingService
{
    public function quote(FinancialSpace $space, string $eventType, int $amountMinor, ?int $partnerId = null, ?int $partnerProductId = null): array
    {
        if ($amountMinor < 0) throw new InvalidArgumentException('Commercial amount cannot be negative.');

        $now = now();
        $rules = DB::table('commercial_pricing_rules')
            ->where('status', 'active')
            ->where('event_type', $eventType)
            ->where('currency', strtoupper($space->currency ?: 'UGX'))
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $now))
            ->where(fn ($q) => $q->whereNull('financial_space_type')->orWhere('financial_space_type', $space->type))
            ->where(fn ($q) => $q->whereNull('partner_id')->orWhere('partner_id', $partnerId))
            ->where(fn ($q) => $q->whereNull('partner_product_id')->orWhere('partner_product_id', $partnerProductId))
            ->get()
            ->sortByDesc(fn ($r) => ($r->partner_product_id ? 8 : 0) + ($r->partner_id ? 4 : 0) + ($r->financial_space_type ? 2 : 0) + ($r->commercial_agreement_id ? 1 : 0));

        $rule = $rules->first();
        if (! $rule) {
            throw new InvalidArgumentException("No active commercial pricing rule exists for {$eventType}. Product/action activation is blocked until pricing is explicit, including an intentional zero-fee rule.");
        }

        $fee = match ($rule->charge_basis) {
            'flat' => (int) $rule->flat_amount_minor,
            'percentage' => intdiv(($amountMinor * (int) $rule->rate_bps) + 9999, 10000),
            'flat_plus_percentage' => (int) $rule->flat_amount_minor + intdiv(($amountMinor * (int) $rule->rate_bps) + 9999, 10000),
            default => throw new InvalidArgumentException('Unsupported commercial charge basis.'),
        };
        if ($rule->minimum_amount_minor !== null) $fee = max($fee, (int) $rule->minimum_amount_minor);
        if ($rule->maximum_amount_minor !== null) $fee = min($fee, (int) $rule->maximum_amount_minor);

        return [
            'pricing_rule_id' => (int) $rule->id,
            'pricing_rule_code' => $rule->code,
            'charge_basis' => $rule->charge_basis,
            'payer' => $rule->payer,
            'principal_amount_minor' => $amountMinor,
            'platform_fee_minor' => max(0, $fee),
            'currency' => $rule->currency,
        ];
    }
}
