# OpFin monorepo engineering rules

## Boundaries

- `apps/api` owns identity, consent, eligibility, financial decisions, obligations, ledger postings, provider finality and reconciliation.
- `apps/web` and `apps/client` consume authenticated API contracts and never connect directly to PostgreSQL.
- CPay remains the only production money-movement adapter unless an explicitly approved architecture change says otherwise.
- A provider acknowledgement is not accounting finality.
- Secrets remain service-scoped and are never exposed to web or client builds.

## Required verification

- API changes: formatting, tests, dependency audit, PostgreSQL/migration evidence where applicable.
- Web changes: dependency audit, typecheck, lint, tests and production build.
- Client changes: Flutter analyze/tests plus Android and iOS release compile gates.
- Shared financial/API changes: run all affected jobs and end-to-end contract tests.

## Product rules

- Keep `Home | Borrow | Save | Grow | More` as the customer mental model.
- Keep peer lending visible in Borrow and Grow while preserving regulated gates.
- Account deletion must remain available in-app and preserve only legally required records.
- Never advertise provider-gated or regulator-gated capability as live.
