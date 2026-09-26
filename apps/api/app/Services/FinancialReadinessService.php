<?php

namespace App\Services;

class FinancialReadinessService
{
    public function __construct(
        private readonly ProductionIntegrationReadinessService $integrations,
        private readonly FinancialIntegrityService $integrity,
        private readonly RegulatoryActivationGuard $regulatory,
    ) {}

    public function report(): array
    {
        $integrationReport = $this->integrations->report();
        $integrations = $integrationReport['integrations'];

        $citoReady = (bool) config('services.cito.financial_data_certified', false)
            && $this->allConfigured([
                'base_url' => config('services.cito.base_url'),
                'merchant_number' => config('services.cito.merchant_number'),
                'private_key' => config('services.cito.private_key'),
            ]);
        $directIdentityReady = $this->allConfigured([
            'url' => config('services.identity_verification.url'),
            'token' => config('services.identity_verification.token'),
        ]);
        $directCrbReady = $this->allConfigured([
            'base_url' => config('services.crb.base_url'),
            'client_id' => config('services.crb.account'),
            'client_secret' => config('services.crb.password'),
        ]);
        $creditReportingReady = $this->allConfigured([
            'url' => config('services.credit_reference_reporting.url'),
            'token' => config('services.credit_reference_reporting.token'),
        ]);

        $integrity = $this->integrity->summary();
        $regulatory = $this->regulatory->creditDisclosureStatus();
        $tax = $this->taxReadiness();

        $checks = [
            'money_movement' => [
                'status' => ($integrations['cpay']['status'] ?? 'blocked') === 'ready' ? 'ready' : 'blocked',
                'detail' => $integrations['cpay'] ?? null,
            ],
            'security_notifications' => [
                'status' => ($integrations['sms']['status'] ?? 'blocked') === 'ready' ? 'ready' : 'blocked',
                'detail' => $integrations['sms'] ?? null,
            ],
            'identity_and_kyc_route' => [
                'status' => $directIdentityReady ? 'ready' : 'blocked',
                'routes' => [
                    'cito_nin_phone_route' => $citoReady,
                    'cito_financial_data_certified' => (bool) config('services.cito.financial_data_certified', false),
                    'biometric_document_evidence_provider' => $directIdentityReady,
                ],
                'reason' => $directIdentityReady
                    ? null
                    : 'Current OpFin KYC requires ID-image/selfie evidence that the certified Cito capability contract does not yet replace.',
            ],
            'credit_reference_route' => [
                'status' => $citoReady || $directCrbReady ? 'ready' : 'blocked',
                'routes' => [
                    'cito' => $citoReady,
                    'cito_financial_data_certified' => (bool) config('services.cito.financial_data_certified', false),
                    'direct_crb' => $directCrbReady,
                ],
            ],
            'outbound_credit_reporting' => [
                'status' => $creditReportingReady ? 'ready' : 'blocked',
            ],
            'funding_provenance' => [
                'status' => (bool) config('opfin.credit.require_funding_pool_assignment', false) ? 'ready' : 'blocked',
                'require_funding_pool_assignment' => (bool) config('opfin.credit.require_funding_pool_assignment', false),
            ],
            'regulated_credit_disclosures' => $regulatory,
            'tax_and_efris_determination' => $tax,
            'financial_integrity' => [
                'status' => ($integrity['platform_balanced'] ?? false)
                    && (int) ($integrity['open_critical_alerts'] ?? 0) === 0
                    && (int) ($integrity['open_high_alerts'] ?? 0) === 0
                        ? 'ready'
                        : 'blocked',
                'latest_run' => $integrity['latest_run'] ?? null,
                'open_critical_alerts' => (int) ($integrity['open_critical_alerts'] ?? 0),
                'open_high_alerts' => (int) ($integrity['open_high_alerts'] ?? 0),
            ],
        ];

        $ready = collect($checks)->every(fn (array $check) => ($check['status'] ?? null) === 'ready');

        return [
            'financial_operations_ready' => $ready,
            'status' => $ready ? 'ready' : 'blocked',
            'checks' => $checks,
            'rules' => [
                'service_liveness_is_not_financial_readiness',
                'provider_acceptance_is_not_accounting_or_statement_finality',
                'financial_uat_requires_isolated_environment_and_non_production_provider_routes',
                'no_missing_regulatory_or_tax_fact_is_inferred',
            ],
        ];
    }

    private function taxReadiness(): array
    {
        $required = config('opfin.accounting.efris_required');
        if ($required === null) {
            return [
                'status' => 'blocked',
                'reason' => 'OPFIN_EFRIS_REQUIRED must be explicitly set true or false after a documented tax determination.',
            ];
        }

        if ((bool) $required) {
            $ready = (bool) config('opfin.accounting.efris_enabled', false)
                && $this->allConfigured([
                    'url' => config('services.efris.url'),
                    'token' => config('services.efris.token'),
                    'tin' => config('services.efris.tin'),
                ]);

            return [
                'status' => $ready ? 'ready' : 'blocked',
                'efris_required' => true,
                'efris_enabled' => (bool) config('opfin.accounting.efris_enabled', false),
            ];
        }

        $reference = trim((string) config('opfin.accounting.efris_determination_reference', ''));

        return [
            'status' => $reference !== '' ? 'ready' : 'blocked',
            'efris_required' => false,
            'reason' => $reference === ''
                ? 'A tax determination reference is required when EFRIS is declared not applicable.'
                : null,
            'determination_reference' => $reference !== '' ? $reference : null,
        ];
    }

    private function allConfigured(array $fields): bool
    {
        return collect($fields)->every(fn ($value) => is_string($value) && trim($value) !== '');
    }
}
