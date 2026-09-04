# Roadmap

## Phase 0: Baseline Control

Goal: make the current backend understandable and verifiable.

- Replace Laravel boilerplate README and metadata.
- Remove committed zip artifacts.
- Confirm CI can run Composer, PHPUnit, Pint, npm, and Vite build.
- Keep `AGENTS.md` and architecture docs current.
- Decide which previous security changes should be merged.

## Phase 1: Security and Authorization

Goal: make protected data and financial actions safe by default.

- Add policies for users, institutions, loan applications, loans, transactions, accounts, products, float topups, reports, and chats.
- Centralize roles and permissions.
- Add route-specific rate limits.
- Harden OTP storage and attempts.
- Add regression tests for direct object access.

## Phase 2: Financial Integrity

Goal: make money movement deterministic, auditable, and reversible.

- Introduce integer minor-unit money fields for new work.
- Define transaction and loan application state machines.
- Add idempotency keys for financial actions.
- Add database transactions around financial state changes.
- Add immutable ledger model.
- Add reversal/correction workflows.

## Phase 3: Provider Reliability

Goal: make mobile money and CRB/KYC integrations production-grade.

- Define provider interfaces.
- Split sandbox and production adapters.
- Verify callback authenticity.
- Persist provider callback events.
- Add reconciliation jobs and exception queues.
- Add provider metrics and alerts.

## Phase 4: Compliance Foundation

Goal: support responsible fintech operations.

- Add consent versioning.
- Add audit event tables and service.
- Add KYC/CRB purpose logging.
- Add data retention and anonymization workflows.
- Add compliance report generation and audit trails.

## Phase 5: Product Expansion

Goal: safely add new OpFin product lines.

- Savings goals/wallets.
- Investments.
- Insurance.
- Employer-linked benefits.
- Payroll deduction integrations.
- Financial wellness insights.

Each new product line must include authorization, consent, audit logging, ledger/reconciliation impact, tests, and compliance reporting from the first implementation slice.

## Phase 6: Scale and Operations

Goal: prepare for 1,000+ users and beyond.

- Add load tests.
- Monitor API latency, queue lag, failed jobs, provider errors, and reconciliation drift.
- Add backup/restore validation.
- Add incident response runbooks.
- Add operational dashboards.
- Add data quality checks for financial records.

