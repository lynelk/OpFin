<?php

namespace App\Services\Stolets;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Untrusted, preflight-only Stolets stock-finance evidence contract v1.
 *
 * The result is not an authenticated partner instruction, a consent grant,
 * verified income, a credit decision or payment finality. Before accepting
 * evidence, the future server-side receiver must verify Stolets service
 * authentication, audience, anti-replay, authoritative grant/revocation,
 * business ownership, KYC, lender/product gates and underlying PO/passport.
 */
final class MerchantStockFinanceHandoff
{
    private const DAY_SECONDS = 86400;

    private const MAX_SAFE_INTEGER = 9007199254740991;

    /** @return array{packetId: string, tenantId: string, evidenceLevel: string, preflightOnly: true} */
    public static function inspect(array $packet, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        self::keys($packet, [
            'contractVersion', 'sourceSystem', 'recipientSystem', 'purpose',
            'packetId', 'tenantId', 'issuedAt', 'expiresAt',
            'consent', 'passport', 'order',
        ], 'packet');

        if (($packet['contractVersion'] ?? null) !== '1.0.0' ||
            ($packet['sourceSystem'] ?? null) !== 'stolets' ||
            ($packet['recipientSystem'] ?? null) !== 'opfin' ||
            ($packet['purpose'] ?? null) !== 'stock_finance_preflight') {
            throw new InvalidArgumentException('Unsupported integration contract, origin, recipient or purpose.');
        }

        $packetId = self::id($packet['packetId'] ?? null, 'packetId');
        $tenantId = self::id($packet['tenantId'] ?? null, 'tenantId');
        $issued = self::time($packet['issuedAt'] ?? null, 'issuedAt');
        $expiry = self::time($packet['expiresAt'] ?? null, 'expiresAt');
        if ($issued > $now->getTimestamp() || $expiry <= $now->getTimestamp() ||
            $expiry <= $issued || $expiry - $issued > self::DAY_SECONDS) {
            throw new InvalidArgumentException('Packet validity must be non-future, unexpired and at most 24 hours.');
        }

        $consent = self::object($packet['consent'] ?? null, 'consent');
        self::keys($consent, [
            'grantId', 'recipientId', 'purpose', 'status', 'effectiveAt', 'expiresAt',
        ], 'consent');
        self::id($consent['grantId'] ?? null, 'consent.grantId');
        self::id($consent['recipientId'] ?? null, 'consent.recipientId');
        if (($consent['status'] ?? null) !== 'granted' ||
            ($consent['purpose'] ?? null) !== 'stock_finance_preflight') {
            throw new InvalidArgumentException('Purpose-matched consent is required.');
        }
        if (self::time($consent['effectiveAt'] ?? null, 'consent.effectiveAt') > $issued ||
            self::time($consent['expiresAt'] ?? null, 'consent.expiresAt') < $expiry) {
            throw new InvalidArgumentException('Packet must be within consent validity.');
        }

        $passport = self::object($packet['passport'] ?? null, 'passport');
        self::keys($passport, [
            'snapshotId', 'asOf', 'coverageStart', 'coverageEnd', 'evidenceLevel',
        ], 'passport');
        self::id($passport['snapshotId'] ?? null, 'passport.snapshotId');
        $asOf = self::time($passport['asOf'] ?? null, 'passport.asOf');
        $start = self::time($passport['coverageStart'] ?? null, 'passport.coverageStart');
        $end = self::time($passport['coverageEnd'] ?? null, 'passport.coverageEnd');
        if ($start > $end || $end > $asOf || $asOf > $issued ||
            $issued - $asOf > 30 * self::DAY_SECONDS) {
            throw new InvalidArgumentException('Passport evidence coverage or freshness is invalid.');
        }
        $level = $passport['evidenceLevel'] ?? null;
        if (! in_array($level, ['recorded', 'reconciled', 'externally_corroborated'], true)) {
            throw new InvalidArgumentException('Unknown evidence level.');
        }

        $order = self::object($packet['order'] ?? null, 'order');
        self::keys($order, [
            'purchaseOrderId', 'supplierId', 'currency',
            'totalMinor', 'merchantContributionMinor', 'requestedFinanceMinor',
        ], 'order');
        self::id($order['purchaseOrderId'] ?? null, 'order.purchaseOrderId');
        self::id($order['supplierId'] ?? null, 'order.supplierId');
        if (($order['currency'] ?? null) !== 'UGX') {
            throw new InvalidArgumentException('Only UGX is supported in contract v1.');
        }
        $total = self::minor($order['totalMinor'] ?? null, 'order.totalMinor', true);
        $contribution = self::minor($order['merchantContributionMinor'] ?? null, 'order.merchantContributionMinor');
        $finance = self::minor($order['requestedFinanceMinor'] ?? null, 'order.requestedFinanceMinor', true);
        if ($contribution > self::MAX_SAFE_INTEGER - $finance ||
            $total !== $contribution + $finance) {
            throw new InvalidArgumentException('Order total must equal contribution plus requested finance.');
        }

        return [
            'packetId' => $packetId,
            'tenantId' => $tenantId,
            'evidenceLevel' => $level,
            'preflightOnly' => true,
        ];
    }

    /** @return array<string, mixed> */
    private static function object(mixed $value, string $name): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException($name.' must be an object.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload
     *  @param list<string> $allowed
     */
    private static function keys(array $payload, array $allowed, string $name): void
    {
        $extra = array_diff(array_keys($payload), $allowed);
        if ($extra !== []) {
            throw new InvalidArgumentException($name.' contains unapproved fields: '.implode(', ', $extra).'.');
        }
    }

    private static function id(mixed $value, string $name): string
    {
        if (! is_string($value) || strlen($value) < 1 || strlen($value) > 128 ||
            preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1) {
            throw new InvalidArgumentException($name.' must be a non-empty opaque reference.');
        }

        return $value;
    }

    private static function time(mixed $value, string $name): int
    {
        if (! is_string($value) ||
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/D', $value) !== 1) {
            throw new InvalidArgumentException($name.' must be a UTC ISO 8601 timestamp.');
        }
        try {
            return (new DateTimeImmutable($value))->getTimestamp();
        } catch (\Exception) {
            throw new InvalidArgumentException($name.' must be a valid UTC ISO 8601 timestamp.');
        }
    }

    private static function minor(mixed $value, string $name, bool $positive = false): int
    {
        if (! is_int($value) || $value < ($positive ? 1 : 0) ||
            $value > self::MAX_SAFE_INTEGER) {
            throw new InvalidArgumentException($name.' must be a safe integer in UGX minor units.');
        }

        return $value;
    }
}
