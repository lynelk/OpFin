# OpFin Money-Movement Integration

## Production boundary

CPay v2 is the only production money-movement boundary for OpFin. `mock` exists only for local development and automated tests. Direct MTN/Airtel collection or payout adapters are not valid production paths.

OpFin owns customer/product decisions, obligations, schedules, product ledgers and customer financial state. CPay owns external execution and provider-side finality evidence. Provider success must be applied by the relevant product service before OpFin treats the product outcome as settled.

## Canonical idempotency

Every collection or payout requires a unique `idempotency_key`, positive integer `amount_minor`, currency and phone/MSISDN.

An idempotency key is permanently bound to the original financial instruction. A replay is accepted only when the stored and requested instruction agree on the material fields supplied by the caller, including:

- provider;
- direction;
- amount;
- currency;
- phone;
- linked transaction, credit offer, loan, user and institution IDs;
- internal reference when supplied;
- purpose and source identifiers when supplied.

Exact replay returns the existing transaction. Reuse of the same key for a different instruction raises a hard conflict. A unique database constraint remains the final concurrency backstop.

## Outbound CPay contract

CPay requests include the merchant identity, amount, currency, party, merchant reference, callback URL and cross-system metadata such as correlation ID, trace ID, product/customer references and purpose. State-changing requests send `X-CPay-Idempotency-Key`.

Requests are RSA-signed with a fresh nonce and UTC timestamp. No live private key or callback secret may be committed.

Required production settings include:

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
CPAY_MINOR_UNIT_EXPONENT=0
```

## Status normalization

CPay statuses are normalized into OpFin's finite state model:

```text
SUCCESSFUL | SUCCEEDED | COMPLETED | SUCCESS -> successful
FAILED | REJECTED | CANCELLED | CANCELED     -> failed
REVERSED | REFUNDED and certified equivalents -> reversed
other/undetermined                            -> pending
```

Refund/reversal webhook event types also normalize to `reversed`; they are never downgraded to `pending`.

CPay terminal state regressions are rejected. A later webhook cannot silently rewrite one terminal state into another.

## Credit disbursement finality and accounting

A production credit loan is not created from request acceptance. The sequence is:

```text
offer accepted
→ CPay payout requested
→ provider success verified
→ loan and exact schedule created
→ immutable disbursement ledger posted
→ offer/application marked disbursed
```

These steps are applied transactionally after provider success. Replaying the successful event repairs a missing expected ledger posting idempotently instead of creating a second loan or posting.

For deducted fees:

```text
Dr Loan receivable             approved principal
Cr Provider disbursement cash  net cash paid
Cr Credit-fee clearing         deducted fees
```

The system enforces `net cash paid + deducted fees = principal`.

A provider-confirmed reversal of an unrepaid disbursement creates a separate append-only reversal transaction, zeroes the customer obligation by voiding the unrepaid schedule and marks the loan reversed. It never deletes the original posting. If repayment activity already exists, automatic reversal is blocked, the loan enters `Exception`, and operations must resolve the economic history from provider and customer evidence.

## Callback verification

The live callback endpoint is:

```text
POST /api/webhooks/cpay
```

`callback-v1` verification uses the raw request body and requires the CPay signature version, signature, timestamp, nonce, callback task ID, merchant ID and reference headers. OpFin validates HMAC, merchant identity, replay window and nonce uniqueness. Legitimate CPay retries must use a fresh nonce. Duplicate provider event IDs return the already-processed transaction without reapplying product state.

## Product-state safety

Verified CPay records are passed to idempotent product services:

- production credit disbursement sync;
- repayment allocation;
- savings movement sync;
- protection premium sync;
- governed long-range financial-intent settlement.

Money movement and product settlement remain separate states by design.

## Reconciliation

Provider statements are ingested by business date and compared with OpFin records on reference, amount, currency, direction and normalized status. Missing records, duplicate references, amount/currency/direction mismatches and status discrepancies remain explicit exceptions.

Reconciliation is evidence comparison. It must never create a balancing financial entry merely to make totals agree.

## Continuous integrity controls

`opfin:integrity-audit` complements transaction-level balancing by testing event completeness. Among other checks it raises critical findings when:

- a successful production credit disbursement lacks its expected immutable ledger posting;
- a provider-reversed production disbursement lacks its required reversal posting, unless it is already held in an operations exception state;
- a long-range financial intent is settled without provider success;
- duplicate provider references exist;
- participatory funding is overfunded or over-reserved.

A balanced set of existing entries is therefore not sufficient evidence of complete accounting.

## Production certification gate

Before real customer funds move, retain evidence that:

1. managed PostgreSQL is current and recoverable;
2. mock/direct-provider money paths are disabled in production;
3. genuine CPay merchant/signing/callback credentials are configured in secrets management;
4. signed collect, payout, callback, lookup and reconciliation certification passes;
5. duplicate request, mismatched idempotency replay, stale callback, duplicate event, terminal regression, refund/reversal and reconciliation exception tests pass;
6. successful disbursement and repayment events produce the expected product state and balanced immutable accounting exactly once;
7. monitoring, integrity audit, incident response and restore procedures are operational.
