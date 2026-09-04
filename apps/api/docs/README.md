# OpFin Backend Documentation

This index distinguishes current operational specifications from dated audit and implementation history. The repository accumulated many checkpoints while the platform changed quickly; leaving them all equally authoritative would be a surprisingly efficient way to reintroduce old defects.

## Current sources of truth

Read these first for current production behavior:

1. `../README.md` — platform boundary, current formulas, accounting invariants, deployment configuration and production flow.
2. `../AGENTS.md` — engineering rules, release gates and non-negotiable financial invariants.
3. `production/financial-controls-review.md` — current money, ledger, idempotency, reversal, credit, P2P and asset-finance controls.
4. `integrations/mobile-money.md` — current CPay contract, callback, status normalization, idempotency and reconciliation behavior.
5. `api/frontend-backend-contract.md` — current client/server API contract where implemented endpoints are documented.

When a dated audit document conflicts with one of those files, the current source-of-truth documents win unless a newer code change has made them stale.

## Documentation classes

### `api/`

API and frontend/backend contracts. These are implementation-facing and should be kept synchronized with routes, validation rules, response schemas and authorization behavior.

### `architecture/`

Architecture decisions and structural records. ADR-style documents are historical decisions by design. Do not rewrite history; add a superseding decision when architecture changes materially.

### `audit/`

Point-in-time audits, checkpoints, gap lists and remediation plans. These are historical evidence, not automatically current product truth. Their filenames and internal dates should be respected when interpreting findings.

### `demo/`

Demo-only or investor-demo documentation. Demo arithmetic and mock provider behavior must never be treated as production financial rules.

### `integrations/`

External integration contracts. Production money movement must follow `integrations/mobile-money.md` and remain CPay-only unless the architecture is deliberately changed and recertified.

### `production/`

Operations, cutover, backup, incident, monitoring and readiness material. `production/financial-controls-review.md` is the current financial-control specification. Other dated plans should be interpreted in their stated timeframe and updated when they are active runbooks rather than historical records.

### `security/`

Security controls and security review material. Credentials and secrets must never be embedded in documentation.

### `uat/`

User-acceptance and release-test evidence. These documents prove what was tested at a point in time; they do not override current code or current production-control documentation.

## Documentation maintenance rule

Any pull request that changes a financial formula, state transition, money movement, accounting entry, provider contract, authentication/step-up rule, reconciliation behavior or production environment requirement must update the applicable current documentation in the same pull request.

Historical audits should normally be preserved rather than rewritten. If a historical finding is remediated, update or add a current remediation record and link it from the source-of-truth documentation instead of pretending the original audit never happened.
