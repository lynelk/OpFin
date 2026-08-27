# OpFin Money-Movement Integration

## Status

CPay v2 is the **only production money-movement boundary for OpFin**.

Supported runtime adapters:

- `cpay`: production and certified sandbox collection/payout/status boundary.
- `mock`: local development and automated-test adapter only.

Direct MTN/Airtel payment and payout adapters were removed from the production codebase. Airtel configuration that remains in OpFin is KYC-only and must not be used for money movement.

## Architecture boundary

OpFin owns customer journeys, product decisions, obligations, schedules, product ledgers and financial-wellbeing state. CPay owns external payment execution and provider-side payment/reconciliation evidence. A provider event must terminate at CPay first and enter OpFin only through the governed CPay callback boundary.

Production code must fail closed if an attempt is made to route money movement outside CPay.

## Main classes

- `App\Contracts\MobileMoneyProviderInterface`
- `App\Services\MobileMoney\MobileMoneyService`
- `App\Services\MobileMoney\MobileMoneyProviderManager`
- `App\Services\MobileMoney\MobileMoneyProviderResponse`
- `App\Services\MobileMoney\WebhookSignatureValidator`
- `App\Services\MobileMoney\Adapters\CpayV2Adapter`
- `App\Services\MobileMoney\Adapters\MockMobileMoneyAdapter`
- `App\Models\MobileMoneyTransaction`

## Outbound CPay contract

Every collection or payout requires a unique OpFin `idempotency_key` and an integer `amount_minor` greater than zero. The CPay adapter propagates the v5 cross-system context:

- correlation ID
- trace ID
- product reference
- customer reference
- purpose
- country
- preferred channel when configured
- callback event route
- OpFin transaction ID
- currency and amount
- merchant/payment reference

CPay requests are RSA-signed using the configured merchant private key and carry a fresh nonce and timestamp. `X-CPay-Idempotency-Key` is sent for state-changing payment instructions.

## Required production configuration

Production must use a dedicated managed PostgreSQL database and CPay credentials issued for the OpFin merchant/integration.

Required money-movement settings include:

```env
MOBILE_MONEY_PROVIDER=cpay
CPAY_BASE_URL=
CPAY_MERCHANT_NUMBER=
CPAY_MERCHANT_ID=
CPAY_PRIVATE_KEY=
CPAY_CALLBACK_URL=
CPAY_CALLBACK_SECRET=
CPAY_CALLBACK_REPLAY_WINDOW_SECONDS=300
CPAY_ENVIRONMENT=production
CPAY_COUNTRY=UG
CPAY_CURRENCY=UGX
```

Never commit live keys or callback secrets. Production deployment must fail its release gate if mandatory CPay identity/signing values are absent.

## Transaction tracking and idempotency

`mobile_money_transactions` records:

- linked internal transaction, credit offer and/or loan where applicable
- user and institution scope
- provider and direction
- integer amount and currency
- phone/MSISDN
- unique `idempotency_key`
- unique `internal_reference`
- provider reference
- normalized payment status
- reconciliation status
- retry metadata
- unique webhook event ID when supplied
- provider payload and cross-system metadata

If the same outbound idempotency key is submitted again, OpFin returns the existing transaction rather than creating another money instruction.

## CPay callback verification

The only live provider callback endpoint is:

```text
POST /api/webhooks/cpay
```

`callback-v1` verification uses the **raw request body** and requires:

- `X-CPay-Signature-Version: callback-v1`
- `X-CPay-Signature`
- `X-CPay-Timestamp`
- `X-CPay-Nonce`
- `X-CPay-Callback-Task-Id`
- `X-CPay-Merchant-Id`
- `X-CPay-Reference`

The canonical HMAC input is:

```text
CALLBACK_TASK_ID
MERCHANT_ID
REFERENCE
TIMESTAMP
NONCE
RAW_REQUEST_BODY
```

OpFin rejects unsupported signature versions, invalid HMACs, mismatched merchant IDs, stale timestamps, reused delivery nonces and terminal-state regressions. Consumed CPay nonces are persisted in `cpay_webhook_nonces` under a merchant+nonce uniqueness constraint for replay protection.

CPay re-signs legitimate retry deliveries with a fresh nonce. When the payload carries an already-processed `event_id`, OpFin returns the existing transaction and audit-logs the duplicate rather than applying the event twice.

## Product-state safety

Payment events do not directly rewrite product obligations. After a verified CPay transaction is normalized, product services apply their own idempotent state transitions:

- credit-offer disbursement sync
- production repayment allocation
- savings movement sync
- protection premium sync

Terminal payment states cannot regress to a different terminal state through a later callback.

## Reconciliation

CPay and OpFin maintain separate accounting responsibilities. OpFin reconciliation compares external payment evidence against `mobile_money_transactions` and the relevant product obligation without using reconciliation to rewrite business truth.

Provider statement ingestion is immutable and business-date scoped. Exceptions include missing records, amount/currency/direction mismatches, duplicate references and status discrepancies.

## Audit events

The payment boundary records events including:

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

## Production release checks

Before enabling real money movement, verify all of the following with evidence:

1. Managed PostgreSQL is reachable and migrations are current.
2. `MOBILE_MONEY_PROVIDER=cpay`; mock mode is disabled.
3. CPay merchant number, merchant ID, private key, callback URL and callback secret are configured through the secret manager/environment, not source control.
4. Sandbox collect, payout, status and callback certification has passed with the real OpFin merchant credentials.
5. Callback invalid-signature, stale-timestamp, reused-nonce, duplicate-event and terminal-regression tests pass.
6. Reconciliation evidence closes payment, product obligation and provider statement records without unexplained differences.
7. Monitoring, alerting, backup/restore, incident runbook and rollback gates are signed off before production go-live.
