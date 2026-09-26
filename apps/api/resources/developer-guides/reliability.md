# Financial safety and provider independence

Audience: experienced developers, operations and integration architects.

## One financial authority

OpFin's domain services own customer/product state and the required accounting. CPay is the preferred payment route, not an excuse for disabling unrelated dashboards, records or planning when it is unavailable. Cito is the preferred gateway for configured third-party capabilities; the current gnuGrid direction remains Cito-mediated.

Provider independence is not duplicate execution. A timeout after submission can mean the provider acted but the response was lost. Preserve the original instruction, provider reference and pending state, then reconcile. Use a different provider only when policy and authoritative evidence establish that duplicate movement cannot result.

## Idempotency and finality

One logical economic instruction uses one payload-bound idempotency identity. Changing amount, currency, direction, beneficiary, lender, provider or source requires a new authorised instruction, not reuse of a previous key with changed meaning.

Keys and locations differ by endpoint. The Essentials repayment controller requires its key in the body; do not assume another endpoint's supported header automatically applies.

Test concurrent requests, not only sequential retries. Exactly-once effects require service transactions, appropriate locks and database uniqueness backstops. A successful provider response must still satisfy product-state validation, the expected immutable accounting event and reconciliation.

Corrections and reversals are append-only. Never create a balancing entry solely to silence a difference.

## Reuse evidence, not assumptions

The implemented Cito NIN-evidence path can reuse a fresh, attributable internal observation under an approved policy and active purpose-specific consent. Its source, verification time, expiry and environment remain part of the result. Reading a receipt does not renew its age.

This capability remains disabled until genuine policy intervals and approvals are configured. It does not create a direct NIRA contract, a population database, a complete KYC decision or a universal cache for every legacy identity route. A NIN result does not substitute for biometric, phone-ownership, sanctions or affordability checks.

A failed or expired revalidation must not become PASS simply to preserve availability. Only the dependent action should pause or enter review; unrelated internal work should remain usable.

## Failure and recovery tests

Exercise authentication expiry, revoked consent, wrong Financial Space, duplicate callbacks, changed-payload keys, two simultaneous requests, provider timeouts, explicit failures, reversals, delayed status, deleted customers and missing accounting events. Keep sandbox and production credentials/data separate.

The discovery interface does not repair outstanding Essentials ledger, collection, funding or authorisation defects. Current release findings remain relevant until the corresponding implementation and review evidence close them.
