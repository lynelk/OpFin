<?php

namespace App\Services;

use InvalidArgumentException;

class RegulatoryActivationGuard
{
    public function assertCreditDisclosuresReady(): void
    {
        $status = $this->creditDisclosureStatus();
        if (! $status['required'] || $status['status'] === 'ready') {
            return;
        }

        throw new InvalidArgumentException(
            'Regulated credit activation is blocked until required lender identity and complaints disclosures are configured: '
            .implode(', ', $status['missing'])
            .'.'
        );
    }

    public function creditDisclosureStatus(): array
    {
        $required = (bool) config('opfin.regulatory.require_credit_disclosure', false);
        $fields = [
            'licensed_entity_name' => config('opfin.regulatory.licensed_entity_name'),
            'umra_license_number' => config('opfin.regulatory.umra_license_number'),
            'business_address' => config('opfin.regulatory.business_address'),
            'complaints_email' => config('opfin.regulatory.complaints_email'),
            'complaints_phone' => config('opfin.regulatory.complaints_phone'),
        ];

        $missing = collect($fields)
            ->filter(fn ($value) => ! is_string($value) || trim($value) === '')
            ->keys()
            ->values()
            ->all();

        return [
            'status' => ! $required || $missing === [] ? 'ready' : 'blocked',
            'required' => $required,
            'missing' => $missing,
            'configured_fields' => collect($fields)->keys()->diff($missing)->values()->all(),
        ];
    }
}
