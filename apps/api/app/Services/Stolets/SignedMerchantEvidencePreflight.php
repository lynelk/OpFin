<?php

namespace App\Services\Stolets;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use JsonException;

/**
 * Authenticates an exact raw Stolets evidence envelope and rejects replays.
 *
 * Deliberately NOT merchant-consent verification, underwriting or permission
 * to initiate a payment. The future production receiver must independently
 * verify live consent, space authority and lender/provider approvals.
 */
final class SignedMerchantEvidencePreflight
{
    public function __construct(private readonly Repository $cache) {}

    /** @return array<string, mixed> */
    public function inspect(string $rawBody, string $signature, string $sharedKey, ?DateTimeImmutable $now = null): array
    {
        if (strlen($rawBody) > 65536 || strlen($rawBody) < 2) {
            throw new InvalidArgumentException('Stolets evidence body exceeds permitted bounds.');
        }
        if (strlen($sharedKey) < 32) {
            throw new InvalidArgumentException('A separately provisioned integration key is required.');
        }
        if (preg_match('/^sha256=[0-9a-f]{64}$/D', $signature) !== 1 ||
            ! hash_equals('sha256='.hash_hmac('sha256', $rawBody, $sharedKey), $signature)) {
            throw new InvalidArgumentException('Stolets evidence signature is invalid.');
        }

        try {
            $packet = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Stolets evidence is not valid bounded JSON.');
        }
        if (! is_array($packet) || array_is_list($packet)) {
            throw new InvalidArgumentException('Stolets evidence must be a JSON object.');
        }

        $preflight = MerchantStockFinanceHandoff::inspect($packet, $now);
        $issuedAt = (new DateTimeImmutable($packet['issuedAt']))->getTimestamp();
        $clock = ($now ?? new DateTimeImmutable('now'))->getTimestamp();
        if ($issuedAt < $clock - 300) {
            throw new InvalidArgumentException('Stolets evidence signature is outside the five-minute replay window.');
        }

        $nonceKey = 'stolets:evidence:v1:'.hash('sha256', (string) $packet['tenantId'].'|'.(string) $packet['packetId']);
        if (! $this->cache->add($nonceKey, 1, 86400)) {
            throw new InvalidArgumentException('Stolets evidence packet was already inspected.');
        }

        return [
            ...$preflight,
            'signature_validated' => true,
            'consent_independently_verified' => false,
            'credit_or_payment_authorised' => false,
            'status' => 'awaiting_authoritative_consent_and_product_review',
        ];
    }
}
