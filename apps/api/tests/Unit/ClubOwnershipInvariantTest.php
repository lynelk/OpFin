<?php

namespace Tests\Unit;

use App\Services\ClubAccounting\ClubOwnershipInvariant;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClubOwnershipInvariantTest extends TestCase
{
    public function test_owned_asset_invariant_preserves_valid_models_and_flags_invalid_ownership(): void
    {
        $cases = [
            ['capital_accounts', 1000, 0, 0, 'positive_net_assets_without_member_ownership'],
            ['capital_accounts', 1000, 1000, 0, null],
            ['capital_accounts', 0, 0, 0, null],
            ['capital_accounts', -100, 1000, 0, null],
            ['capital_accounts', 1000, 1000, 1, 'capital_account_book_has_units'],
            ['capital_accounts', 0, -1, 0, 'negative_member_ownership'],
            ['unitised', 1000, 1000, 0, 'positive_net_assets_without_member_ownership'],
            ['unitised', 1000, 0, 1000000, null],
            ['unitised', 0, 0, 0, null],
            ['unitised', -100, 1000, 1000000, null],
            ['unitised', 100, 100, -1, 'negative_member_ownership'],
        ];
        foreach ($cases as [$model, $netAssets, $capital, $units, $expected]) {
            $this->assertSame($expected, ClubOwnershipInvariant::issue($model, $netAssets, $capital, $units));
        }
    }

    public function test_unknown_ownership_model_is_not_silently_treated_as_capital_accounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClubOwnershipInvariant::issue('unknown', 0, 0, 0);
    }
}
