<?php

declare(strict_types=1);

namespace App\Domain\Essentials;

use InvalidArgumentException;
use RuntimeException;

/** Deterministic financial rules. No provider calls, database access or side effects. */
final class AccountingRules
{
    public const ALLOCATION_VERSION = 'oldest_due_interest_fees_principal_v1';
    public const LIVE_COLLECTIONS = ['prepared', 'submitting', 'pending', 'confirmed_unapplied', 'exception'];
    public const ACTIVE_DEBT = ['active', 'overdue', 'fulfilment_reversal_review'];
    public const TERMINAL_ADVANCES = ['settled', 'fulfilment_failed', 'lender_funding_failed', 'reversed'];

    public static function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $right > PHP_INT_MAX - $left) {
            throw new InvalidArgumentException('Non-negative integer addition exceeded its supported range.');
        }
        return $left + $right;
    }

    public static function amount(mixed $value, bool $positive = false): int
    {
        if (! is_int($value) || $value < ($positive ? 1 : 0)) {
            throw new InvalidArgumentException('Money must be an integer in currency minor units.');
        }
        return $value;
    }

    /** A network timeout or elapsed time never releases a collection reservation. */
    public static function collectionCapacity(int $outstanding, array $reservations): int
    {
        self::amount($outstanding);
        $reserved = 0;
        $seen = [];
        foreach ($reservations as $row) {
            $id = (string) ($row['reference'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                throw new InvalidArgumentException('Collection reservations need distinct stable references.');
            }
            $seen[$id] = true;
            $status = $row['status'] ?? '';
            $amount = self::amount($row['amount_minor'] ?? null, true);
            if (in_array($status, ['applied', 'failed', 'reversed'], true)) {
                continue;
            }
            $reserved = self::add($reserved, $amount);
        }
        if ($reserved > $outstanding) {
            throw new RuntimeException('Pending collections exceed the outstanding obligation; reconciliation is required.');
        }
        return $outstanding - $reserved;
    }

    public static function assertCanCollect(int $amount, int $outstanding, array $reservations): void
    {
        self::amount($amount, true);
        if ($amount > self::collectionCapacity($outstanding, $reservations)) {
            throw new InvalidArgumentException('This amount exceeds the balance remaining after pending collections.');
        }
    }

    public static function allocate(array $rows, int $collected, string $policy = self::ALLOCATION_VERSION): array
    {
        self::amount($collected, true);
        $order = match ($policy) {
            'oldest_due_interest_fees_principal_v1' => ['interest', 'fees', 'principal'],
            'oldest_due_fees_interest_principal_v1' => ['fees', 'interest', 'principal'],
            default => throw new InvalidArgumentException('The accepted allocation policy is unsupported.'),
        };
        $rows = self::schedule($rows);
        $remaining = $collected;
        $totals = ['principal_minor' => 0, 'interest_minor' => 0, 'fees_minor' => 0];
        $allocations = [];
        foreach ($rows as &$row) {
            if ($remaining === 0) {
                break;
            }
            $allocation = ['schedule_item_id' => $row['id'], 'principal_minor' => 0, 'interest_minor' => 0, 'fees_minor' => 0];
            foreach ($order as $component) {
                $field = $component.'_outstanding_minor';
                $take = min($row[$field], $remaining);
                $row[$field] -= $take;
                $remaining -= $take;
                $allocation[$component.'_minor'] = $take;
                $totals[$component.'_minor'] = self::add($totals[$component.'_minor'], $take);
            }
            $row['total_outstanding_minor'] = self::sumComponents($row, '_outstanding_minor');
            if (self::sumComponents($allocation, '_minor') > 0) {
                $allocations[] = $allocation;
            }
        }
        unset($row);
        if ($remaining !== 0 || self::sumComponents($totals, '_minor') !== $collected) {
            throw new InvalidArgumentException('The collected amount cannot be allocated exactly to the outstanding schedule.');
        }
        return ['rows' => $rows, 'allocations' => $allocations] + $totals
            + ['total_minor' => $collected, 'policy' => $policy];
    }

    /** Restore original allocation evidence, not a newly calculated allocation. */
    public static function reverse(array $rows, array $allocations, int $collected): array
    {
        self::amount($collected, true);
        $rows = self::schedule($rows);
        $indexed = array_column($rows, null, 'id');
        $seen = [];
        $total = 0;
        foreach ($allocations as $allocation) {
            $id = $allocation['schedule_item_id'] ?? null;
            if (! is_int($id) || ! isset($indexed[$id]) || isset($seen[$id])) {
                throw new InvalidArgumentException('Reversal allocations must identify distinct original schedule items.');
            }
            $seen[$id] = true;
            foreach (['fees', 'interest', 'principal'] as $component) {
                $amount = self::amount($allocation[$component.'_minor'] ?? null);
                $field = $component.'_outstanding_minor';
                $restored = self::add($indexed[$id][$field], $amount);
                if ($restored > self::amount($indexed[$id][$component.'_original_minor'] ?? null)) {
                    throw new RuntimeException('A reversal would exceed the original disclosed schedule.');
                }
                $indexed[$id][$field] = $restored;
                $total = self::add($total, $amount);
            }
            $indexed[$id]['total_outstanding_minor'] = self::sumComponents($indexed[$id], '_outstanding_minor');
        }
        if ($total !== $collected) {
            throw new RuntimeException('Original repayment allocation evidence does not match the reversal amount.');
        }
        return array_values($indexed);
    }

    public static function exposure(array $advances, string $today): array
    {
        self::date($today);
        $reserved = 0;
        $debt = 0;
        $due = 0;
        $next = null;
        foreach ($advances as $advance) {
            $status = (string) ($advance['status'] ?? '');
            $principal = self::amount($advance['principal_minor'] ?? null);
            $outstanding = self::amount($advance['outstanding_minor'] ?? null);
            if (in_array($status, self::ACTIVE_DEBT, true)) {
                $sum = 0;
                foreach (self::schedule($advance['schedule'] ?? []) as $row) {
                    $sum = self::add($sum, $row['total_outstanding_minor']);
                    if ($row['total_outstanding_minor'] > 0) {
                        if ($row['due_date'] <= $today) {
                            $due = self::add($due, $row['total_outstanding_minor']);
                        }
                        $next = $next === null || $row['due_date'] < $next ? $row['due_date'] : $next;
                    }
                }
                $debt = self::add($debt, max($sum, $outstanding));
            } elseif (! in_array($status, self::TERMINAL_ADVANCES, true)) {
                $reserved = self::add($reserved, max($principal, $outstanding));
            } elseif ($status === 'settled' && $outstanding > 0) {
                $debt = self::add($debt, $outstanding);
            }
        }
        return ['reserved_minor' => $reserved, 'debt_minor' => $debt,
            'exposure_minor' => self::add($reserved, $debt), 'due_minor' => $due, 'next_due_date' => $next];
    }

    public static function providerState(string $purpose, string $status, bool $statusLookup = false): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($purpose, ['funding', 'fulfilment', 'collection', 'funding_release', 'refund'], true)) {
            throw new InvalidArgumentException('Unknown provider purpose.');
        }
        if ($purpose === 'funding_release') {
            if (in_array($status, ['RELEASED', 'REFUNDED', 'REVERSED'], true)) {
                return 'success';
            }
            if (! $statusLookup && in_array($status, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'], true)) {
                return 'success';
            }
            return in_array($status, ['FAILED', 'DECLINED', 'REJECTED'], true) ? 'exception' : 'pending';
        }
        if (in_array($status, ['REVERSED', 'REFUNDED', 'RELEASED'], true)) {
            return 'reversed';
        }
        if (in_array($status, ['FAILED', 'DECLINED', 'REJECTED', 'CANCELLED'], true)) {
            return 'failed';
        }
        $success = $purpose === 'funding'
            ? ['FUNDED', 'COMMITTED', 'AUTHORISED', 'AUTHORIZED']
            : ['SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED'];
        return in_array($status, $success, true) ? 'success' : 'pending';
    }

    public static function transition(string $old, string $observed): string
    {
        if (! in_array($observed, ['pending', 'success', 'failed', 'reversed', 'exception'], true)) {
            throw new InvalidArgumentException('Unknown provider observation.');
        }
        if ($old === 'exception') {
            return 'exception';
        }
        if ($old === $observed) {
            return $old;
        }
        if (in_array($old, ['success', 'failed', 'reversed'], true)) {
            if ($observed === 'pending') {
                return $old;
            }
            return $old === 'success' && $observed === 'reversed' ? 'reversed' : 'exception';
        }
        return $observed;
    }

    public static function canonical(array $value): string
    {
        $normalise = function (mixed $item) use (&$normalise): mixed {
            if (is_array($item)) {
                if (! array_is_list($item)) {
                    ksort($item, SORT_STRING);
                }
                foreach ($item as &$child) {
                    $child = $normalise($child);
                }
                unset($child);
                return $item;
            }
            if (is_float($item) || is_object($item) || is_resource($item)) {
                throw new InvalidArgumentException('Financial identity permits only exact JSON scalars and arrays.');
            }
            return $item;
        };
        return json_encode($normalise($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function schedule(array $rows): array
    {
        $seen = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            if (! is_int($id) || $id <= 0 || isset($seen[$id])) {
                throw new InvalidArgumentException('Schedule items require distinct positive integer identifiers.');
            }
            $seen[$id] = true;
            self::date($row['due_date'] ?? '');
            $sum = self::sumComponents($row, '_outstanding_minor');
            if ($sum !== self::amount($row['total_outstanding_minor'] ?? null)) {
                throw new RuntimeException('Schedule components do not match their total.');
            }
            foreach (['fees', 'interest', 'principal'] as $component) {
                if (array_key_exists($component.'_original_minor', $row)
                    && $row[$component.'_outstanding_minor'] > self::amount($row[$component.'_original_minor'])) {
                    throw new RuntimeException('Outstanding schedule component exceeds its original value.');
                }
            }
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']) ?: ($a['id'] <=> $b['id']));
        return $rows;
    }

    private static function sumComponents(array $row, string $suffix): int
    {
        return self::add(self::add(self::amount($row['fees'.$suffix] ?? null),
            self::amount($row['interest'.$suffix] ?? null)), self::amount($row['principal'.$suffix] ?? null));
    }

    private static function date(string $value): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('A real ISO calendar date is required.');
        }
    }
}
