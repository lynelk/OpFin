# OpFin Financial Core Architecture

Status: Controlled internal architecture standard  
Updated: 24 September 2026  
Language: English (United Kingdom)

## Objective

Keep OpFin agile without weakening financial truth. The preferred architecture is a **modular monolith with explicit bounded services**, not premature financial microservices. Money, accounting and reconciliation remain transactionally close while provider adapters, queues and read models can scale independently.

## Core bounded contexts

| Context | Authoritative responsibility |
| --- | --- |
| Credit policy | product/term availability, affordability, pricing policy, offers and disclosures |
| Funding | approved capital mandates, reservation, deployment, release and reversal |
| Payments | durable money intent, provider submission, provider finality and idempotency |
| Loan subledger | exact contractual schedule and repayment allocation |
| General ledger | immutable balanced postings and append-only reversals |
| Reconciliation | provider statement evidence, exceptions and bank settlement |
| Revenue/tax | governed revenue ownership, partner share, tax and EFRIS evidence |
| Savings/protection | customer instruction, partner custody/settlement and provider finality |
| Regulatory controls | effective-dated policies, maker-checker exceptions, reporting and evidence |
| Financial integrity | cross-context reconciliation, alerting and release/readiness evidence |

Controllers remain thin. They authenticate, validate request shape and translate domain exceptions to API responses. Financial rules live in services and are rechecked at the authoritative mutation boundary.

## Non-negotiable invariants

1. Money is integer minor units with an explicit ISO currency.
2. A provider acknowledgement is not accounting finality.
3. A provider statement match is not inferred from internal state.
4. Every provider request starts from a **durably committed local intent** carrying a canonical idempotency key and reference.
5. An ambiguous provider outcome is preserved and reconciled. It is never blindly retried.
6. Production credit requires an active product and active term at application/offer/acceptance boundaries.
7. Production credit requires approved funding provenance before disbursement.
8. Wallet ownership/verification is checked before an offer or collection mutates financial state.
9. Ledger postings are immutable, balanced and append-only for corrections/reversals.
10. Reconciliation can become `matched` only from provider evidence. Manual support actions cannot force a match.
11. Reconciliation write-off requires maker-checker approval and does not convert the underlying provider statement to `matched`.
12. Missing regulatory, provider or tax facts fail closed. They are not replaced with defaults that imply approval.
13. Financial release readiness requires actual integrity evidence, not merely healthy containers or successful deployment.

## Money movement state boundary

```text
authenticated instruction
→ ownership / policy / wallet validation
→ persist canonical money intent
→ commit local transaction
→ submit to provider with same idempotency key/reference
→ provider pending / successful / failed / reversed / ambiguous
→ product state + immutable accounting after verified finality
→ provider-statement reconciliation
→ provider/bank settlement evidence where applicable
```

The external HTTP request does not run inside the database transaction that creates the canonical payment intent.

## Credit state boundary

```text
active product + active term
→ KYC / consent / CRB / affordability
→ approved decision
→ exact governed economics
→ immutable offer + disclosure snapshot
→ recheck product/term
→ verified payout target
→ approved funding reservation
→ durable disbursement intent
→ provider finality
→ loan + exact schedule + ledger posting
→ reconciliation + reporting
```

Pausing or retiring a term therefore blocks new offers/acceptance without rewriting already-finalised historical contracts.

## Reconciliation governance

`matched` means system and provider evidence agree on reference, amount, currency, direction and status. Support staff may annotate exceptions but cannot set `matched` or `written_off`.

Write-off is an exception disposition, not fabricated evidence:

```text
exception
→ write-off request + reason + evidence hash
→ independent checker approval
→ controlled application
→ item written_off
→ underlying statement/reconciliation state remains exception
→ audit/integrity evidence retained
```

## Scalability model

- API nodes remain stateless and horizontally scalable.
- Worker/scheduler processes share the database-backed authoritative state and distributed locks.
- Provider calls use stable idempotency and durable intents, allowing safe asynchronous processing later without changing financial semantics.
- Reporting and dashboards are read-side workloads and may move to replicas/materialised views without moving the authoritative ledger.
- Bounded services are dependency-injected and testable independently.
- A future service extraction must preserve the same invariants and introduce an outbox/inbox boundary before crossing database ownership.

## Agility rules

- Prefer one reusable authoritative service over repeated controller/query logic.
- Add a new provider behind an adapter; do not fork product logic by provider.
- Add a new financial product through policy/configuration and bounded services; do not duplicate the ledger.
- Keep external activation facts in configuration/readiness gates, not hard-coded optimistic assumptions.
- Each material financial change includes regression tests, API/operations documentation and an independent review.

## Readiness surfaces

`/api/health/ready` answers whether the software runtime can serve requests.

`/api/health/financial-ready` answers whether the environment is safe for production-equivalent financial operations/UAT. It checks required integrations, verified funding policy, regulated disclosures, tax/EFRIS determination and the latest financial-integrity evidence.

Use:

```bash
php artisan opfin:financial-readiness
php artisan opfin:financial-readiness --json
```

A financial candidate is not accepted while this command fails.
