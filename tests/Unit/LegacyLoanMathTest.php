<?php

namespace Tests\Unit;

use App\Models\Loan;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LegacyLoanMathTest extends TestCase
{
    public function test_short_term_monthly_frequency_never_rounds_to_zero_installments(): void
    {
        $this->assertSame(1, Loan::getInstallments(7, 'Monthly'));
    }

    public function test_zero_rate_amortization_returns_principal_without_division_by_zero(): void
    {
        $this->assertSame(
            100000.0,
            Loan::getRepaymentAmount(0.0, 100000, 'Amortization', 1, 'Monthly', 30),
        );
    }

    public function test_unsupported_frequency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Loan::getInstallments(30, 'Quarterly');
    }
}
