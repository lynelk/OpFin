<?php

namespace App\Services;

use InvalidArgumentException;

class CreditCashFlowService
{
    public const APR_ALGORITHM_VERSION = 'dated-cash-flow-bisection-v1';

    public const SCHEDULE_ALGORITHM_VERSION = 'frequency-stub-v2';

    /**
     * @return array<int, array{
     *   installment_number:int,
     *   due_offset_days:int,
     *   principal_minor:int,
     *   interest_minor:int,
     *   fees_minor:int,
     *   total_due_minor:int
     * }>
     */
    public function repaymentSchedule(
        int $principalMinor,
        int $interestMinor,
        int $feesMinor,
        int $durationDays,
        string $repaymentFrequency,
    ): array {
        foreach ([
            'principal' => $principalMinor,
            'interest' => $interestMinor,
            'fees' => $feesMinor,
        ] as $label => $amount) {
            if ($amount < 0) {
                throw new InvalidArgumentException("Credit {$label} amount cannot be negative.");
            }
        }
        if ($principalMinor <= 0) {
            throw new InvalidArgumentException('Credit principal must be positive.');
        }

        $offsets = $this->dueOffsets($durationDays, $this->frequencyDays($repaymentFrequency));
        $count = count($offsets);
        $principal = $this->allocate($principalMinor, $count);
        $interest = $this->allocate($interestMinor, $count);
        $fees = $this->allocate($feesMinor, $count);

        $schedule = [];
        foreach ($offsets as $index => $offset) {
            $schedule[] = [
                'installment_number' => $index + 1,
                'due_offset_days' => $offset,
                'principal_minor' => $principal[$index],
                'interest_minor' => $interest[$index],
                'fees_minor' => $fees[$index],
                'total_due_minor' => $principal[$index] + $interest[$index] + $fees[$index],
            ];
        }

        return $schedule;
    }

    /**
     * Produce contractual due offsets without creating a tiny residual instalment immediately
     * after a normal frequency boundary. A residual period shorter than half a normal cycle is
     * merged into the preceding instalment; the contractual final due date always remains the
     * exact term end.
     *
     * @return array<int, int>
     */
    public function dueOffsets(int $durationDays, int $frequencyDays): array
    {
        if ($durationDays <= 0 || $frequencyDays <= 0) {
            throw new InvalidArgumentException('Credit duration and repayment frequency must be positive.');
        }

        if ($durationDays <= $frequencyDays) {
            return [$durationDays];
        }

        $wholeCycles = intdiv($durationDays, $frequencyDays);
        $remainderDays = $durationDays % $frequencyDays;
        $offsets = [];

        for ($cycle = 1; $cycle <= $wholeCycles; $cycle++) {
            $offsets[] = $cycle * $frequencyDays;
        }

        if ($remainderDays === 0) {
            return $offsets;
        }

        $minimumStandaloneStubDays = max(1, (int) ceil($frequencyDays / 2));

        if ($remainderDays < $minimumStandaloneStubDays && $offsets !== []) {
            $offsets[count($offsets) - 1] = $durationDays;
        } else {
            $offsets[] = $durationDays;
        }

        return array_values(array_unique($offsets));
    }

    /**
     * Effective annual percentage rate from the actual dated borrower cash flows.
     *
     * The borrower receives netDisbursementMinor at day zero and pays the schedule amounts
     * on their contractual due offsets. The solved rate r satisfies:
     *
     * net_disbursement = Σ(payment / (1 + r)^(days / 365)).
     */
    public function equivalentAnnualPercentageRate(int $netDisbursementMinor, array $schedule): float
    {
        if ($netDisbursementMinor <= 0) {
            throw new InvalidArgumentException('Net disbursement must be positive for APR calculation.');
        }
        if ($schedule === []) {
            throw new InvalidArgumentException('A repayment schedule is required for APR calculation.');
        }

        $totalRepayment = 0;
        foreach ($schedule as $item) {
            $days = (int) ($item['due_offset_days'] ?? 0);
            $amount = (int) ($item['total_due_minor'] ?? 0);
            if ($days <= 0 || $amount <= 0) {
                throw new InvalidArgumentException('APR cash flows require positive due offsets and repayment amounts.');
            }
            $totalRepayment += $amount;
        }

        if ($totalRepayment === $netDisbursementMinor) {
            return 0.0;
        }

        $npv = function (float $annualRate) use ($netDisbursementMinor, $schedule): float {
            $presentValue = 0.0;
            foreach ($schedule as $item) {
                $days = (int) $item['due_offset_days'];
                $amount = (int) $item['total_due_minor'];
                $presentValue += $amount / pow(1.0 + $annualRate, $days / 365.0);
            }

            return $presentValue - $netDisbursementMinor;
        };

        $low = -0.999999;
        $high = 1.0;

        while ($npv($high) > 0.0 && $high < 1000000.0) {
            $high *= 2.0;
        }

        if ($npv($high) > 0.0) {
            throw new InvalidArgumentException('Unable to bracket the effective APR for the disclosed cash flows.');
        }

        for ($iteration = 0; $iteration < 200; $iteration++) {
            $mid = ($low + $high) / 2.0;
            if ($npv($mid) > 0.0) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return (($low + $high) / 2.0) * 100.0;
    }

    public function frequencyDays(string $frequency): int
    {
        return match (strtolower(trim($frequency))) {
            'daily' => 1,
            'weekly' => 7,
            'fortnightly' => 14,
            'monthly' => 30,
            default => throw new InvalidArgumentException('Unsupported repayment frequency.'),
        };
    }

    /**
     * @return array<int, int>
     */
    private function allocate(int $total, int $count): array
    {
        if ($total < 0 || $count <= 0) {
            throw new InvalidArgumentException('Invalid monetary allocation parameters.');
        }

        $base = intdiv($total, $count);
        $remainder = $total % $count;
        $values = array_fill(0, $count, $base);
        $values[$count - 1] += $remainder;

        return $values;
    }
}
