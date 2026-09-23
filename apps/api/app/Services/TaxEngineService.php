<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaxEngineService
{
    public function __construct(private readonly FinancialPolicyService $policies) {}

    public function quoteRevenue(
        string $eventType,
        int $commercialAmountMinor,
        int $partnerAmountMinor,
        string $currency,
    ): array {
        if ($commercialAmountMinor < 0 || $partnerAmountMinor < 0 || $partnerAmountMinor > $commercialAmountMinor) {
            throw new InvalidArgumentException('Revenue/tax inputs contain invalid amounts.');
        }

        $policy = $this->policies->active(
            (string) config('opfin.accounting.tax_policy_type', 'tax'),
            $eventType,
        );
        $rules = $this->policies->rules($policy);
        $revenueRules = (array) ($rules['revenue_rules'] ?? []);
        $matched = null;
        foreach ($revenueRules as $rule) {
            $types = array_map('strval', (array) ($rule['event_types'] ?? []));
            if ($types === [] || in_array($eventType, $types, true)) {
                $matched = (array) $rule;
                break;
            }
        }
        if ($matched === null) {
            throw new InvalidArgumentException("No tax rule covers revenue event type {$eventType}.");
        }

        $rate = (float) ($matched['rate_percent'] ?? 0);
        if ($rate < 0) {
            throw new InvalidArgumentException('Tax rate cannot be negative.');
        }

        $treatment = strtolower((string) ($matched['price_tax_treatment'] ?? 'exclusive'));
        $basisName = strtolower((string) ($matched['taxable_basis'] ?? 'commercial_amount'));
        $taxableBase = $basisName === 'opfin_share'
            ? max(0, $commercialAmountMinor - $partnerAmountMinor)
            : $commercialAmountMinor;

        if ($treatment === 'inclusive') {
            $tax = $rate <= 0 ? 0 : (int) round($taxableBase - ($taxableBase / (1 + ($rate / 100))));
            $invoiceTotal = $commercialAmountMinor;
            $opfinAmount = max(0, $commercialAmountMinor - $partnerAmountMinor - $tax);
        } elseif ($treatment === 'exclusive') {
            $tax = (int) round($taxableBase * ($rate / 100));
            $invoiceTotal = $commercialAmountMinor + $tax;
            $opfinAmount = max(0, $commercialAmountMinor - $partnerAmountMinor);
        } else {
            throw new InvalidArgumentException('Tax rule price_tax_treatment must be inclusive or exclusive.');
        }

        return [
            'tax_type' => (string) ($matched['tax_type'] ?? 'none'),
            'rate_percent' => $rate,
            'taxable_amount_minor' => $taxableBase,
            'tax_amount_minor' => $tax,
            'invoice_total_minor' => $invoiceTotal,
            'opfin_amount_minor' => $opfinAmount,
            'partner_amount_minor' => $partnerAmountMinor,
            'currency' => strtoupper($currency),
            'invoice_required' => (bool) ($matched['invoice_required'] ?? false),
            'efris_document_type' => (string) ($matched['efris_document_type'] ?? 'invoice'),
            'policy' => [
                'id' => $policy->id,
                'code' => $policy->code,
                'version' => $policy->version,
                'rules' => $matched,
            ],
        ];
    }

    public function record(int $revenueEventId, array $quote, ?int $ledgerTransactionId = null): ?object
    {
        if ((int) $quote['tax_amount_minor'] <= 0 && ! $quote['invoice_required']) {
            return null;
        }

        $reference = 'TAX-REV-'.$revenueEventId;
        DB::table('tax_events')->updateOrInsert(
            ['event_reference' => $reference],
            [
                'revenue_event_id' => $revenueEventId,
                'ledger_transaction_id' => $ledgerTransactionId,
                'tax_type' => $quote['tax_type'],
                'taxable_amount_minor' => $quote['taxable_amount_minor'],
                'rate_percent' => $quote['rate_percent'],
                'tax_amount_minor' => $quote['tax_amount_minor'],
                'currency' => $quote['currency'],
                'status' => 'accrued',
                'rule_snapshot' => json_encode($quote['policy'], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return DB::table('tax_events')->where('event_reference', $reference)->first();
    }
}
