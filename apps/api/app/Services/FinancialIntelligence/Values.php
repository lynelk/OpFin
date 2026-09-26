<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Exact, bounded values shared by imports, analysis and evidence fingerprints. */
final class Values
{
    // Keeps amount * 10,000 inside signed 64-bit arithmetic and JSON amounts exact.
    public const MAX_MINOR = 900_000_000_000_000;

    public static function amount(mixed $value, string $field, bool $signed = false): int
    {
        if (is_string($value) && preg_match('/^-?(0|[1-9][0-9]*)$/D', $value) === 1 && strlen(ltrim($value, '-')) <= 15) {
            $value = (int) $value;
        }
        if (! is_int($value) || abs($value) > self::MAX_MINOR || (! $signed && $value < 0)) {
            throw new InvalidArgumentException("{$field} must be a bounded integer in minor currency units.");
        }

        return $value;
    }

    public static function add(int $left, int $right): int
    {
        return self::amount($left + $right, 'Aggregate amount', true);
    }

    public static function bps(int $numerator, int $denominator): ?int
    {
        if ($denominator === 0) {
            return null;
        }
        self::amount($numerator, 'Ratio numerator', true);
        self::amount($denominator, 'Ratio denominator');

        return intdiv($numerator * 10000, $denominator);
    }

    public static function date(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$field} must be a YYYY-MM-DD date.");
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$field} must be a real YYYY-MM-DD date.");
        }

        return $value;
    }

    public static function days(string $from, string $to): int
    {
        return (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->format('%r%a');
    }

    public static function text(mixed $value, string $field, int $max = 120): string
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException("{$field} must be non-empty text without control characters (maximum {$max} bytes).");
        }

        return trim($value);
    }

    public static function flag(mixed $value, string $field): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException("{$field} must be true or false, not an inferred value.");
        }

        return $value;
    }

    public static function currency(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^[A-Z]{3}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Currency must be an uppercase three-letter code.');
        }

        return $value;
    }

    public static function canonical(mixed $value): string
    {
        $normalise = function (mixed $item) use (&$normalise): mixed {
            if (is_array($item)) {
                if (! array_is_list($item)) {
                    ksort($item, SORT_STRING);
                }
                return array_map($normalise, $item);
            }
            if (is_float($item) || is_object($item) || is_resource($item)) {
                throw new InvalidArgumentException('Evidence may contain only exact JSON values, not floating-point or runtime objects.');
            }
            return $item;
        };

        return json_encode($normalise($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function hash(mixed $value): string
    {
        return hash('sha256', self::canonical($value));
    }

    public static function keys(array $input, array $allowed, string $context): void
    {
        $unknown = array_diff(array_keys($input), $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException("{$context} contains unsupported fields. Remove personal/protected attributes and use the published schema.");
        }
    }

    public static function csvCell(mixed $value): string
    {
        $text = $value === null ? '' : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        // Includes whitespace-prefixed spreadsheet formula payloads.
        return preg_match('/^\s*[=+@\-]/u', $text) ? "'".$text : $text;
    }
}
