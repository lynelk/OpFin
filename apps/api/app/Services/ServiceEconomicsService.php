<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceEconomicsService
{
    public function record(array $data): object
    {
        $required = ['service_code', 'provider', 'route', 'request_reference', 'status'];
        foreach ($required as $key) {
            if (! isset($data[$key]) || trim((string) $data[$key]) === '') {
                throw new \InvalidArgumentException("{$key} is required for a service economics event.");
            }
        }

        $existing = DB::table('service_economics_events')
            ->where('service_code', $data['service_code'])
            ->where('request_reference', $data['request_reference'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $providerGross = $this->integerOrNull($data['provider_gross_cost_minor'] ?? null);
        $providerDiscount = $this->integerOrNull($data['provider_discount_minor'] ?? null);
        $providerNet = $this->integerOrNull($data['provider_net_cost_minor'] ?? null);
        if ($providerNet === null && $providerGross !== null && $providerDiscount !== null) {
            $providerNet = $providerGross - $providerDiscount;
        }

        $customerServiceCharge = $this->integerOrNull($data['customer_service_charge_minor'] ?? null);
        $customerPlatformFee = $this->integerOrNull($data['customer_platform_fee_minor'] ?? null);
        $partnerCommission = $this->integerOrNull($data['partner_commission_minor'] ?? null);
        $citoFee = $this->integerOrNull($data['cito_platform_fee_minor'] ?? null);
        $opfinFee = $this->integerOrNull($data['opfin_platform_fee_minor'] ?? null);
        $tax = $this->integerOrNull($data['tax_amount_minor'] ?? null);

        $grossRevenue = $this->integerOrNull($data['gross_revenue_minor'] ?? null);
        if ($grossRevenue === null && $this->allKnown([$customerServiceCharge, $customerPlatformFee, $partnerCommission, $citoFee, $opfinFee])) {
            $grossRevenue = $customerServiceCharge + $customerPlatformFee + $partnerCommission + $citoFee + $opfinFee;
        }

        $netRevenue = $this->integerOrNull($data['net_revenue_minor'] ?? null);
        if ($netRevenue === null && $grossRevenue !== null && $tax !== null) {
            $netRevenue = $grossRevenue - $tax;
        }

        $grossMargin = $this->integerOrNull($data['gross_margin_minor'] ?? null);
        if ($grossMargin === null && $netRevenue !== null && $providerNet !== null) {
            $grossMargin = $netRevenue - $providerNet;
        }

        $id = DB::table('service_economics_events')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'user_id' => $data['user_id'] ?? null,
            'financial_space_id' => $data['financial_space_id'] ?? null,
            'partner_id' => $data['partner_id'] ?? null,
            'partner_product_id' => $data['partner_product_id'] ?? null,
            'commercial_agreement_id' => $data['commercial_agreement_id'] ?? null,
            'service_code' => trim((string) $data['service_code']),
            'capability_code' => isset($data['capability_code']) ? trim((string) $data['capability_code']) : null,
            'provider' => trim((string) $data['provider']),
            'route' => strtoupper(trim((string) $data['route'])),
            'environment' => strtoupper(trim((string) ($data['environment'] ?? 'PRODUCTION'))),
            'request_reference' => trim((string) $data['request_reference']),
            'provider_reference' => $data['provider_reference'] ?? null,
            'status' => strtoupper(trim((string) $data['status'])),
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'UGX'))),
            'provider_gross_cost_minor' => $providerGross,
            'provider_discount_minor' => $providerDiscount,
            'provider_net_cost_minor' => $providerNet,
            'customer_service_charge_minor' => $customerServiceCharge,
            'customer_platform_fee_minor' => $customerPlatformFee,
            'partner_commission_minor' => $partnerCommission,
            'cito_platform_fee_minor' => $citoFee,
            'opfin_platform_fee_minor' => $opfinFee,
            'tax_amount_minor' => $tax,
            'net_settlement_to_provider_minor' => $this->integerOrNull($data['net_settlement_to_provider_minor'] ?? null),
            'gross_revenue_minor' => $grossRevenue,
            'net_revenue_minor' => $netRevenue,
            'gross_margin_minor' => $grossMargin,
            'price_book_version' => $data['price_book_version'] ?? null,
            'contract_version' => $data['contract_version'] ?? null,
            'reconciliation_reference' => $data['reconciliation_reference'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'reconciled_at' => $data['reconciled_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('service_economics_events')->where('id', $id)->first();
    }

    private function integerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('Service economics amounts must use integer minor units.');
        }

        return (int) $value;
    }

    private function allKnown(array $values): bool
    {
        foreach ($values as $value) {
            if ($value === null) {
                return false;
            }
        }

        return true;
    }
}
