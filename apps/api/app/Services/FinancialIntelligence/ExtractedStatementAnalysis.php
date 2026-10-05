<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use App\Services\FinancialIntelligence\Layouts\StatementLayoutAdapter;
use InvalidArgumentException;

/**
 * Checks rows extracted from a document against their own running balances, then hands the rows
 * whose direction the document establishes to StatementEngine. Each row is checked against the
 * balance printed on the row before it, so one altered row is flagged once instead of cascading.
 */
final class ExtractedStatementAnalysis
{
    /** @param array{currency: string, period_start: string, period_end: string, declared_opening_balance_minor?: ?int, declared_closing_balance_minor?: ?int} $input */
    public function analyse(array $extraction, StatementLayoutAdapter $adapter, string $issuerCode, array $input): array
    {
        $rows = $extraction['transactions'];
        $order = 'chronological';
        if (count($rows) > 1 && $rows[0]['date'] > $rows[count($rows) - 1]['date']) {
            $rows = array_reverse($rows);
            $order = 'reverse_chronological';
        }
        $findings = [];
        $balances = [];
        foreach (['opening', 'closing'] as $label) {
            $documented = $extraction["{$label}_balance_minor"];
            $declared = $input["declared_{$label}_balance_minor"] ?? null;
            if ($documented !== null && $declared !== null && $documented !== $declared) {
                $findings[] = $this->finding("declared_{$label}_balance_differs", null);
            }
            $balances[$label] = $documented ?? $declared;
            $balances["{$label}_source"] = $documented !== null ? 'document' : ($declared !== null ? 'declared_by_uploader' : null);
        }

        $previous = $balances['opening'];
        $previousDate = null;
        $ordered = true;
        $resolved = [];
        $unresolved = 0;
        foreach ($rows as $row) {
            $amount = $row['amount_minor'];
            if ($row['date'] < $input['period_start'] || $row['date'] > $input['period_end']) {
                $findings[] = $this->finding('row_outside_declared_period', $row['line']);
                $unresolved++;
                $previous = $row['balance_minor'];

                continue;
            }
            if ($previousDate !== null && $row['date'] < $previousDate) {
                $ordered = false;
            }
            $previousDate = $row['date'];
            $direction = $row['direction'];
            $balance = $row['balance_minor'];
            if ($direction === null) {
                if ($previous !== null && $balance !== null) {
                    $direction = match ($balance - $previous) {
                        $amount => 'credit',
                        -$amount => 'debit',
                        default => null,
                    };
                    if ($direction === null) {
                        $findings[] = $this->finding('amount_balance_discrepancy', $row['line']);
                    }
                } else {
                    $findings[] = $this->finding('direction_unresolved', $row['line']);
                }
            } elseif ($previous !== null && $balance !== null && $previous + ($direction === 'credit' ? $amount : -$amount) !== $balance) {
                $findings[] = $this->finding('running_balance_mismatch', $row['line']);
            }
            $previous = $balance ?? ($previous !== null && $direction !== null ? $previous + ($direction === 'credit' ? $amount : -$amount) : null);
            if ($direction === null) {
                $unresolved++;

                continue;
            }
            $resolved[] = [...$row, 'direction' => $direction];
        }

        $extractionSummary = ['adapter' => $adapter->id(), 'adapter_version' => $adapter->version(),
            'layout_validated' => in_array($issuerCode, $adapter->validatedFor(), true),
            'candidate_lines' => $extraction['candidate_lines'], 'parsed_rows' => count($rows), 'resolved_rows' => count($resolved),
            'coverage' => $unresolved === 0 && count($rows) >= $extraction['candidate_lines'] ? 'complete' : 'partial',
            'source_order' => $ordered ? $order : 'mixed', 'opening_balance_source' => $balances['opening_source'],
            'closing_balance_source' => $balances['closing_source']];

        if (! $ordered) {
            return $this->unassessed($input, [...$findings, $this->finding('rows_not_in_date_order', null)], $extractionSummary, $rows);
        }
        $engineInput = ['currency' => $input['currency'], 'period_start' => $input['period_start'], 'period_end' => $input['period_end'],
            'transactions' => array_map(fn (array $row): array => ['date' => $row['date'], 'description' => $row['description'] !== '' ? $row['description'] : 'Statement entry',
                'direction' => $row['direction'], 'amount_minor' => $row['amount_minor']] + ($row['reference'] !== null ? ['reference' => $row['reference']] : []), $resolved)];
        foreach (['opening', 'closing'] as $label) {
            if ($balances[$label] !== null) {
                $engineInput["{$label}_balance_minor"] = $balances[$label];
            }
        }
        try {
            $analysis = (new StatementEngine)->analyse($engineInput);
        } catch (InvalidArgumentException) {
            return $this->unassessed($input, [...$findings, $this->finding('extracted_values_out_of_bounds', null)], $extractionSummary, $rows);
        }

        foreach ($analysis['findings'] as &$finding) {
            $finding['source_line'] = $finding['row'] !== null ? ($resolved[$finding['row'] - 1]['line'] ?? null) : null;
        }
        unset($finding);
        foreach ($analysis['transactions'] as $index => &$transaction) {
            $transaction['balance_minor'] = $resolved[$index]['balance_minor'];
            $transaction['source_line'] = $resolved[$index]['line'];
        }
        unset($transaction);
        $analysis['findings'] = [...$analysis['findings'], ...$findings];
        $review = array_filter($analysis['findings'], fn (array $finding): bool => $finding['severity'] === 'review') !== [];
        $analysis['financial_checks'] = $review ? 'review_required'
            : ($balances['opening'] === null || $balances['closing'] === null ? 'incomplete' : 'consistent');
        $analysis['extraction'] = $extractionSummary;

        return $analysis;
    }

    private function finding(string $code, ?int $line): array
    {
        return ['code' => $code, 'row' => null, 'source_line' => $line, 'severity' => 'review'];
    }

    private function unassessed(array $input, array $findings, array $extraction, array $rows): array
    {
        return ['engine_version' => null, 'currency' => $input['currency'], 'period_start' => $input['period_start'], 'period_end' => $input['period_end'],
            'row_count' => count($rows), 'transactions' => array_map(fn (array $row): array => ['source_line' => $row['line'], 'date' => $row['date'],
                'description' => $row['description'], 'direction' => $row['direction'], 'amount_minor' => $row['amount_minor'], 'balance_minor' => $row['balance_minor']], $rows),
            'findings' => $findings, 'financial_checks' => 'unable_to_assess', 'extraction' => $extraction,
            'source_authenticity' => 'unconfirmed', 'account_ownership' => 'unconfirmed', 'verified_income_minor' => null, 'credit_decision_eligible' => false,
            'interpretation' => 'The extracted rows could not be checked as a single dated balance chain. They need review against the original document.'];
    }
}
