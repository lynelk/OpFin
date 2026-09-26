<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

/** Integer-only allocation; no floating-point intermediate is used for money or units. */
final class ExactAllocation
{
    public static function add(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new OverflowException('The accounting amount exceeds the supported integer range.');
        }

        return $left + $right;
    }

    /** Return [floor(a*b/divisor), remainder] without overflowing the intermediate product. */
    public static function multiplyDivide(int $a, int $b, int $divisor): array
    {
        if ($a < 0 || $b < 0 || $divisor <= 0) {
            throw new InvalidArgumentException('Allocation requires non-negative integers and a positive divisor.');
        }
        $whole = intdiv($b, $divisor);
        $fraction = $b % $divisor;
        $result = 0;
        $remainder = 0;
        while ($a > 0) {
            if (($a & 1) === 1) {
                $result = self::add($result, $whole);
                if ($remainder >= $divisor - $fraction) {
                    $remainder -= $divisor - $fraction;
                    $result = self::add($result, 1);
                } else {
                    $remainder += $fraction;
                }
            }
            $a = intdiv($a, 2);
            if ($a === 0) {
                break;
            }
            $whole = self::add($whole, $whole);
            if ($fraction >= $divisor - $fraction) {
                $fraction -= $divisor - $fraction;
                $whole = self::add($whole, 1);
            } else {
                $fraction += $fraction;
            }
        }

        return [$result, $remainder];
    }

    /** Largest-remainder allocation. Stable identifier ordering breaks ties. */
    public static function proportional(int $amount, array $weights): array
    {
        if ($amount < 0 || $weights === []) {
            throw new InvalidArgumentException('An amount and at least one allocation weight are required.');
        }
        $total = 0;
        foreach ($weights as $key => $weight) {
            if (! is_int($weight) || $weight < 0) {
                throw new InvalidArgumentException('Allocation weights must be non-negative integers.');
            }
            $total = self::add($total, $weight);
        }
        if ($total === 0) {
            throw new InvalidArgumentException('An allocation cannot use only zero weights.');
        }
        $allocated = [];
        $fractions = [];
        $used = 0;
        foreach ($weights as $key => $weight) {
            [$share, $remainder] = self::multiplyDivide($amount, $weight, $total);
            $allocated[$key] = $share;
            $fractions[] = ['key' => $key, 'remainder' => $remainder];
            $used = self::add($used, $share);
        }
        usort($fractions, static fn (array $a, array $b): int =>
            ($b['remainder'] <=> $a['remainder']) ?: strcmp((string) $a['key'], (string) $b['key']));
        $left = $amount - $used;
        foreach ($fractions as $item) {
            if ($left === 0) {
                break;
            }
            $allocated[$item['key']]++;
            $left--;
        }
        if ($left !== 0) {
            throw new InvalidArgumentException('The allocation did not consume the amount exactly.');
        }

        return $allocated;
    }
}
