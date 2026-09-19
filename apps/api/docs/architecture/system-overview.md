# OpFin system overview

Review date: 18 September 2026. Audience: product, support, developers and operations. This is an architectural map of the repository, not a production-readiness certificate.

## Purpose and scope

OpFin is a Uganda-first personal-finance platform. The launch customer journey focuses on secure access, identity verification, credit profile and affordability, a formal loan offer, verified-wallet disbursement and repayment. Broader savings, investment, protection, employer and community-finance capabilities must be distinguished from enabled launch services. Existing design documents and registered endpoints do not prove provider activation or approval for use.

The API is the system of record for customer identity, consent, financial product state, credit decisions, payment outcomes, operational actions and compliance evidence. Read [the launch journey](../../../../docs/LAUNCH_CUSTOMER_JOURNEY.md), [backend responsibilities](../../README.md) and [engineering rules](../../../../AGENTS.md) together.

## People and systems

Customers use the supported app and assisted channels. Authorised operational, support, compliance and institution users have role-specific workflows. Employer and partner capabilities depend on the relevant product and permissions. External providers supply identity, credit-information, messaging and payment services through the configured integration boundaries.

Authentication identifies the caller. Authorisation must additionally establish permission for the particular operation and record. A role label or successful login is not permission to inspect every customer's information.

## Runtime components

| Component | Responsibility |
| --- | --- |
| `apps/api` | Laravel API and backend operational interfaces; identity, consent, credit, offers, financial state, accounting and reconciliation |
| `apps/web` | Next.js customer and operational interfaces consuming the API |
| `apps/client` | Flutter Android/iOS customer experience consuming the API |
| Database | Authoritative customer, obligation, transaction, schedule, consent, ledger and audit records |
| Queue worker | Background work such as provider processing, notifications and reconciliation according to the registered jobs |
| Scheduler | Periodic operational work and heartbeat evidence according to the scheduled commands |
| Private evidence storage | Controlled identity-document storage, configured through the backend and never exposed as public customer fields |
| External adapters | Configured identity, CRB/MNO/scoring, messaging and payment integration boundaries |

The current API manifest declares Laravel 12. Use [Composer requirements](../../composer.json), the lockfile and [developer setup](../../../../docs/DEVELOPER_START_HERE.md) for exact dependency requirements rather than an older architecture version label.

## Request and channel boundaries

A request reaches a registered route, then its middleware and handler apply authentication, validation, record ownership, permissions and capability rules. Domain services own the resulting state transition. Financial changes must retain the required transaction, audit, ledger and provider-finality controls. Read [API design](api-design.md) for the named service boundaries; confirm endpoint-specific behaviour in the handler and its tests.

API routes are not confined to one file. [Application bootstrap](../../bootstrap/app.php) loads the main API routes and the autopilot, experience, governance and long-range route groups. The generated reference reads Laravel registration after those groups are loaded. It does not infer a complete contract from one source file.

Web and Flutter use authenticated API contracts. WhatsApp and USSD use the same server-authoritative customer state, with authenticated hand-off for high-impact commitments. PINs must not be requested in chat or USSD. Assisted identity verification may change the interaction method, not the required identity assurance.

## Financial and provider controls

CPay is the production collection and payout boundary unless an explicitly approved architecture change says otherwise. Do not describe direct MTN or Airtel adapters as the current production money-movement architecture. Local mock configuration is for a controlled development/test environment, not a substitute for verified production provider outcomes.

Money uses integer minor units and the configured currency exponent. Credit limits are profile-level: adding phones or wallets does not multiply exposure. Wallet selection requires verified ownership. A provider acknowledgement is not accounting finality, and reconciliation must not invent balancing entries.

Identity, CRB, MNO and other score components retain source attribution. Unavailable or inconclusive provider information remains unavailable or under review; it must not become a fabricated verification result or score. See [security controls](../../../../SECURITY.md) and [the backend contract](../../README.md).

These are required architectural controls. Passing a source check does not independently certify that every path, role and deployment satisfies them. Database-backed behavioural tests and release acceptance provide the corresponding evidence.

## Deployment and health

The documented deployment separates web, API, queue-worker and scheduler responsibilities. Production also needs the configured database, private persistent evidence storage, environment-scoped secrets, logs, alerting, backups and tested recovery. Use [the operational runbook](../operations/operational-runbook.md) and [readiness checklist](../operations/production-readiness-checklist.md) for the release procedure.

Health routes already exist. `/up` is the framework health route; `/api/health/live` reports process liveness; `/api/health/ready` reports database, worker/scheduler and integration information. Inspect readiness fields as well as HTTP status: the reviewed handler can return HTTP success while reporting warming operations or blocked integrations. See [the health controller](../../app/Http/Controllers/Api/HealthController.php).

## Current evidence and maintenance

Use the exact candidate commit, deployment environment and test results when assessing readiness. `main`, a generated route export and the deployed service are different evidence states. Neither a historic list of missing controls nor a new architecture description should be reused as a current pass/fail verdict without verification.

Update this overview when service boundaries change, alongside contracts, operational guidance and training sources. The [documentation-maintenance workflow](../../../../docs/DOCUMENTATION_MAINTENANCE.md) checks changes and exposes reference gaps; human review remains necessary for meaning, completeness and release availability.
