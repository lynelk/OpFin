<?php

namespace Tests\Unit;

use App\Services\Stolets\MerchantStockFinanceHandoff;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MerchantStockFinanceHandoffTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-10T12:00:00Z');
    }

    /** @return array<string, mixed> */
    private function packet(): array
    {
        return [
            'contractVersion' => '1.0.0',
            'sourceSystem' => 'stolets',
            'recipientSystem' => 'opfin',
            'purpose' => 'stock_finance_preflight',
            'packetId' => 'packet-1',
            'tenantId' => 'merchant-1',
            'issuedAt' => '2026-10-10T10:00:00Z',
            'expiresAt' => '2026-10-10T20:00:00Z',
            'consent' => [
                'grantId' => 'grant-1',
                'recipientId' => 'opfin-licensed-provider-1',
                'purpose' => 'stock_finance_preflight',
                'status' => 'granted',
                'effectiveAt' => '2026-10-09T12:00:00Z',
                'expiresAt' => '2026-10-11T10:00:00Z',
            ],
            'passport' => [
                'snapshotId' => 'snapshot-1',
                'asOf' => '2026-10-10T09:00:00Z',
                'coverageStart' => '2026-09-10T09:00:00Z',
                'coverageEnd' => '2026-10-09T09:00:00Z',
                'evidenceLevel' => 'recorded',
            ],
            'order' => [
                'purchaseOrderId' => 'purchase-1',
                'supplierId' => 'supplier-1',
                'currency' => 'UGX',
                'totalMinor' => 800000,
                'merchantContributionMinor' => 200000,
                'requestedFinanceMinor' => 600000,
            ],
        ];
    }

    public function test_preflight_returns_no_credit_or_payment_approval(): void
    {
        $result = MerchantStockFinanceHandoff::inspect($this->packet(), $this->now());
        $this->assertSame([
            'packetId' => 'packet-1',
            'tenantId' => 'merchant-1',
            'evidenceLevel' => 'recorded',
            'preflightOnly' => true,
        ], $result);
    }

    public function test_expired_or_long_lived_packet_is_rejected(): void
    {
        $expired = $this->packet();
        $expired['expiresAt'] = '2026-10-10T11:00:00Z';
        try {
            MerchantStockFinanceHandoff::inspect($expired, $this->now());
            $this->fail('Expired packet accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Packet validity', $exception->getMessage());
        }

        $long = $this->packet();
        $long['expiresAt'] = '2026-10-12T10:00:00Z';
        $long['consent']['expiresAt'] = '2026-10-13T10:00:00Z';
        $this->expectException(InvalidArgumentException::class);
        MerchantStockFinanceHandoff::inspect($long, $this->now());
    }

    public function test_consent_expiring_before_packet_is_rejected(): void
    {
        $packet = $this->packet();
        $packet['consent']['expiresAt'] = '2026-10-10T14:00:00Z';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('consent validity');
        MerchantStockFinanceHandoff::inspect($packet, $this->now());
    }

    public function test_untrusted_personal_fields_are_rejected(): void
    {
        $packet = $this->packet();
        $packet['customerPhone'] = '+256700000000';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unapproved fields');
        MerchantStockFinanceHandoff::inspect($packet, $this->now());
    }

    public function test_decimal_or_inconsistent_minor_unit_amount_is_rejected(): void
    {
        $decimal = $this->packet();
        $decimal['order']['requestedFinanceMinor'] = 600000.50;
        try {
            MerchantStockFinanceHandoff::inspect($decimal, $this->now());
            $this->fail('Decimal money accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('safe integer', $exception->getMessage());
        }

        $inconsistent = $this->packet();
        $inconsistent['order']['totalMinor'] = 900000;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order total');
        MerchantStockFinanceHandoff::inspect($inconsistent, $this->now());
    }

    public function test_stale_business_evidence_is_rejected(): void
    {
        $packet = $this->packet();
        $packet['passport']['asOf'] = '2026-08-10T09:00:00Z';
        $packet['passport']['coverageStart'] = '2026-07-10T09:00:00Z';
        $packet['passport']['coverageEnd'] = '2026-08-01T09:00:00Z';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('freshness');
        MerchantStockFinanceHandoff::inspect($packet, $this->now());
    }

    public function test_recorded_evidence_is_not_an_automatic_denial(): void
    {
        $packet = $this->packet();
        $this->assertSame(
            'recorded',
            MerchantStockFinanceHandoff::inspect($packet, $this->now())['evidenceLevel']
        );
    }
}
