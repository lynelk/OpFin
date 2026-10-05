<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence\Layouts;

/**
 * Chooses layout adapters for an issuer. Issuer-specific adapters are registered here only after
 * validation against authorised redacted samples; until then the unvalidated generic reader is the
 * only candidate, and a document it cannot read is reported as "not yet supported".
 */
final class LayoutRegistry
{
    /** @var array<string, list<class-string<StatementLayoutAdapter>>> issuer code => adapters, most specific first */
    private const ISSUER_ADAPTERS = [];

    /** @return list<StatementLayoutAdapter> */
    public function for(string $issuerCode): array
    {
        $adapters = array_map(fn (string $class): StatementLayoutAdapter => app($class), self::ISSUER_ADAPTERS[$issuerCode] ?? []);
        $adapters[] = app(GenericRunningBalanceLayout::class);

        return $adapters;
    }
}
