<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use InvalidArgumentException;

/** Bounded CSV ingestion. Column mappings are explicit and form part of import evidence. */
final class CsvImportReader
{
    public const MAX_BYTES = 25_000_000;
    public const MAX_ROWS = 100000;

    public function rows(string $contents, array $mapping = []): array
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES || str_contains($contents, "\0") || preg_match('//u', $contents) !== 1) {
            throw new InvalidArgumentException('Upload a non-empty UTF-8 CSV no larger than 25 MB.');
        }
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new InvalidArgumentException('Unable to open the import buffer.');
        }
        try {
            fwrite($stream, $contents);
            rewind($stream);
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if ($headers === false || count($headers) > 100) {
                throw new InvalidArgumentException('The CSV needs a header with at most 100 columns.');
            }
            $headers = array_map(fn ($header): string => trim((string) $header), $headers);
            if (in_array('', $headers, true) || count(array_unique(array_map('strtolower', $headers))) !== count($headers)) {
                throw new InvalidArgumentException('CSV column headers must be non-empty and unique.');
            }
            foreach ($mapping as $target => $source) {
                if (! is_string($target) || ! is_string($source) || ! in_array($source, $headers, true)) {
                    throw new InvalidArgumentException('Every mapped column must name an existing CSV header.');
                }
            }
            $result = [];
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($values === [null] || count(array_filter($values, fn ($v): bool => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new InvalidArgumentException('A CSV row has a different number of columns from its header.');
                }
                $original = array_combine($headers, array_map(fn ($v): string => trim((string) $v), $values));
                if ($mapping === []) {
                    $record = $original;
                } else {
                    $record = [];
                    foreach ($mapping as $target => $source) {
                        $record[$target] = $original[$source];
                    }
                }
                $result[] = $record;
                if (count($result) > self::MAX_ROWS) {
                    throw new InvalidArgumentException('CSV row limit exceeded. Split the source into explicitly named populations.');
                }
            }
            return $result;
        } finally {
            fclose($stream);
        }
    }

    public function portfolio(string $loansCsv, ?string $scheduleCsv, array $manifest, array $mapping = []): array
    {
        return (new PortfolioEngine)->normalise($this->portfolioInput($loansCsv, $scheduleCsv, $manifest, $mapping));
    }

    public function portfolioInput(string $loansCsv, ?string $scheduleCsv, array $manifest, array $mapping = []): array
    {
        $loans = $this->rows($loansCsv, $mapping['loans'] ?? []);
        $schedules = [];
        $loanIds = [];
        foreach ($loans as &$loan) {
            foreach (['restructured', 'unlikely_to_pay', 'regulatory_npl'] as $flag) {
                if (array_key_exists($flag, $loan) && $loan[$flag] !== '') {
                    $loan[$flag] = $this->flag($loan[$flag]);
                } else {
                    unset($loan[$flag]);
                }
            }
            foreach (array_keys($loan) as $field) {
                if ($loan[$field] === '') {
                    unset($loan[$field]);
                }
            }
            $id = Values::text($loan['loan_ref'] ?? null, 'loan_ref');
            $loanIds['loan:'.$id] = true;
            $loan['instalments'] = $scheduleCsv === null ? null : [];
        }
        unset($loan);
        if ($scheduleCsv !== null) {
            foreach ($this->rows($scheduleCsv, $mapping['instalments'] ?? []) as $item) {
                $id = Values::text($item['loan_ref'] ?? null, 'loan_ref');
                if (! isset($loanIds['loan:'.$id])) {
                    throw new InvalidArgumentException('The schedule contains a loan absent from the supplied population.');
                }
                unset($item['loan_ref']);
                $schedules['loan:'.$id][] = $item;
            }
            foreach ($loans as &$loan) {
                $loan['instalments'] = $schedules['loan:'.$loan['loan_ref']] ?? [];
            }
            unset($loan);
        }
        $manifest['schema_version'] = PortfolioEngine::VERSION;
        $manifest['loans'] = $loans;

        return $manifest;
    }

    public function statement(string $csv, array $metadata, array $mapping = []): array
    {
        $rows = $this->rows($csv, $mapping);
        foreach ($rows as &$row) {
            foreach (array_keys($row) as $field) {
                if ($row[$field] === '') {
                    unset($row[$field]);
                }
            }
        }
        unset($row);
        $metadata['transactions'] = $rows;

        return (new StatementEngine)->analyse($metadata);
    }

    private function flag(string $value): bool
    {
        return match (strtolower($value)) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new InvalidArgumentException('CSV booleans must be true/false or 1/0.'),
        };
    }
}
