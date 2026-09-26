<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use InvalidArgumentException;

/** Transaction analysis is independent of issuer authentication and lending permission. */
final class StatementEngine
{
    public function analyse(array $input): array
    {
        Values::keys($input, ['currency', 'period_start', 'period_end', 'opening_balance_minor', 'closing_balance_minor', 'transactions'], 'Statement');
        $currency = Values::currency($input['currency'] ?? null);
        $start = Values::date($input['period_start'] ?? null, 'period_start');
        $end = Values::date($input['period_end'] ?? null, 'period_end');
        if ($start > $end || Values::days($start, $end) > 1096) {
            throw new InvalidArgumentException('A statement period must be ordered and at most three years.');
        }
        $opening = isset($input['opening_balance_minor']) ? Values::amount($input['opening_balance_minor'], 'opening_balance_minor', true) : null;
        $closing = isset($input['closing_balance_minor']) ? Values::amount($input['closing_balance_minor'], 'closing_balance_minor', true) : null;
        $transactions = $input['transactions'] ?? null;
        if (! is_array($transactions) || ! array_is_list($transactions) || count($transactions) > 50000) {
            throw new InvalidArgumentException('transactions must be a list containing at most 50,000 records.');
        }
        $rows = [];
        $references = [];
        $findings = [];
        $previousDate = null;
        $balance = $opening;
        $credits = 0;
        $debits = 0;
        $monthly = [];
        $sources = [];
        $categories = [];
        foreach ($transactions as $index => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Each statement transaction must be an object.');
            }
            Values::keys($row, ['date', 'reference', 'description', 'direction', 'amount_minor', 'balance_minor', 'counterparty_ref'], 'Statement transaction');
            $date = Values::date($row['date'] ?? null, 'date');
            if ($date < $start || $date > $end || ($previousDate !== null && $date < $previousDate)) {
                throw new InvalidArgumentException('Transactions must be in source-confirmed chronological order within the statement period.');
            }
            $previousDate = $date;
            $amount = Values::amount($row['amount_minor'] ?? null, 'amount_minor');
            if ($amount === 0 || ! in_array($row['direction'] ?? null, ['credit', 'debit'], true)) {
                throw new InvalidArgumentException('Each transaction needs a positive amount and a credit/debit direction.');
            }
            $description = Values::text($row['description'] ?? null, 'description', 500);
            $reference = isset($row['reference']) ? Values::text($row['reference'], 'reference') : null;
            $counterparty = isset($row['counterparty_ref']) ? Values::text($row['counterparty_ref'], 'counterparty_ref') : null;
            $reportedBalance = isset($row['balance_minor']) ? Values::amount($row['balance_minor'], 'balance_minor', true) : null;
            $signed = $row['direction'] === 'credit' ? $amount : -$amount;
            if ($balance !== null) {
                $balance = Values::add($balance, $signed);
                if ($reportedBalance !== null && $reportedBalance !== $balance) {
                    $findings[] = ['code' => 'running_balance_mismatch', 'row' => $index + 1, 'severity' => 'review'];
                }
            } elseif ($reportedBalance !== null) {
                // This anchors only subsequent checks; it does not invent an opening balance.
                $balance = $reportedBalance;
            }
            if ($reference !== null) {
                $fingerprint = Values::hash([$date, $row['direction'], $amount, $reportedBalance]);
                if (isset($references['ref:'.$reference])) {
                    $findings[] = ['code' => $references['ref:'.$reference] === $fingerprint ? 'repeated_reference' : 'conflicting_reference', 'row' => $index + 1, 'severity' => 'review'];
                }
                $references['ref:'.$reference] = $fingerprint;
            }
            if ($row['direction'] === 'credit') {
                $credits = Values::add($credits, $amount);
            } else {
                $debits = Values::add($debits, $amount);
            }
            $month = substr($date, 0, 7);
            $monthly[$month] ??= ['month' => $month, 'credits_minor' => 0, 'debits_minor' => 0, 'net_movement_minor' => 0];
            $field = $row['direction'] === 'credit' ? 'credits_minor' : 'debits_minor';
            $monthly[$month][$field] = Values::add($monthly[$month][$field], $amount);
            $monthly[$month]['net_movement_minor'] = Values::add($monthly[$month]['net_movement_minor'], $signed);
            $category = $this->category($description);
            $categories[$category] ??= ['category' => $category, 'credit_minor' => 0, 'debit_minor' => 0, 'transaction_count' => 0];
            $categories[$category][$row['direction'].'_minor'] = Values::add($categories[$category][$row['direction'].'_minor'], $amount);
            $categories[$category]['transaction_count']++;
            if ($counterparty !== null) {
                $key = Values::hash([$counterparty, $row['direction']]);
                $sources[$key] ??= ['counterparty_ref' => $counterparty, 'direction' => $row['direction'], 'months' => [], 'amount_minor' => 0, 'count' => 0];
                $sources[$key]['months'][$month] = true;
                $sources[$key]['amount_minor'] = Values::add($sources[$key]['amount_minor'], $amount);
                $sources[$key]['count']++;
            }
            $rows[] = ['row_number' => $index + 1, 'date' => $date, 'reference' => $reference, 'description' => $description, 'direction' => $row['direction'], 'amount_minor' => $amount,
                'balance_minor' => $reportedBalance, 'counterparty_ref' => $counterparty, 'suggested_category' => $category, 'category_assurance' => 'rule_suggestion_not_verified'];
        }
        $expectedClosing = $opening !== null ? Values::add(Values::add($opening, $credits), -$debits) : null;
        if ($closing !== null && $expectedClosing !== null && $closing !== $expectedClosing) {
            $findings[] = ['code' => 'closing_balance_mismatch', 'row' => null, 'severity' => 'review'];
        }
        if ($opening === null || $closing === null) {
            $findings[] = ['code' => 'incomplete_balance_evidence', 'row' => null, 'severity' => 'information'];
        }
        if ($closing !== null && $balance !== null && $closing !== $balance && $expectedClosing === null) {
            $findings[] = ['code' => 'last_balance_mismatch', 'row' => null, 'severity' => 'review'];
        }
        $recurring = [];
        foreach ($sources as $source) {
            if (count($source['months']) >= 2) {
                $source['observed_months'] = array_keys($source['months']);
                unset($source['months']);
                $source['interpretation'] = 'Repeated counterparty activity, not verified salary, revenue or an obligation.';
                $recurring[] = $source;
            }
        }
        ksort($monthly);
        ksort($categories);
        return ['engine_version' => '1.0.0', 'currency' => $currency, 'period_start' => $start, 'period_end' => $end,
            'opening_balance_minor' => $opening, 'closing_balance_minor' => $closing, 'calculated_closing_minor' => $expectedClosing,
            'credits_minor' => $credits, 'debits_minor' => $debits, 'net_movement_minor' => Values::add($credits, -$debits), 'row_count' => count($rows),
            'transactions' => $rows, 'monthly_activity' => array_values($monthly), 'category_suggestions' => array_values($categories), 'recurring_candidates' => $recurring,
            'findings' => $findings, 'financial_checks' => count(array_filter($findings, fn (array $f): bool => $f['severity'] === 'review')) > 0 ? 'review_required' : (($opening === null || $closing === null) ? 'incomplete' : 'consistent'),
            'source_authenticity' => 'unconfirmed', 'account_ownership' => 'unconfirmed', 'verified_income_minor' => null, 'credit_decision_eligible' => false,
            'interpretation' => 'Consistency is not issuer authentication. Credits are not necessarily income. Missing months are not zero-income months. Suggested categories and repeated counterparties require review.'];
    }

    private function category(string $description): string
    {
        $rules = [
            'possible_loan_proceeds_or_repayment' => '/\b(loan|credit facility|advance|repayment)\b/i',
            'possible_own_transfer' => '/\b(own account|self transfer|internal transfer)\b/i',
            'possible_reversal' => '/\b(reversal|reversed|refund)\b/i',
            'possible_salary' => '/\b(salary|payroll|wages)\b/i',
            'possible_rent' => '/\b(rent|landlord)\b/i',
            'possible_utilities' => '/\b(electricity|water bill|utility|umeme|uedcl|nwsc)\b/i',
            'possible_education' => '/\b(school fees|tuition)\b/i',
            'possible_insurance' => '/\b(insurance|premium)\b/i',
            'possible_fees' => '/\b(fee|charge|commission)\b/i',
        ];
        foreach ($rules as $category => $pattern) {
            if (preg_match($pattern, $description)) {
                return $category;
            }
        }
        return 'unclassified';
    }
}
