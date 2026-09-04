<?php

namespace Tests\Unit;

use App\Services\ProductionLedgerService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProductionLedgerServiceTest extends TestCase
{
    public function test_rejects_unbalanced_entries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ledger transaction is not balanced.');

        (new ProductionLedgerService())->assertBalanced([
            ['direction' => 'debit', 'amount_minor' => 1000],
            ['direction' => 'credit', 'amount_minor' => 999],
        ]);
    }

    public function test_rejects_non_positive_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('positive integer minor units');

        (new ProductionLedgerService())->assertBalanced([
            ['direction' => 'debit', 'amount_minor' => 0],
            ['direction' => 'credit', 'amount_minor' => 0],
        ]);
    }

    public function test_accepts_balanced_minor_unit_entries(): void
    {
        (new ProductionLedgerService())->assertBalanced([
            ['direction' => 'debit', 'amount_minor' => 1000],
            ['direction' => 'credit', 'amount_minor' => 1000],
        ]);

        $this->assertTrue(true);
    }
}
