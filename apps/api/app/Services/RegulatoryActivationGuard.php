<?php

namespace App\Services;

use App\Models\Institution;
use InvalidArgumentException;

class RegulatoryActivationGuard
{
    public function assertCreditDisclosuresReady(?Institution $institution = null): void
    {
        $status = $this->creditDisclosureStatus($institution);

        // Development may leave the guard disabled, but financial readiness will
        // remain blocked. Production boot separately requires the guard enabled.
        if (! $status['required']) {
            return;
        }

        if ($status['status'] === 'ready') {
            return;
        }

        throw new InvalidArgumentException(
            'Regulated credit activation is blocked until the lender-of-record authority and complaints disclosures are complete: '
            .implode(', ', $status['missing'])
            .'.'
        );
    }

    public function creditDisclosureStatus(?Institution $institution = null): array
    {
        $required = (bool) config('opfin.regulatory.require_credit_disclosure', false);

        $complaints = [
            'complaints_email' => config('opfin.regulatory.complaints_email'),
            'complaints_phone' => config('opfin.regulatory.complaints_phone'),
        ];
        $missing = collect($complaints)
            ->filter(fn ($value) => ! is_string($value) || trim($value) === '')
            ->keys()
            ->values()
            ->all();

        $lenders = $institution
            ? collect([$institution])
            : Institution::query()
                ->where('status', 'Active')
                ->whereHas('loanProducts', fn ($query) => $query->where('status', 'Active'))
                ->orderBy('id')
                ->get();

        if ($lenders->isEmpty()) {
            $missing[] = 'active_lender_of_record';
        }

        $lenderStatus = [];
        foreach ($lenders as $lender) {
            $lenderMissing = $this->lenderMissingFields($lender);
            foreach ($lenderMissing as $field) {
                $missing[] = 'lender_'.$lender->id.'.'.$field;
            }
            $lenderStatus[] = [
                'institution_id' => (int) $lender->id,
                'status' => $lenderMissing === [] ? 'ready' : 'blocked',
                'missing' => $lenderMissing,
            ];
        }

        $missing = array_values(array_unique($missing));

        return [
            'status' => $required && $missing === [] ? 'ready' : 'blocked',
            'required' => $required,
            'missing' => $missing,
            'configured_fields' => collect($complaints)->keys()->diff($missing)->values()->all(),
            'lenders' => $lenderStatus,
            'reason' => ! $required
                ? 'The regulated-credit disclosure guard is disabled. Financial UAT and activation require it to be enabled.'
                : ($missing !== [] ? 'Required lender-of-record authority or complaints disclosures are incomplete.' : null),
        ];
    }

    private function lenderMissingFields(Institution $institution): array
    {
        $missing = [];

        if (strcasecmp((string) $institution->status, 'Active') !== 0) {
            $missing[] = 'status';
        }
        if (! is_string($institution->name) || trim($institution->name) === '') {
            $missing[] = 'legal_name';
        }
        if (! is_string($institution->address) || trim($institution->address) === '') {
            $missing[] = 'business_address';
        }
        if (! is_string($institution->country) || ! preg_match('/^[A-Z]{2}$/', $institution->country)) {
            $missing[] = 'country';
        }

        $basis = strtolower(trim((string) $institution->authority_basis));
        if (! in_array($basis, ['licensed', 'other_authority', 'exempt'], true)) {
            $missing[] = 'authority_basis';
        }
        if (! is_string($institution->authority_reference) || trim($institution->authority_reference) === '') {
            $missing[] = 'authority_reference';
        }
        if ($institution->authority_valid_until && $institution->authority_valid_until->copy()->endOfDay()->isPast()) {
            $missing[] = 'authority_valid_until';
        }
        if ($basis === 'licensed') {
            if (! is_string($institution->regulator_code) || trim($institution->regulator_code) === '') {
                $missing[] = 'regulator_code';
            }
            if (! is_string($institution->licence_class) || trim($institution->licence_class) === '') {
                $missing[] = 'licence_class';
            }
        }

        return $missing;
    }
}
