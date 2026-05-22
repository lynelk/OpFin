# Production Gap Assessment

Date: 2026-05-22

Scope: OpFin-BE and OpFin-FE as a replacement for the current live OpFin solution. This is not an investor-demo assessment.

## Bottom line

OpFin is not production-ready as a replacement system today. It contains useful foundations and a demo vertical slice, but several core replacement requirements are incomplete, sandbox-only, inconsistently implemented, or unverified because the local PHP/Composer/npm toolchains are unavailable.

The safest classification is: suitable for architecture continuation and controlled internal demos; not suitable for customer cutover, repayment operations, live disbursement, regulatory reporting, or support operations.

## Current implementation status

### Implemented backend modules

- Laravel 11 API and legacy web/admin routes.
- Sanctum API authentication.
- User profile endpoint.
- Role constants and permission mapping for platform admin, operations, support, employer admin, and customer.
- Audit log model, migration, middleware, and service-level usage in selected sensitive/demo flows.
- Loan products and loan product terms.
- Institutions.
- Legacy loan applications.
- Legacy loan accounts, schedules, repayments, transactions, accounts, journal entries, and float top-ups.
- NIN validation endpoint and credit-score request endpoint.
- SMS/OTP scaffolding.
- Mobile money adapter layer with provider interface, mock adapter, MTN/Airtel placeholders, signature validator, normalized responses, transaction table, retry/failure/reconciliation fields, and idempotency fields.
- Investor-demo consent, decision, offer, dashboard, admin snapshot, and demo loan acceptance flow.
- Production-shaped KYC cases, versioned consent records, CRB reports, credit decisions, immutable minor-unit ledger tables, reconciliation runs/items, support cases, and compliance report records.
- RAG/chat scaffolding and OpenAI/Pinecone configuration hooks.
- Feature tests for foundation auth/roles/audit, backend checkpoint flows, API security, mobile money adapter behavior, and investor-demo slice.

### Implemented frontend screens

Next.js implementation:

- Login.
- Admin login.
- Customer dashboard.
- KYC status.
- Consent management.
- Loan application.
- Loan decision result.
- Loan offer.
- Loan account.
- Repayment schedule.
- Admin dashboard placeholder.
- Admin credit review.
- Admin audit trail.
- Employer portal placeholder.
- Savings placeholder.
- Insurance placeholder.
- Investments placeholder.
- Route-protected portal layout and role-aware navigation.

Separate older Flutter tree remains present and should be treated as a separate legacy/frontend artifact until a migration decision is made.

### Fully functional today

Based on code inspection, not runtime proof:

- API route definitions for the demo vertical slice exist.
- Sanctum-protected API grouping is present.
- Demo KYC/consent/application/decision/offer/acceptance/admin snapshot flow is implemented in code.
- Frontend can call the real backend when `NEXT_PUBLIC_USE_MOCK_API=false`.
- Frontend can run fixture mode when real backend is unavailable.
- Mobile money mock adapter can normalize sandbox outcomes.
- Audit logs are recorded for selected sensitive and demo financial actions.

### Partially implemented

- Authentication: token login exists, but production controls such as MFA, device/session governance, account lockout policy review, and support-safe recovery are incomplete.
- Authorization/RBAC: roles exist, but policy coverage is not comprehensive and some checks are controller-level or legacy-style.
- KYC: NIN validation exists, but full provider workflow, evidence capture, review queue, expiry, resubmission, and exception handling are not production-complete.
- Consent: demo consent exists; production-grade consent records, versioning, lawful basis, revocation impacts, and reporting are incomplete.
- Credit applications: legacy and demo flows coexist; production workflow and state machine are not unified.
- Decisioning: demo affordability/decisioning is mock logic; production credit policy, CRB integration, explainability, overrides, and manual review are incomplete.
- Loan offers: demo offer table exists; production offer lifecycle, terms disclosure, acceptance evidence, and expiry handling need hardening.
- Loan accounts and schedules: exist, but decimal/string money fields and model-event schedule generation are not acceptable for regulated production finance.
- Ledger: journal entries still exist, and successful legacy disbursement/repayment processing now also posts immutable balanced minor-unit ledger transactions. Full replacement remains incomplete because legacy decimal money fields and old journal balance mutations still exist.
- Mobile money: adapter layer exists, but MTN/Airtel adapters are placeholders for the new layer and legacy services still contain live-call logic.
- Admin operations: legacy web admin and Next admin placeholders coexist; production operational workflows are incomplete.
- API consistency: newer endpoints use an envelope, legacy endpoints are inconsistent.
- Error handling: common statuses are handled in frontend/client, but backend exception normalization and field-level UX are incomplete.
- Tests: important tests exist, but the full test/build/migration suite could not run in this environment.

### Mocked or sandbox-only

