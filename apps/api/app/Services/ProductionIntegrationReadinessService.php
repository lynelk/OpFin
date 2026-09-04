<?php

namespace App\Services;

class ProductionIntegrationReadinessService
{
    public function report(): array
    {
        $integrations = [
            'cpay' => $this->check([
                'base_url' => config('services.cpay.base_url'),
                'merchant_number' => config('services.cpay.merchant_number'),
                'private_key' => config('services.cpay.private_key'),
                'callback_url' => config('services.cpay.callback_url'),
                'callback_secret' => config('services.cpay.callback_secret'),
            ], true, 'Money movement and provider-finality reconciliation'),
            'sms' => $this->sms(),
            'whatsapp' => $this->check([
                'phone_number_id' => config('services.whatsapp.phone_number_id'),
                'access_token' => config('services.whatsapp.access_token'),
                'app_secret' => config('services.whatsapp.app_secret'),
                'verify_token' => config('services.whatsapp.verify_token'),
            ], false, 'WhatsApp assisted channel'),
            'crb' => $this->check([
                'base_url' => config('services.crb.base_url'),
                'client_id' => config('services.crb.account'),
                'client_secret' => config('services.crb.password'),
            ], false, 'Credit-reference data'),
            'airtel_kyc' => $this->check([
                'base_url' => config('services.airtel.base_url'),
                'client_id' => config('services.airtel.client_id'),
                'client_secret' => config('services.airtel.client_secret'),
            ], false, 'Airtel identity/KYC lookup only'),
        ];

        $requiredReady = collect($integrations)
            ->filter(fn (array $integration) => $integration['required'])
            ->every(fn (array $integration) => $integration['status'] === 'ready');

        return [
            'production_ready' => $requiredReady,
            'required_integrations_ready' => $requiredReady,
            'integrations' => $integrations,
            'rules' => [
                'optional_integrations_may_remain_inactive_until_provider_activation',
                'missing_credentials_never_enable_mock_or fallback_money_movement',
                'provider_acceptance_is_not_accounting_finality',
                'secrets_are_never_returned_by_this_endpoint',
            ],
        ];
    }

    private function sms(): array
    {
        $gateway = strtoupper(trim((string) config('services.sms_gateway')));

        if ($gateway === 'YO') {
            return $this->check([
                'gateway' => $gateway,
                'base_url' => config('services.yo.base_url'),
                'account' => config('services.yo.account'),
                'password' => config('services.yo.password'),
            ], true, 'OTP and customer security notifications');
        }

        if ($gateway === 'CPAY') {
            return $this->check([
                'gateway' => $gateway,
                'base_url' => config('services.cpay.base_url'),
                'merchant_number' => config('services.cpay.merchant_number'),
                'private_key' => config('services.cpay.private_key'),
                'sms_path' => config('services.cpay.sms_path'),
            ], true, 'OTP and customer security notifications');
        }

        return [
            'status' => 'blocked',
            'required' => true,
            'purpose' => 'OTP and customer security notifications',
            'missing' => ['SMS_GATEWAY'],
            'configured_fields' => [],
        ];
    }

    private function check(array $fields, bool $required, string $purpose): array
    {
        $missing = collect($fields)
            ->filter(fn ($value) => ! is_string($value) || trim($value) === '')
            ->keys()
            ->values()
            ->all();

        $configured = collect($fields)
            ->reject(fn ($value) => ! is_string($value) || trim($value) === '')
            ->keys()
            ->values()
            ->all();

        return [
            'status' => empty($missing) ? 'ready' : ($required ? 'blocked' : 'inactive'),
            'required' => $required,
            'purpose' => $purpose,
            'missing' => $missing,
            'configured_fields' => $configured,
        ];
    }
}
