# Mobile Money Integration Adapter

## Status

The mobile money adapter layer supports local mock transactions and configured MTN/Airtel HTTP adapters. Live credentials must be configured and certified before production use.

Current providers:

- `mock`: local sandbox adapter for development and tests.
- `mtn`: MTN Mobile Money HTTP adapter that requires base URL, access token, and subscription key configuration.
- `airtel`: Airtel Money HTTP adapter that requires base URL, client ID, and access token configuration.

## Design Rules

- No real API keys in source control.
- No live provider calls until provider certification and audit review are complete.
- Every provider response is normalized into `MobileMoneyProviderResponse`.
- Money values use integer minor units through `amount_minor`.
- Disbursements, collections, status checks, reversals, webhooks, and failures are recorded in `mobile_money_transactions`.
- Payment events must not directly mutate loan balances.
- Ledger updates must happen only through the ledger/loan accounting service after an idempotent internal transaction has been accepted for processing.
- Duplicate webhooks must not create duplicate ledger entries.

## Main Classes

- `App\Contracts\MobileMoneyProviderInterface`
- `App\Services\MobileMoney\MobileMoneyService`
- `App\Services\MobileMoney\MobileMoneyProviderManager`
- `App\Services\MobileMoney\MobileMoneyProviderResponse`
- `App\Services\MobileMoney\WebhookSignatureValidator`
- `App\Services\MobileMoney\Adapters\MockMobileMoneyAdapter`
- `App\Services\MobileMoney\Adapters\MtnMobileMoneyAdapter`
- `App\Services\MobileMoney\Adapters\AirtelMoneyAdapter`
- `App\Models\MobileMoneyTransaction`

## Transaction Tracking

`mobile_money_transactions` stores:

- linked internal `transactions.id` where available
- provider
- direction: `disbursement`, `collection`, `reversal`
- integer `amount_minor`
- currency
- phone
- unique `idempotency_key`
- unique `internal_reference`
- provider reference
- status
- reconciliation status
- retry count and next retry time
- webhook event ID
- provider payload
- metadata

## Idempotency

Disbursements and collections require an `idempotency_key`.

If the same key is submitted again, the service returns the existing `MobileMoneyTransaction` and records a duplicate audit event instead of creating a new transaction.

Webhook processing uses `webhook_event_id` where the provider supplies one. Duplicate webhook events return the existing transaction and are audit logged as duplicates.

## Webhook Signature Validation

`WebhookSignatureValidator` checks HMAC SHA-256 signatures over the JSON payload.

Accepted signature header names:

- `X-Opfin-Mobile-Money-Signature`
- `x-opfin-mobile-money-signature`
- `X-Momo-Signature`
- `X-Airtel-Signature`

Provider secrets come from environment variables:

- `MOCK_MOBILE_MONEY_WEBHOOK_SECRET`
- `MTN_MOMO_WEBHOOK_SECRET`
- `AIRTEL_WEBHOOK_SECRET`

## Configuration

Set the default provider:

```env
MOBILE_MONEY_PROVIDER=mock
MOBILE_MONEY_CURRENCY=UGX
```

Mock local webhook secret:

```env
MOCK_MOBILE_MONEY_WEBHOOK_SECRET=
```

Set a local-only random value in an uncommitted `.env` file when testing mock webhooks. Do not commit real or reusable webhook secrets.

Live provider credentials:

```env
MTN_MOMO_ACCESS_TOKEN=
AIRTEL_ACCESS_TOKEN=
MTN_MOMO_WEBHOOK_SECRET=
AIRTEL_WEBHOOK_SECRET=
```

MTN and Airtel adapters fail before making requests if required configuration is missing.

## Usage Example

```php
$transaction = app(MobileMoneyService::class)->collect([
    'idempotency_key' => 'repayment-123',
    'amount_minor' => 50000,
    'currency' => 'UGX',
    'phone' => '256700000001',
    'description' => 'Loan repayment',
]);
```

## Audit Events

The adapter layer records audit logs for:

- `mobile_money.disbursement.requested`
- `mobile_money.disbursement.duplicate`
- `mobile_money.disbursement.provider_response`
- `mobile_money.collection.requested`
- `mobile_money.collection.duplicate`
- `mobile_money.collection.provider_response`
- `mobile_money.status_checked`
- `mobile_money.webhook.processed`
- `mobile_money.webhook.duplicate`
- `mobile_money.reversal.requested`
- `mobile_money.transaction.failed`

## Next Steps

1. Add first-class ledger service boundaries before wiring payment success into loan accounting.
2. Add provider certification fixtures for MTN and Airtel.
3. Add outbound HTTP clients only behind explicit sandbox/live configuration guards.
4. Add webhook controllers after endpoint-level auth, rate limiting, and replay protection are finalized.
5. Add reconciliation jobs that compare provider statements against `mobile_money_transactions`.
6. Add unique provider-reference constraints where provider behavior guarantees uniqueness.