- Investor-demo affordability.
- Investor-demo decisioning.
- Investor-demo consent.
- Investor-demo loan offers.
- Investor-demo mobile money disbursement.
- Frontend fixture API mode.
- `/api/mock-login` frontend route.
- Savings, insurance, investments, employer portal screens.
- MTN/Airtel provider-agnostic adapters in the new mobile-money layer.
- KYC provider verification beyond stored NIN status fields.
- CRB workflow and production credit scoring.
- Production compliance reporting.

### Missing for production use

- Production cutover plan with data migration, reconciliation, rollback, and parallel-run criteria.
- Verified fresh migrations and seeders.
- Verified test/build/lint/static-analysis pipeline.
- Unified production financial state machine.
- Integer minor units across all financial tables.
- Full migration of all financial state changes to immutable balanced ledger postings. Successful legacy loan disbursements and repayments now post into the production ledger, but legacy decimal journal/account writes remain in place during migration.
- Production-grade mobile money disbursement, collection, webhook, reversal, reconciliation, retry, and support tooling.
- Full live KYC provider integration beyond the new persisted KYC case/review foundation.
- Full production consent lifecycle reporting beyond the new versioned consent records.
- Live CRB request integration and credit policy governance beyond the new CRB report and gate-based decision records.
- Customer support console and case management.
- Admin operational dashboards with maker-checker controls.
- Incident response, monitoring, audit export, backups, and disaster recovery.
- Privacy, data retention, regulatory reporting, and access review processes.

## Live system replacement requirements

### Minimum safe replacement features

- Customer registration/login/account recovery.
- Customer profile and KYC status with verified provider evidence.
- Consent capture, versioning, revocation, and audit evidence.
- Product catalog and eligibility rules.
- Loan application submission and tracking.
- Credit decisioning with CRB and affordability checks.
- Manual review queue with maker-checker controls.
- Offer disclosure, expiry, acceptance, and audit evidence.
- Loan account creation with transactional ledger posting.
- Repayment schedule generation and recalculation rules.
- Mobile money disbursement and repayment collection with idempotency, reconciliation, and reversals.
- Customer loan balance and statement views.
- Admin operations for applications, loans, payments, exceptions, customers, and audit trail.
- Support tooling for identity, payment, loan, and complaint cases.
- Compliance reports and audit exports.

### Workflows required before migration

- Register/login/reset credentials.
- Verify KYC and handle failed/rejected KYC.
- Grant/revoke consent and block future credit processing after revocation.
- Submit, review, approve, decline, and refer applications.
- Accept offers and create loan accounts atomically.
- Disburse to mobile money and reconcile provider confirmation.
- Collect repayments and allocate principal/interest/fees correctly.
- Reverse, retry, or fail payments without duplicating ledger entries.
- Generate accurate customer statement and support-view ledger history.
- Handle arrears, partial repayments, early settlement, and schedule changes.
- Export operational, reconciliation, audit, and regulatory reports.
- Migrate existing customers, active loans, schedules, ledger balances, and provider references.

### Operational dependencies

- PHP 8.2+ and Composer.
- Node/npm toolchain for frontend.
- Production database with backups and migration controls.
- Queue worker and failed-job handling.
- SMS/OTP provider.
- KYC/NIN provider.
- CRB provider.
- MTN Mobile Money and Airtel Money production credentials.
- Email/SMS notification delivery.
- Monitoring, logging, alerting, tracing, and uptime checks.
- Secrets management.
- CI/CD with gated tests, linting, migration checks, and deployment approvals.
- Customer support tooling and escalation paths.

### Regulatory, audit, and reporting needs

- Immutable audit log for financial and customer-data actions.
- Regulatory audit export with actor, IP, user agent, before/after metadata, and reason codes.
- Consent register and revocation register.
- KYC evidence register and review history.
- Credit decision reason-code retention.
- CRB submission and retrieval audit trail.
- Mobile money settlement and reconciliation reports.
- Ledger trial balance and exception reports.
- Complaints/support case audit history.
- Data retention, deletion, and privacy controls.
- Role access reviews and privileged-action reporting.

### Customer-support requirements

- Search customers by phone, national ID, account, application, and transaction reference.
- View KYC, consent, applications, decisions, offers, loans, schedules, ledger, payments, and audit events.
- Payment status lookup, retry/reversal request, and escalation workflow.
- Complaint/case logging and SLA tracking.
- Support-safe account recovery and identity verification.
- Clear customer messaging for failed KYC, missing consent, declined decisions, payment failures, and arrears.

## Backend production readiness

