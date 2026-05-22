# Blockers and Risks

Date: 2026-05-22

## Critical: blocks production

| Risk | Evidence | Required action |
| --- | --- | --- |
| Tests, builds, and migrations could not be run | PHP, Composer, Pint, and npm are unavailable in this shell. | Restore toolchain and run backend/frontend verification before any readiness claim. |
| Production KYC lifecycle is incomplete | KYC is represented mainly by NIN fields/validation and demo dashboard status. | Implement provider-backed KYC workflow with evidence, review, retry, expiry, and audit. |
| Production consent management is missing | Current consent flow is investor-demo scoped. | Implement real consent records, versions, revocation effects, lawful basis, and reporting. |
| Decisioning is mock/demo | Investor-demo affordability and decisions are deterministic mock rules. | Implement governed credit policy, CRB integration, affordability engine, manual review, and reason-code controls. |
| Live mobile money is not production-safe in the new adapter layer | Provider-agnostic layer has mock adapter and placeholder MTN/Airtel adapters. Production boot now blocks `MOBILE_MONEY_PROVIDER=mock`. | Complete provider adapters, webhook validation, reconciliation, reversals, retries, and operational controls in sandbox and production certification. |
| Ledger integrity is not production-grade | Legacy financial tables use decimal/string money fields and ledger balancing is not globally enforced. | Convert money to integer minor units, enforce balanced immutable postings, and test all financial transitions. |
| Cutover/migration plan is missing | No validated live data migration, reconciliation, rollback, or parallel-run plan. | Produce and rehearse migration plan with balances, active loans, schedules, customers, provider refs, and audit evidence. |
| Compliance reporting is missing | No production reports for regulator, CRB, consent, KYC, ledger, or settlement. | Define and implement required reports before replacement. |
| Customer-support console is missing | Admin screens are legacy/demo/placeholder focused. | Build support workflows for customer lookup, cases, payment exceptions, KYC issues, and loan operations. |

## High: must fix before cutover

| Risk | Evidence | Required action |
| --- | --- | --- |
| RBAC coverage is incomplete | Some checks are route middleware, some controller checks, legacy controllers vary. | Add policies/middleware coverage and tests for all sensitive operations. |
| API consistency is partial | Newer endpoints use standard envelopes; legacy endpoints vary. | Standardize API responses and error payloads before frontend replacement. |
| Financial state changes are split across legacy and demo paths | Legacy loan/payment code and demo service coexist. | Consolidate production services and state machines. |
| Frontend mock mode must stay production-disabled | Frontend now requires `NEXT_PUBLIC_USE_MOCK_API=true` for fixtures, and production builds fail when that flag is enabled. | Keep deployment configuration locked to real API mode. |
| Frontend route middleware only checks token presence | Middleware now requires a token cookie, but does not validate the bearer token cryptographically. | Rely on backend authorization for data and consider server-side session validation for sensitive pages. |
| Admin operations are not replacement-grade | Admin dashboard is placeholder; credit review uses demo snapshot. | Build real operations console with maker-checker and exception handling. |
| Payment callback/webhook security must be hardened | Signature structures exist, but production provider behavior is unverified. | Complete provider-specific validation, idempotency, replay protection, and alerting. |
| Monitoring and incident response are undefined | No production runbook or observability evidence. | Add uptime checks, logs, alerts, queues, failed job handling, and incident playbooks. |

## Medium: can fix before pilot or early rollout

| Risk | Evidence | Required action |
| --- | --- | --- |
| Accessibility is not audited | Labels exist but no browser accessibility test was run. | Run WCAG basics pass and keyboard/screen-reader checks. |
| Mobile responsiveness is not verified | CSS exists but browser/device tests could not run. | Test core flows on mobile viewports. |
| Field-level validation UX is limited | Frontend mostly redirects with message notices. | Add inline errors and clearer recovery guidance. |
| Empty/loading states are generic | Basic placeholders/loading pages exist. | Add workflow-specific guidance. |
| Operational reporting is not designed in the frontend | Admin views are snapshot/demo focused. | Add production reporting screens after backend contracts are ready. |

## Low: can fix after launch if excluded from replacement scope

| Risk | Evidence | Required action |
| --- | --- | --- |
| Savings placeholder | Placeholder screen only. | Keep out of replacement launch unless live solution requires it. |
| Insurance placeholder | Placeholder screen only. | Keep out of replacement launch unless live solution requires it. |
| Investments placeholder | Placeholder screen only. | Keep out of replacement launch unless live solution requires it. |
| Employer portal placeholder | Placeholder screen only. | Keep out of replacement launch unless live solution requires it. |

## Non-production elements that must not ship enabled

- `NEXT_PUBLIC_USE_MOCK_API` fixture mode.
- `/api/mock-login`.
- Investor-demo `/api/demo/*` flows as customer-facing production workflows. These routes are now disabled by default outside `local` and `testing`, unless `OPFIN_ENABLE_DEMO_ROUTES=true`.
- Mock mobile money provider.
- MTN/Airtel placeholder adapters in the provider-agnostic layer.
- Hardcoded demo credentials in docs or seeders for production environments.
- Any financial state change path not covered by audit logging, idempotency, transactions, and ledger balancing.

## Required production gate

No cutover until:

1. Critical risks are resolved.
2. Fresh migrations pass.
3. Backend test suite passes.
4. Frontend test/typecheck/lint/build passes.
5. End-to-end replacement workflows pass in staging with real sandbox providers.
6. Ledger and mobile-money reconciliation are signed off.
7. Support and compliance users complete operational acceptance testing.
