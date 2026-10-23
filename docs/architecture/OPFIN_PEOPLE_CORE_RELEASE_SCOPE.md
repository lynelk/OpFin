# OpFin People — Core Release Scope and Extension Architecture

Status: **Approved product scope; implementation pending**. This document does not imply features are live.

## Core Release: ten mandatory capabilities

1. **Core HR:** organisation, branch, department, cost centre, employee lifecycle, contracts, job history, reporting lines, access controls, onboarding/offboarding, document retention.
2. **Leave:** policies, accrual, balances, calendars, requests, delegated approvals, cancellation, audit and payroll impact.
3. **Payroll:** earnings, deductions, allowances, advances, Uganda statutory rules with effective dates, maker-checker approvals, locked periods, payslips, exports and reconciliation.
4. **Attendance:** rosters, shifts, clock events, breaks, overtime, absence, offline ingestion, corrections and supervisor approvals; non-biometric capture supported from day one.
5. **Field accountability:** assigned visits and work orders, activity plans, offline evidence, deliverables, receipts, imprest/expense reconciliation, exception review and supervisor sign-off. Location evidence is optional and limited to authorised work.
6. **Asset management:** asset register, QR/barcode tags, employee custody, issue/transfer/return, inspections, incidents, maintenance and offboarding clearance.
7. **KPI tracking:** versioned KPI definitions, owners, periods, target/actual, evidence, weighting, review, disputes, sign-off and reporting; no automatic punitive decisions.

All ten are required for Core Release acceptance; none may be replaced with a placeholder dashboard.

## Shared architecture

- OpFin People is a standalone HR service and remains operational without OpFin financial services.
- Organisation-scoped, least-privilege access to every record; scoped supervisor access; explicit separation of employee, HR, payroll and finance permissions.
- Stable employee ID, organisation ID, reporting-line history and effective-dated assignments shared by module contracts.
- Common workflow engine: request, approve, reject, delegate, escalate, reverse; immutable audit history and idempotent commands.
- Events/outbox for attendance-approved, leave-approved, expense-approved, asset-assigned, KPI-period-closed and payroll-locked. Version events and use idempotent consumers.
- Modules own their tables and APIs. Payroll consumes approved inputs, not mutable draft attendance or unapproved expense data.
- Tenant isolation, consent, purpose restriction, encryption, retention, export, deletion/hold and data-access logs.
- Mobile-first employee experience, web employer portal, offline-safe field/attendance queue with conflict resolution.
- Integration to OpFin Business Spaces is optional; no employer access to private financial information.

## Additional mandatory Core Release capabilities and extension contracts

8. **Biometric connectors:** versioned AttendanceEvent ingestion adapter; vendor-specific mapping, clock skew handling, duplicate/replay detection, device health, employee-device binding, manual fallback. Prefer matching on devices and do not store biometric templates in the HR service. Require lawful basis, DPIA as appropriate, security and employee notice before activation.

9. **Appraisals:** consume signed-off KPI periods and authorised evidence snapshots; add goals, self-assessment, manager review, calibration, appeals and improvement plans without altering original KPI history.

10. **Rewards:** policy-based reward eligibility from approved KPI/appraisal events, nomination, budget checks, maker-checker approval, fraud safeguards, and optional payroll instruction. No automatic payroll payout on unreviewed score.

## Required end-to-end acceptance scenarios

1. Create organisation, invite employee, activate employment, restrict other tenants from access.
2. Record offline attendance; reconcile duplicates; approve correction; calculate approved overtime into draft payroll.
3. Approve leave and ensure attendance and payroll reconcile without double counting.
4. Dispatch field visit, capture offline evidence and receipts, approve accountable expenses, close task and update verified KPI.
5. Assign device, transfer it, report damage, recover on offboarding and block clearance only through documented policy.
6. Configure KPI period, evidence and weighting; reject unauthorised edits; close and reopen with audit and approval.
7. Prepare payroll, apply effective-dated statutory rules, approve with a second actor, lock, generate payslip and reconcile export.
8. Confirm standalone HR works when OpFin is unavailable; deny cross-tenant requests and unauthorised service calls.
9. Validate production-grade biometric device sync, enrolment safeguards, offline fallback, appraisal cycles and approved rewards workflows.
10. Run PHP/Laravel tests, Next.js typecheck/lint/tests/build, Flutter analyse/tests and integration/security tests before deployment.

## Delivery gates

- **Foundation:** identity federation, organisation provisioning, scoped authorisation, workflows, audit and migrations.
- **Core implementation:** all ten modules and employee/employer interfaces, with tested cross-module contracts.
- **Pilot:** attendance and field offline testing, payroll parallel runs, statutory verification and user acceptance.
- **Production:** passing CI, migration/backups and restore rehearsal, observability, security review, rollback plan and explicit operational sign-off.

**One Core Release:** no module is deferred to a later release. Staged internal delivery and feature flags are permitted only for testing and rollout safety; all ten modules must pass acceptance before the Core Release is declared complete. No deployment or release-complete claim until evidence supports it.

## Mobile distribution and future app separation (approved)

- **Core Release delivery:** OpFin People employee, supervisor, HR and employer experiences are embedded in the existing OpFin Flutter mobile application. Do not require a separate People mobile app for launch.
- **Navigation:** preserve the existing OpFin Finance, Activity and More journeys; expose a role-aware People workspace through the existing navigation, with deep links for attendance, leave, payroll, assets, field accountability, KPIs, biometrics, appraisals and rewards. Avoid assuming a new permanent bottom tab.
- **Organisation context:** support authorised switching between multiple engagements and organisations. Every API request must enforce tenant and role boundaries server-side.
- **Backend independence:** People retains its own domain APIs, data ownership, deployment boundaries, audit and authorisation, even while embedded in the OpFin mobile shell. The standalone People backend must remain viable if the OpFin finance backend is unavailable, subject to a secure identity/session strategy.
- **Employer web:** a responsive employer administration portal may coexist with the embedded mobile experience; it is not a prerequisite for employees to install another app.
- **Future dedicated app:** package People Flutter routes, widgets, state and API clients into a reusable feature module with a documented interface; a later dedicated People application may consume the same feature module and backend without migrating HR data or rewriting business rules.
- **Separation safeguards:** do not expose financial balances, loan information or financial eligibility to HR roles; avoid reusing financial permissions as HR permissions; no implicit credit-data sharing.
- **Release acceptance:** test the full People feature set inside OpFin on Android and iOS, deep-linking, logout, session expiry, multi-organisation switching, offline reconciliation, and existing OpFin finance regression flows.

## Protected source merge, not production activation (23 October 2026)

New People and Savings Groups route trees and new SACCO attestation, product-activation and settlement endpoints require `OPFIN_CORE_RELEASE_ENABLED=true`; this flag defaults **false outside tests**. Legacy SACCO routes are unaffected. Internal tests use explicit testing defaults. This allows review/merge of additive tables and restricted source while employer identity federation, KYC/consent/DPIA, regulator authority, funding, statutory payroll, device attendance and actual customer UAT remain unaccepted. Only authorised operators may change the production flag after separate named sign-off and monitoring/rollback acceptance. No system should interpret a passed unit test, submitted licence reference or locked payroll entry as confirmed money movement.

The same production-default-off gate covers the incoming signed People membership-verification endpoint and candidate club migration template/preview/submit routes; they are not live just because source is merged.
