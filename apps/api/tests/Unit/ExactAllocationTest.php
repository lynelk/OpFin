<?php

namespace Tests\Unit;

use App\Support\ExactAllocation;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class ExactAllocationTest extends TestCase
{
    public function test_multiply_divide_is_exact_beyond_safe_intermediate_multiplication(): void
    {
        $this->assertSame([9007199254740991, 0], ExactAllocation::multiplyDivide(9007199254740991, 1000000, 1000000));
        $this->assertSame([3333333333333333, 1], ExactAllocation::multiplyDivide(10000000000000000, 1, 3));
        $this->assertSame([0, 0], ExactAllocation::multiplyDivide(0, 999, 1));
    }

    public function test_small_values_match_direct_integer_arithmetic(): void
    {
        for ($a = 0; $a < 40; $a++) {
            for ($b = 0; $b < 30; $b++) {
                for ($d = 1; $d < 17; $d++) {
                    $this->assertSame([intdiv($a * $b, $d), ($a * $b) % $d], ExactAllocation::multiplyDivide($a, $b, $d));
                }
            }
        }
    }

    public function test_proportional_allocations_consume_every_minor_unit_with_stable_ties(): void
    {
        $this->assertSame(['a' => 4, 'b' => 3], ExactAllocation::proportional(7, ['a' => 1, 'b' => 1]));
        $this->assertSame(['b' => 3, 'a' => 4], ExactAllocation::proportional(7, ['b' => 1, 'a' => 1]));
        $this->assertSame(['a' => 5, 'b' => 2, 'c' => 0], ExactAllocation::proportional(7, ['a' => 2, 'b' => 1, 'c' => 0]));
        $this->assertSame(['a' => 0, 'b' => 0], ExactAllocation::proportional(0, ['a' => 1, 'b' => 1]));
    }

    public function test_integer_overflow_is_not_silently_converted_to_float(): void
    {
        $this->expectException(OverflowException::class);
        ExactAllocation::add(PHP_INT_MAX, 1);
    }

    public function test_invalid_weights_do_not_create_allocations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ExactAllocation::proportional(1, ['a' => 0]);
    }
}
