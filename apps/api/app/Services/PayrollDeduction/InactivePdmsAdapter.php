<?php

namespace App\Services\PayrollDeduction;

use App\Contracts\PayrollDeductionProviderInterface;
use LogicException;

class InactivePdmsAdapter implements PayrollDeductionProviderInterface
{
    public function capability(): array
    {
        return [
            'provider' => 'pdms',
            'mode' => 'manual_evidence',
            'machine_interface_active' => false,
            'reason' => 'Official PDMS machine interface details and production credentials are not configured.',
        ];
    }

    public function requestAffordability(array $subject): array
    {
        return $this->blocked();
    }

    public function reserve(array $case): array
    {
        return $this->blocked();
    }

    public function submitKeyFacts(array $case): array
    {
        return $this->blocked();
    }

    public function checkStatus(array $case): array
    {
        return $this->blocked();
    }

    private function blocked(): array
    {
        throw new LogicException('The live PDMS connector is not activated. Record verified provider evidence through the controlled operations workflow instead.');
    }
}
