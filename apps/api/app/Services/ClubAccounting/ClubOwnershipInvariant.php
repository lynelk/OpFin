<?php

namespace App\Services\ClubAccounting;

use InvalidArgumentException;

final class ClubOwnershipInvariant
{
    public static function issue(string $model, int $netAssets, int $memberCapital, int $memberUnits): ?string
    {
        if (! in_array($model, ['capital_accounts', 'unitised'], true)) {
            throw new InvalidArgumentException('Unknown club ownership model.');
        }
        if ($memberCapital < 0 || $memberUnits < 0) {
            return 'negative_member_ownership';
        }
        if ($model === 'capital_accounts' && $memberUnits !== 0) {
            return 'capital_account_book_has_units';
        }
        $ownership = $model === 'unitised' ? $memberUnits : $memberCapital;
        if ($netAssets > 0 && $ownership === 0) {
            return 'positive_net_assets_without_member_ownership';
        }

        return null;
    }
}
