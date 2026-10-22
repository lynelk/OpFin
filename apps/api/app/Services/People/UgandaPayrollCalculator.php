<?php

namespace App\Services\People;

use InvalidArgumentException;

/**
 * Deterministic resident Uganda monthly PAYE/NSSF preview from 1 July 2026.
 * Based on URA Income Tax Amendment 2026 guidance and NSSF standard shares.
 *
 * This does not decide employment tax residency, statutory exemptions,
 * local-service-tax assessment, taxable benefits or regulatory certification.
 * Those are employer/legal evidence and review decisions.
 */
final class UgandaPayrollCalculator
{
    public const EFFECTIVE_FROM = '2026-07-01';

    public const POLICY_VERSION = 'UG-RESIDENT-2026-07';

    public const URA_REFERENCE = 'https://ura.go.ug/en/changes-to-paye-return-form-following-the-income-tax-amendment-act-2026/';

    public const NSSF_REFERENCE = 'https://www.nssfug.org/about-us/membership/';

    public function preview(
        string $periodStart,
        int $grossMinor,
        int $localServiceTaxMinor,
        int $otherAuthorisedDeductionsMinor,
        bool $nssfApplicable
    ): array {
        if ($periodStart < self::EFFECTIVE_FROM) {
            throw new InvalidArgumentException('The current PAYE schedule cannot be used before 1 July 2026.');
        }
        if ($grossMinor < 0 || $grossMinor > 100000000000
            || $localServiceTaxMinor < 0
            || $otherAuthorisedDeductionsMinor < 0
            || $localServiceTaxMinor + $otherAuthorisedDeductionsMinor > $grossMinor) {
            throw new InvalidArgumentException('Gross pay and authorised deduction inputs are inconsistent.');
        }

        // URA: mandatory LST is deductible when establishing chargeable income.
        // Employee NSSF contributions remain part of taxable employment income.
        $chargeable = $grossMinor - $localServiceTaxMinor;
        $paye = $this->residentPaye($chargeable);
        $employeeNssf = $nssfApplicable ? $this->percentage($grossMinor, 5) : 0;
        $employerNssf = $nssfApplicable ? $this->percentage($grossMinor, 10) : 0;
        $deductions = $paye + $employeeNssf + $localServiceTaxMinor + $otherAuthorisedDeductionsMinor;
        if ($deductions > $grossMinor) {
            throw new InvalidArgumentException('Calculated deductions exceed available gross pay.');
        }

        return [
            'policy_version' => self::POLICY_VERSION,
            'residence_classification' => 'resident',
            'currency' => 'UGX',
            'gross_minor' => $grossMinor,
            'chargeable_income_minor' => $chargeable,
            'paye_minor' => $paye,
            'employee_nssf_minor' => $employeeNssf,
            'employer_nssf_minor' => $employerNssf,
            'local_service_tax_minor' => $localServiceTaxMinor,
            'other_authorised_deductions_minor' => $otherAuthorisedDeductionsMinor,
            'total_employee_deductions_minor' => $deductions,
            'net_minor' => $grossMinor - $deductions,
            'employer_total_cost_minor' => $grossMinor + $employerNssf,
            'rounding' => 'nearest_whole_UGX_half_up',
            'source_ura' => self::URA_REFERENCE,
            'source_nssf' => self::NSSF_REFERENCE,
            'calculation_only' => true,
            'employer_statutory_certification' => 'not_attested',
        ];
    }

    public function residentPaye(int $monthlyChargeableMinor): int
    {
        if ($monthlyChargeableMinor < 0) {
            throw new InvalidArgumentException('Monthly chargeable income cannot be negative.');
        }
        if ($monthlyChargeableMinor <= 335000) {
            return 0;
        }
        if ($monthlyChargeableMinor <= 410000) {
            return $this->percentage($monthlyChargeableMinor - 335000, 20);
        }
        if ($monthlyChargeableMinor <= 485000) {
            return 15000 + $this->percentage($monthlyChargeableMinor - 410000, 25);
        }
        $ordinary = 33750 + $this->percentage($monthlyChargeableMinor - 485000, 30);

        return $monthlyChargeableMinor > 10000000
            ? $ordinary + $this->percentage($monthlyChargeableMinor - 10000000, 10)
            : $ordinary;
    }

    private function percentage(int $amountMinor, int $percent): int
    {
        return intdiv($amountMinor * $percent + 50, 100);
    }
}
