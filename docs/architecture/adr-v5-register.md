# OpFin v5 Architecture Decision Register

**Status:** Accepted for implementation  
**Effective date:** 2026-08-26  
**Scope:** OpFin-FE, OpFin-BE, and the OpFin ↔ CPay integration boundary.

This register records the 20 architecture decisions required by the v5 build specification. Decisions are engineering architecture decisions, not legal or regulatory approvals.

## ADR-001 — OpFin vs CPay domain ownership

**Decision:** OpFin owns customer identity, consent, product logic, eligibility, underwriting, obligations, savings/protection/investment positions, customer journeys, support and workflow. CPay owns payment execution, provider adapters, payment callbacks, payment ledger, settlement, treasury, payment reconciliation and payment-network interoperability. OpFin must not implement a second payment gateway.

## ADR-002 — Cross-system payment orchestration

**Decision:** OpFin creates a business intent and calls CPay v2 using an idempotency key, correlation metadata and signed requests. CPay returns a payment reference/status. Final financial fulfilment in OpFin occurs only after a valid CPay callback or governed status reconciliation.

## ADR-003 — Dual-ledger boundary

**Decision:** CPay is authoritative for money-movement and settlement ledger entries. OpFin may keep domain subledgers for customer obligations, loan accounting, savings liabilities, protection liabilities and investment positions. Every OpFin posting caused by money movement must retain the CPay reference and must never mutate CPay ledger state directly.

## ADR-004 — Correlation and idempotency

**Decision:** Every money movement carries an immutable idempotency key plus internal reference, correlation ID and trace ID. Duplicate requests return the original transaction. Idempotency keys cannot be reused for a different financial intent.

## ADR-005 — Event delivery semantics

**Decision:** External callbacks and internal asynchronous events are treated as at-least-once delivery. Consumers must be idempotent. Signed callbacks use replay windows, unique event identifiers and terminal-state transition protection. Failures enter retry/exception workflows rather than silent mutation.

## ADR-006 — Product Factory versioning

**Decision:** Product definitions are versioned and effective-dated. Published versions are immutable. Changes create a new version and require maker-checker approval before activation. Existing contracts remain bound to their originating version.

## ADR-007 — Rules Engine governance

**Decision:** Rules are versioned, explainable and auditable. Draft, approval and activation are separate actions. Regulated or financially material rules require maker-checker approval. Evaluation records the rule version and result used for the decision.

## ADR-008 — Workflow Engine governance

**Decision:** Workflow definitions are versioned and approved before use. Runs are state-machine based, auditable and idempotent. Manual overrides require authorized roles, a reason and an audit event.

## ADR-009 — Financial Passport data model

**Decision:** Financial Passport stores verified attributes and domain summaries, not one opaque score. Identity, employment, income, CRB, repayment, savings, affordability and other permitted domains retain provenance, verification state, effective dates and consent scope.

## ADR-010 — Consent model

**Decision:** Consent is purpose-specific, versioned, time-stamped, revocable and auditable. A single canonical consent model is used across OpFin. Revocation stops future processing where legally applicable without deleting evidence needed for legal or audit retention.

## ADR-011 — Country Policy model

**Decision:** Jurisdiction-specific limits, disclosures, licence-dependent capabilities and regulatory rules are effective-dated configuration, not scattered conditionals. Production features must be gated by an active country policy.

## ADR-012 — AI provider abstraction

**Decision:** AI integrations sit behind a provider abstraction. AI output is advisory unless a separately approved policy explicitly authorizes automation. Regulated decisions require deterministic policy evidence, human review where required, explainability and auditability.

## ADR-013 — Customer timeline/event model

**Decision:** Customer timeline entries are append-oriented domain events derived from authoritative records. Events contain source, event type, reference, timestamp and visibility classification. Timeline presentation must not become a second system of record.

## ADR-014 — Security/device model

**Decision:** Authentication uses revocable sessions/tokens, strong credential rules, throttling and step-up controls for sensitive actions. Device/session security events are auditable. Privileged actions require RBAC and least privilege. Secrets never enter frontend bundles or source control.

## ADR-015 — ISO 20022 canonical message

**Decision:** ISO 20022 payment-message translation belongs to CPay. OpFin sends business/payment intents using the CPay contract and does not implement a competing ISO 20022 payment adapter. OpFin retains relevant message identifiers for traceability when returned by CPay.

## ADR-016 — ISO 8583 adapter boundary

**Decision:** ISO 8583 translation and switch connectivity belong to CPay. OpFin never handles PAN/switch field mapping merely to execute a payment. Any card/switch capability is exposed to OpFin through governed CPay APIs.

## ADR-017 — BIC directory strategy

**Decision:** BIC validation/routing is provided by CPay or a shared governed reference-data service owned by the payments layer. OpFin may cache read-only reference data but cannot become the authoritative BIC routing directory.

## ADR-018 — Reconciliation ownership

**Decision:** CPay reconciles provider, settlement and payment-ledger records. OpFin reconciles CPay outcomes against OpFin business obligations and domain subledgers. Exceptions must be visible to operations and corrected through governed adjustment workflows, never raw database edits.

## ADR-019 — Savings ledger model

**Decision:** OpFin owns the savings-domain liability/subledger and customer goal state; CPay executes deposits, withdrawals and payouts. Savings postings are immutable/double-entry where financially material and reference the originating CPay transaction.

## ADR-020 — Investment position model

**Decision:** OpFin owns investor suitability, orders, units/positions, valuation and product-level records. CPay executes cash legs. Investment positions are never inferred only from payment status; position changes require an approved investment-domain event linked to the corresponding CPay movement.

## Enforcement

- `MOBILE_MONEY_PROVIDER=cpay` is mandatory in production.
- Mock money movement is permitted only in local/testing environments.
- Direct MTN/Airtel/provider payment adapters are forbidden in OpFin.
- CI fails if retired direct-provider payment classes are reintroduced.
- Architecture changes affecting these decisions require a superseding ADR and review of the v5 release gates.
