<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence\Layouts;

/**
 * A versioned reader for one family of statement layouts. Adapters only extract what the document
 * shows: they never invent balances, directions or references that the text does not contain.
 */
interface StatementLayoutAdapter
{
    public function id(): string;

    public function version(): string;

    /** Issuer codes whose layouts were validated against authorised redacted samples. */
    public function validatedFor(): array;

    /**
     * @param  list<string>  $lines
     * @return array{opening_balance_minor: ?int, closing_balance_minor: ?int, candidate_lines: int,
     *     transactions: list<array{line: int, date: string, reference: ?string, description: string,
     *     amount_minor: int, direction: ?string, balance_minor: ?int}>}|null null when the layout is not recognised
     */
    public function extract(array $lines, int $minorUnitExponent): ?array;
}
