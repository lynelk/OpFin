<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence\Layouts;

/**
 * Reads "date … description … amount … running balance" lines, the common shape of bank and
 * mobile-money exports. It has not been validated against any issuer's authorised samples, so
 * its output is always labelled unvalidated. It deliberately does not guess transaction
 * references, and leaves direction unresolved unless a sign, CR/DR marker or the balance chain
 * shows it.
 */
final class GenericRunningBalanceLayout implements StatementLayoutAdapter
{
    private const MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    private const DATE = '(?<date>\d{4}-\d{2}-\d{2}|\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{4}|\d{1,2}[ \-](?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*[ \-,]+\d{4})';

    private const NUMBER = '\(?-?\d{1,3}(?:,\d{3})+(?:\.\d{1,4})?\)?|\(?-?\d+(?:\.\d{1,4})?\)?';

    /** A value written like money (thousands separators or decimals), used to count rows that look transactional. */
    private const MONEY_LIKE = '/\d{1,3}(?:,\d{3})+|\d+\.\d{2}/';

    public function id(): string
    {
        return 'generic_running_balance';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function validatedFor(): array
    {
        return [];
    }

    public function extract(array $lines, int $minorUnitExponent): ?array
    {
        $opening = null;
        $closing = null;
        $rows = [];
        $candidates = 0;
        foreach ($lines as $index => $line) {
            if (preg_match('/^'.self::DATE.'(?:[ T]\d{1,2}:\d{2}(?::\d{2})?)?\s+(?<rest>.+)$/iu', $line, $match) === 1) {
                if (preg_match(self::MONEY_LIKE, $match['rest']) === 1) {
                    $candidates++;
                }
                $date = $this->date($match['date']);
                $row = $date === null ? null : $this->row($match['rest'], $minorUnitExponent);
                if ($row !== null) {
                    $rows[] = ['line' => $index + 1, 'date' => $date, ...$row];
                }

                continue;
            }
            if (preg_match('/\b(opening balance|balance brought forward|brought forward|balance b\/f|previous balance)\b/i', $line) === 1) {
                $opening ??= $this->trailingAmount($line, $minorUnitExponent);
            } elseif (preg_match('/\b(closing balance|balance carried forward|carried forward|balance c\/f|ending balance)\b/i', $line) === 1) {
                $closing = $this->trailingAmount($line, $minorUnitExponent) ?? $closing;
            }
        }
        if ($rows === []) {
            return null;
        }

        return ['opening_balance_minor' => $opening, 'closing_balance_minor' => $closing,
            'candidate_lines' => max($candidates, count($rows)), 'transactions' => $rows];
    }

    private function row(string $rest, int $exponent): ?array
    {
        $withBalance = '/^(?<description>.*?\S)\s+(?<amount>'.self::NUMBER.')(?:\s?(?<amark>CR|DR))?\s+(?<balance>'.self::NUMBER.')(?:\s?(?<bmark>CR|DR))?$/i';
        $markedOnly = '/^(?<description>.*?\S)\s+(?<amount>'.self::NUMBER.')\s?(?<amark>CR|DR)$/i';
        if (preg_match($withBalance, $rest, $match) === 1) {
            $balance = $this->minor($match['balance'], $exponent);
            if ($balance !== null && strtoupper($match['bmark'] ?? '') === 'DR') {
                $balance = -abs($balance);
            }
        } elseif (preg_match($markedOnly, $rest, $match) === 1) {
            $balance = null;
        } else {
            return null;
        }
        $amount = $this->minor($match['amount'], $exponent);
        if ($amount === null || $amount === 0 || (isset($match['balance']) && $match['balance'] !== '' && $balance === null)) {
            return null;
        }
        $direction = match (true) {
            strtoupper($match['amark'] ?? '') === 'CR' => 'credit',
            strtoupper($match['amark'] ?? '') === 'DR', $amount < 0 => 'debit',
            default => null,
        };

        return ['reference' => null, 'description' => mb_substr(trim($match['description']), 0, 500),
            'amount_minor' => abs($amount), 'direction' => $direction, 'balance_minor' => $balance];
    }

    private function trailingAmount(string $line, int $exponent): ?int
    {
        if (preg_match('/(?<amount>'.self::NUMBER.')(?:\s?(?<mark>CR|DR))?$/i', $line, $match) !== 1) {
            return null;
        }
        $amount = $this->minor($match['amount'], $exponent);

        return $amount !== null && strtoupper($match['mark'] ?? '') === 'DR' ? -abs($amount) : $amount;
    }

    /** Converts a displayed amount to integer minor units, refusing precision the currency cannot hold. */
    private function minor(string $token, int $exponent): ?int
    {
        $negative = str_starts_with($token, '(') || str_contains($token, '-');
        $digits = str_replace([',', '(', ')', '-'], '', $token);
        if (preg_match('/^(\d{1,15})(?:\.(\d{1,4}))?$/', $digits, $match) !== 1) {
            return null;
        }
        $fraction = $match[2] ?? '';
        if (strlen($fraction) > $exponent && trim(substr($fraction, $exponent), '0') !== '') {
            return null;
        }
        $value = (int) ($match[1].str_pad(substr($fraction, 0, $exponent), $exponent, '0'));

        return $negative ? -$value : $value;
    }

    /** Day-first numeric dates, as used by Ugandan issuers, plus ISO and month-name forms. */
    private function date(string $raw): ?string
    {
        $raw = strtolower(trim($raw));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $raw, $m) === 1) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[ \-]([a-z]{3})[a-z]*[ \-,]+(\d{4})$/', $raw, $m) === 1 && isset(self::MONTHS[$m[2]])) {
            [$day, $month, $year] = [(int) $m[1], self::MONTHS[$m[2]], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}
