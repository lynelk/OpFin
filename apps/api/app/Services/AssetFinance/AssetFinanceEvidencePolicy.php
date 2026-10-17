<?php

namespace App\Services\AssetFinance;

use InvalidArgumentException;

final class AssetFinanceEvidencePolicy
{
    /** @return array{pre_approval: list<string>, activation: list<string>} */
    public static function for(string $vertical, string $assetClass): array
    {
        $rules = config('asset_finance.verticals.'.$vertical);
        if (! is_array($rules) || ! array_key_exists($assetClass, config('asset_registry.classes', []))) {
            throw new InvalidArgumentException('Unrecognised asset class or finance vertical.');
        }
        $extra = $rules['class_evidence'][$assetClass] ?? [];

        return [
            'pre_approval' => array_values(array_unique([
                ...$rules['pre_approval_evidence'], ...($extra['pre_approval'] ?? []),
            ])),
            'activation' => array_values(array_unique([
                ...$rules['activation_evidence'], ...($extra['activation'] ?? []),
            ])),
        ];
    }
}
