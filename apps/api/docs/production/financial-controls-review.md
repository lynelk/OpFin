# Financial Controls Review

Date: 2026-09-01
Status: Current production-control specification

## Summary

OpFin's current production financial architecture separates product authority from external money execution. OpFin owns product decisions, customer obligations, schedules, product state and immutable accounting; CPay owns external collection/payout execution and provider finality evidence.

This review supersedes the May 2026 control snapshot for current implementation behavior. Historical audit files remain useful as evidence of the remediation journey but must not override this document, the root `README.md`, or `AGENTS.md`.

## Monetary representation

New production financial paths use integer minor units. UGX currently uses exponent `0`, so one minor unit is one shilling. Production code must reject fractional minor-unit amounts rather than round them silently.

Legacy decimal/string fields remain for compatibility with historical records. New production credit does not originate through that legacy model.

## Production credit posting

A credit offer can become a loan only after provider-confirmed payout success. The controlled transition is:

```text
offer accepted
→ CPay payout
→ verified provider success
→ loan created
→ exact integer repayment schedule created
→ immutable disbursement accounting posted
→ offer/application marked disbursed
```

The state transition and required accounting are applied transactionally.

Financed-fee disbursement:

```text
Dr Loan receivable             principal
Cr Provider disbursement cash  principal
```

Deducted-fee disbursement:

```text
Dr Loan receivable             principal
Cr Provider disbursement cash  net cash paid
Cr Credit-fee clearing         deducted fees
```

The service requires:

```text
net cash paid + deducted fees = principal
```

A successful production payout that lacks its expected ledger reference is a critical financial-integrity finding.

## Repayment allocation and accounting

Production repayments are capped at current exact outstanding obligation and allocated oldest due first under policy `oldest-due-interest-fees-principal-v1`:

```text
interest → fees → principal
```

The allocation must consume the collection amount exactly. The resulting accounting is:

```text
Dr Provider collection cash  total collected
Cr Interest income            allocated interest
Cr Credit-fee clearing        allocated fees
Cr Loan receivable            allocated principal
```

Any unexplained remainder is an exception, not revenue and not an automatic balancing item.

## Reversals and refunds

CPay reversal/refund evidence normalizes to terminal `reversed` status.

For an unrepaid credit disbursement, OpFin creates a separate append-only reversal ledger transaction, voids the remaining schedule obligation, and marks the loan reversed. The original disbursement posting is preserved.

If repayment activity already exists, OpFin does not rewrite history automatically. The payment is marked as a reconciliation exception, the loan enters an exception state, and operations must resolve the economic history from provider and customer evidence.

## Idempotency

Every money instruction requires an idempotency key bound to the original canonical instruction. Exact replay returns the same transaction. Reuse with changed material data is rejected, including changes to provider, direction, amount, currency, phone, source identifiers or supplied internal reference.

Database uniqueness remains the final concurrency backstop.

## Affordability

Production operations approval calculates:

```text
DSR = estimated_monthly_obligation_minor / verified_monthly_income_minor * 100
```

The configured default maximum is `35%` through `OPFIN_MAX_DSR_PERCENT`.

The calculation, threshold, decision policy version and reason codes are retained in decision/audit evidence. Passing the formula does not replace the requirement to substantiate the underlying income and obligation inputs.

## Portfolio totals

Customer debt totals combine:

```text
production exact-schedule outstanding
+ legacy-schedule outstanding for non-production loans
```

Production loans are excluded from the legacy component to prevent double counting.

## Participatory finance

Funding capacity is reserved under a row lock while a valid commitment awaits step-up:

```text
unreserved capacity
= target amount
- settled funded amount
- active awaiting-step-up reservations
```

A new commitment cannot exceed unreserved capacity. Failed/reversed collections release their reservation. Settlement re-locks and revalidates the listing before increasing funded amount.

The integrity audit independently flags:

- funded amount different from settled commitments;
- funded amount above target;
- funded plus active reservations above target.

## Asset finance

The following identities are mandatory:

```text
0 <= deposit < asset price
maximum finance = asset price - deposit
0 < approved finance <= maximum finance
```

Deposit collection requires the approved exact deposit amount and fresh customer step-up before CPay execution.

## Savings and partner-held funds

Customer savings state distinguishes collection from custody confirmation:

```text
confirmed balance = confirmed contributions - paid withdrawals
available balance = confirmed balance - reserved withdrawals
```

A successful collection alone does not increase confirmed savings when partner confirmation is required by the custody model.

## Ledger controls

`ProductionLedgerService` enforces:

- at least two entries per transaction;
- positive integer entry amounts;
- valid debit/credit direction;
- exact debit/credit equality before commit.

Ledger account codes cannot be silently reused across different currencies.

## Reconciliation and integrity audit

Provider reconciliation compares amount, currency, direction, status and references. Missing records and mismatches remain visible exceptions.

`opfin:integrity-audit` tests both balance and completeness, including:

- unbalanced transactions;
- orphan ledger entries;
- duplicate immutable references;
- duplicate provider references;
- successful/unreconciled payments;
- missing expected production disbursement postings;
- missing required reversal postings;
- false long-range settlements;
- participatory funding/reservation mismatches;
- referral reward ledger mismatches;
- invalid asset-finance economics.

The control principle is deliberately strict: a perfectly balanced ledger can still be incomplete if an economic event is missing.

## Legacy boundary

Legacy loan origination is disabled in production by default through:

```text
OPFIN_ENABLE_LEGACY_LOAN_ORIGINATION=false
```

Legacy servicing code remains where historical data may depend on it, but it is compatibility code rather than the production source of truth. Its arithmetic has also been hardened against zero-installment terms, zero-rate amortization division by zero and fractional minor-unit postings.

## Release gate

A money-changing release is acceptable only when:

1. hermetic tests pass;
2. changed PHP files pass Pint;
3. dependency audit passes;
4. production money movement remains CPay-only;
5. deployment health checks pass;
6. provider finality/reconciliation jobs execute;
7. `opfin:integrity-audit` executes successfully after deployment;
8. any live provider activation still lacking genuine credentials or certification remains fail-closed.
