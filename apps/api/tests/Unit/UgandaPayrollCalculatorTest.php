<?php

namespace Tests\Unit;

use App\Services\People\UgandaPayrollCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UgandaPayrollCalculatorTest extends TestCase
{
    public function test_ura_2026_resident_thresholds_and_high_income_surtax(): void
    {
        $service = new UgandaPayrollCalculator;
        $expectations = [
            0 => 0,
            335000 => 0,
            335001 => 0, // Half-up whole-UGX rounding.
            410000 => 15000,
            485000 => 33750,
            500000 => 38250,
            10000000 => 2888250,
            15000000 => 4888250,
        ];
        foreach ($expectations as $salary => $expected) {
            $this->assertSame($expected, $service->residentPaye($salary), 'Wrong 2026 PAYE at '.$salary);
        }
    }

    public function test_nssf_is_not_deducted_from_resident_paye_base_and_employer_share_is_not_employee_wage(): void
    {
        $result = (new UgandaPayrollCalculator)->preview(
            '2026-10-01', 500000, 0, 0, true
        );
        $this->assertSame(38250, $result['paye_minor']);
        $this->assertSame(25000, $result['employee_nssf_minor']);
        $this->assertSame(50000, $result['employer_nssf_minor']);
        $this->assertSame(436750, $result['net_minor']);
        $this->assertSame(550000, $result['employer_total_cost_minor']);
        $this->assertTrue($result['calculation_only']);
        $this->assertSame('not_attested', $result['employer_statutory_certification']);
    }

    public function test_reviewed_local_service_tax_reduces_chargeable_income_not_nssf_wage(): void
    {
        $result = (new UgandaPayrollCalculator)->preview(
            '2026-10-01', 500000, 10000, 5000, true
        );
        $this->assertSame(490000, $result['chargeable_income_minor']);
        $this->assertSame(35250, $result['paye_minor']);
        $this->assertSame(25000, $result['employee_nssf_minor']);
        $this->assertSame(424750, $result['net_minor']);
    }

    public function test_old_period_cannot_use_amended_schedule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UgandaPayrollCalculator)->preview('2026-06-01', 500000, 0, 0, true);
    }

    public function test_excess_deductions_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UgandaPayrollCalculator)->preview('2026-10-01', 350000, 10000, 340000, true);
    }
}
