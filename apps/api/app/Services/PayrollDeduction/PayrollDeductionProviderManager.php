<?php

namespace App\Services\PayrollDeduction;

use App\Contracts\PayrollDeductionProviderInterface;
use LogicException;

class PayrollDeductionProviderManager
{
    public function provider(?string $name = null): PayrollDeductionProviderInterface
    {
        $provider = $name ?: (string) config('payroll_deduction.default_provider', 'pdms');
        $mode = (string) config("payroll_deduction.providers.{$provider}.mode", 'inactive');

        if ($provider === 'pdms' && in_array($mode, ['inactive', 'manual_evidence'], true)) {
            return new InactivePdmsAdapter();
        }

        throw new LogicException("Payroll deduction provider [{$provider}] is not certified or configured.");
    }
}
