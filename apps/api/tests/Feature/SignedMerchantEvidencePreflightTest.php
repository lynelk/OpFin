<?php

namespace Tests\Feature;

use App\Services\Stolets\SignedMerchantEvidencePreflight;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class SignedMerchantEvidencePreflightTest extends TestCase
{
    private function packet(): string
    {
        return json_encode([
            'contractVersion' => '1.0.0', 'sourceSystem' => 'stolets', 'recipientSystem' => 'opfin',
            'purpose' => 'stock_finance_preflight', 'packetId' => 'test-packet-1', 'tenantId' => 'test-tenant-1',
            'issuedAt' => '2026-10-17T12:00:00Z', 'expiresAt' => '2026-10-17T12:10:00Z',
            'consent' => [
                'grantId' => 'unverified-consent-1', 'recipientId' => 'opfin',
                'purpose' => 'stock_finance_preflight', 'status' => 'granted',
                'effectiveAt' => '2026-10-16T12:00:00Z', 'expiresAt' => '2026-10-18T12:00:00Z',
            ],
            'passport' => [
                'snapshotId' => 'test-snapshot-1', 'asOf' => '2026-10-17T11:00:00Z',
                'coverageStart' => '2026-09-17T11:00:00Z',
                'coverageEnd' => '2026-10-17T11:00:00Z', 'evidenceLevel' => 'recorded',
            ],
            'order' => [
                'purchaseOrderId' => 'test-purchase', 'supplierId' => 'test-supplier', 'currency' => 'UGX',
                'totalMinor' => 900000, 'merchantContributionMinor' => 300000, 'requestedFinanceMinor' => 600000,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function test_signed_preflight_is_replay_safe_but_never_approves_consent_or_credit(): void
    {
        Cache::clear();
        $payload = $this->packet();
        $key = str_repeat('s', 48);
        $signature = 'sha256='.hash_hmac('sha256', $payload, $key);
        $gateway = app(SignedMerchantEvidencePreflight::class);
        $result = $gateway->inspect($payload, $signature, $key, new DateTimeImmutable('2026-10-17T12:01:00Z'));
        $this->assertTrue($result['signature_validated']);
        $this->assertFalse($result['consent_independently_verified']);
        $this->assertFalse($result['credit_or_payment_authorised']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already inspected');
        $gateway->inspect($payload, $signature, $key, new DateTimeImmutable('2026-10-17T12:01:00Z'));
    }

    public function test_tampering_and_old_packets_fail_before_replay_state_is_consumed(): void
    {
        Cache::clear();
        $payload = $this->packet();
        $key = str_repeat('s', 48);
        $signature = 'sha256='.hash_hmac('sha256', $payload, $key);
        $gateway = app(SignedMerchantEvidencePreflight::class);
        try {
            $gateway->inspect(str_replace('900000', '900001', $payload), $signature, $key, new DateTimeImmutable('2026-10-17T12:01:00Z'));
            $this->fail('Tampered packet accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('signature', $exception->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('replay window');
        $gateway->inspect($payload, $signature, $key, new DateTimeImmutable('2026-10-17T12:08:00Z'));
    }
}
