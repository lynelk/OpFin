<?php

namespace App\Contracts;

interface PayrollDeductionProviderInterface
{
    public function capability(): array;

    public function requestAffordability(array $subject): array;

    public function reserve(array $case): array;

    public function submitKeyFacts(array $case): array;

    public function checkStatus(array $case): array;
}
