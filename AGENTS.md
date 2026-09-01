# AGENTS.md

Guidance for agents and engineers working on the OpFin Laravel backend.

## Project context

OpFin is Uganda-first financial infrastructure. It owns product and customer state for credit, savings, protection, financial wellbeing, linked accounts, community and participatory finance, asset finance, partner distribution, consent, KYC/CRB workflows and compliance evidence. CPay is the only production boundary for external collections and payouts.

Correctness, authorization, auditability, reconciliation and recoverability outrank implementation speed.

## Non-negotiable financial invariants

- Store and move money as integer minor units. UGX currently uses minor-unit exponent `0`.
- Never introduce floats/doubles/decimal strings into new balances, fees, interest, premiums, limits, repayments or transfers.
- Every ledger transaction must contain at least two positive integer entries and total debits must equal total credits before commit.
- Balance is not enough. Every governed financial event must also have exactly its expected immutable accounting event.
- Corrections and reversals are append-only. Never delete or mutate historical financial entries merely to make totals agree.
- CPay provider success is external finality evidence, not permission to skip product-state validation or accounting.
- Refunds and reversals are terminal financial states. Do not normalize them to pending or silently convert them into failures.
- Every outbound idempotency key is bound to one canonical financial instruction. Exact replay is safe; same key with changed provider, direction, amount, currency, party, source or reference is an error.
- Reconciliation never manufactures balancing entries. Differences remain explicit exceptions.
- Production credit disbursement requires provider success, exact loan/schedule creation and the required immutable disbursement ledger posting in one controlled transition.
- Production repayment must consume the collected amount exactly according to the versioned allocation policy.
- Participatory commitments reserve funding capacity under a database lock. Settled + reserved funding may never exceed target.
- Asset finance must satisfy `0 <= deposit < asset price` and `0 < approved finance <= asset price - deposit`.
- Credit approval must apply the configured debt-service-ratio policy and retain the calculation evidence with the decision policy version.
- Legacy lending code is compatibility-only. New production credit must use production decision → offer → CPay finality → exact schedule → immutable ledger.

## Architecture and code rules

- Target Laravel 11 and PHP 8.2+.
- Keep controllers thin; put domain behavior in services/actions.
- Use dependency injection, explicit validation, transactions, row locks and database uniqueness constraints for financial state transitions.
- Do not trust client-supplied ownership, institution, role, status, rate, amount, callback result or provider reference.
- Use Sanctum for protected API authentication and centralized authorization for roles/permissions.
- Test cross-user and cross-institution denial.
- Financial state changes, product decisions, consent/KYC/CRB actions and administrative approvals must be audit logged.
- Never log secrets, OTPs, NINs, access tokens, private keys or unnecessary raw provider payloads.
- Do not hardcode production credentials, merchant identifiers, provider URLs or secrets.
- Do not fake live integrations. Missing production credentials must fail closed.

## Money movement

Production code must route collections and payouts through the governed CPay adapter. `mock` is local/test only. Airtel configuration remaining in OpFin is KYC-related, not a direct production money rail.

For every money-changing path, preserve this sequence:

```text
authenticated product instruction
→ canonical idempotent intent
→ step-up where required
→ CPay execution
→ verified provider finality
→ locked product-state transition
→ immutable accounting
→ provider/product reconciliation
→ integrity audit
```

Retries, callbacks and status repairs must be idempotent. Provider callbacks require signature validation, replay protection and terminal-state transition checks.

## Credit rules

Production offers currently support flat-interest terms because disclosures and schedules must be exactly reproducible. Pricing snapshots and disclosures are immutable once accepted.

Schedules must allocate integer totals exactly. If division leaves a remainder, allocate it deterministically to the final instalment. Never rely on floating-point equality for money.

The production affordability control is configured through `opfin.credit.max_debt_service_ratio_percent` and records:

```text
DSR = estimated_monthly_obligation_minor / verified_monthly_income_minor * 100
```

The input evidence and policy version remain required. A passing DSR does not make unverified external income data magically trustworthy.

## Savings, protection and partner-held funds

Collected cash is not the same as partner-confirmed custody. Keep collection, partner settlement, withdrawal release and payout as separate states and ledger events.

Never increase a customer savings balance from a merely pending or provider-collected contribution when partner confirmation is required by the product custody model.

## Database and migrations

- Do not edit deployed migrations; add forward-safe migrations.
- Use foreign keys, indexes and unique constraints as concurrency backstops.
- Financial references, provider references and idempotency keys require intentional uniqueness semantics.
- Use row locks for transitions where two concurrent actors could consume the same balance, limit, target or reservation.
- Avoid destructive data migrations without backup, restore and audit plans.
- Currency is part of financial identity. Do not reuse an account code across currencies unless the account model explicitly supports that partitioning.

## Testing expectations

Every financial change requires regression tests covering arithmetic and process invariants, not merely HTTP status codes. Include, where relevant:

- exact debit/credit totals;
- exactly-once posting on replay;
- changed-payload idempotency rejection;
- zero, boundary and remainder arithmetic;
- provider failure/refund/reversal;
- callback replay and invalid signature;
- concurrency/reservation boundaries;
- overpayment/overfunding rejection;
- missing expected ledger-event detection;
- mixed legacy/production portfolio aggregation;
- authorization and tenant isolation.

Minimum quality gates:

```bash
composer install
php artisan test
./vendor/bin/pint --test
composer audit
npm ci
npm run build
```

If a command cannot run, report the exact failure. Never call an unexecuted check “passed.”

## Source hygiene

- Remove obsolete imports, unreachable branches, dead compatibility shims and stale comments when their non-use is proven by repository search and tests.
- Do not delete historical compatibility code simply because it looks old. Quarantine it and document its boundary when production data may depend on it.
- Do not commit generated build artefacts, temporary files, debug dumps, backup copies or secrets.
- Keep demo code explicitly named and gated; never let demo/mock behavior leak into production routes.
- Root `README.md` and this file are current operational sources of truth. Historical audit documents must be clearly dated and must not override current production behavior.

## Commit and release discipline

- Keep commits focused and review changed files before merge.
- CI must be green before merge.
- A financial release is not complete until deployment is healthy and the relevant reconciliation/integrity jobs execute successfully in production.
- Never conceal an unresolved financial-integrity exception with a compensating entry created solely to silence a check.
