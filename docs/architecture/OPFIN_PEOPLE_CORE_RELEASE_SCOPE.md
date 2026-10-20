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