| Area | Status | Production gap |
| --- | --- | --- |
| Authentication | Partial | Sanctum exists; production hardening, MFA/OTP policy, lockout, token lifecycle, and recovery controls need review. |
| Authorization | Partial | Roles exist but policy/middleware coverage is incomplete across legacy controllers and admin operations. |
| RBAC | Partial | Role constants exist; permission enforcement needs comprehensive route/controller/policy coverage. |
| KYC | Partial | NIN fields and validation endpoint exist; provider-backed lifecycle and evidence are incomplete. |
| Consent | Demo only | Demo consent cannot replace production consent management. |
| Credit applications | Partial | Legacy and demo paths coexist; production workflow is not unified. |
| Decisioning | Mock/demo | No production scoring, CRB, manual review, override governance, or policy engine. |
| Loan offers | Demo/partial | Demo offers exist; production lifecycle and customer disclosure are incomplete. |
| Loan accounts | Partial | Exists, but financial invariants and minor-unit storage are not production-safe. |
| Repayment schedules | Partial | Exists, but generation is not sufficiently governed/tested for all production cases. |
| Ledger | Partial/high risk | Immutable balanced production ledger postings now exist for legacy loan disbursements and repayments, but legacy decimal account/journal writes still remain. |
| Mobile money | Partial | Adapter layer is valuable; real provider adapters and operational reconciliation are not production-ready. |
| Audit logging | Partial | Useful foundation; coverage is not universal and audit retention/export is incomplete. |
| Admin operations | Partial | Legacy web admin exists; Next admin is mostly demo/snapshot focused. |
| API consistency | Partial | Demo/foundation endpoints use envelope; legacy endpoints vary. |
| Error handling | Partial | Demo errors are normalized; global API error strategy is incomplete. |
| Database integrity | High risk | Financial tables still use decimal/string money fields and inconsistent constraints/indexes. |
| Tests | Unknown in environment | Tests exist but could not be run here; coverage is not enough for production finance. |

## Frontend production readiness

| Area | Status | Production gap |
| --- | --- | --- |
| Customer flows | Partial | Demo journey exists; real KYC, statements, repayments, support flows, and failure recovery are incomplete. |
| Admin flows | Partial/demo | Credit review and audit trail rely on demo snapshot; operations console is missing. |
| Error states | Partial | Generic notices exist; field-level validation and operational exception states need improvement. |
| Empty states | Partial | Several screens show empty notices; production empty states need action guidance. |
| Loading states | Partial | Route loading pages exist; granular loading/skeleton states are limited. |
| Protected routes | Partial | Middleware exists; role-only cookie can pass session guard without a valid token. |
| Role-aware navigation | Partial | Navigation filters by role groups; enforcement must be mirrored server-side for every API. |
| API integration | Partial | Real API mode exists; mock mode remains default unless explicitly disabled. |
| Form validation | Partial | HTML required fields and server redirects exist; no robust client/server validation UX. |
| User messaging | Partial | Demo labels are clear; production content and regulated disclosures are missing. |
| Mobile responsiveness | Unknown | CSS exists but browser/device verification could not be run. |
| Accessibility basics | Partial | Labels and semantic tables exist; full keyboard, contrast, focus, and screen-reader checks are pending. |

## Test and build checks

Commands attempted on 2026-05-22:

| Repo | Command | Result |
| --- | --- | --- |
| OpFin-BE | `git diff --check` | Passed. |
| OpFin-BE | `php --version` | Blocked: `php` not recognized. |
| OpFin-BE | `composer --version` | Blocked: `composer` not recognized. |
| OpFin-BE | `php artisan test` | Blocked: `php` not recognized. |
| OpFin-BE | `php artisan migrate:fresh --seed` | Blocked: `php` not recognized. |
| OpFin-BE | `.\vendor\bin\pint --test` | Blocked: Pint executable not recognized. |
| OpFin-FE | `git diff --check` | Passed with a warning on pre-existing dirty `opfin-frontend/lib/otp_screen.dart`. |
| OpFin-FE | `npm --version` | Blocked: `npm` not recognized. |
| OpFin-FE | `npm run typecheck` | Blocked: `npm` not recognized. |
| OpFin-FE | `npm run lint` | Blocked: `npm` not recognized. |
| OpFin-FE | `npm run test` | Blocked: `npm` not recognized. |
| OpFin-FE | `npm run build` | Blocked: `npm` not recognized. |

## Production decision

Do not cut over customers or financial operations to this implementation yet. Treat it as a foundation and demo system until the critical blockers in `blockers-and-risks.md` are resolved and verified with repeatable tests, migrations, builds, reconciliation evidence, and operational sign-off.

## Guardrails added after assessment

- Backend `/api/demo/*` routes are now registered only when `OPFIN_ENABLE_DEMO_ROUTES=true` or the app environment is `local`/`testing`.
- `OPFIN_ENABLE_DEMO_ROUTES=false` is documented in `.env.example` as the default production posture.
- Laravel now fails fast in production when `APP_DEBUG=true`, `MOBILE_MONEY_PROVIDER=mock`, or `OPFIN_ENABLE_DEMO_ROUTES=true`.
- This does not make the production replacement ready; it only prevents demo endpoints from being exposed by default in production.
- Successful legacy loan disbursement and repayment processing now creates deterministic, idempotent production ledger postings and audit records alongside the existing legacy journal entries.
