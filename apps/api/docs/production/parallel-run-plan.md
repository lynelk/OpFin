# Parallel Run Plan

Date: 2026-05-22

The parallel run validates the new OpFin build against the current live system before cutover. The current live system remains the source of truth until formal cutover approval.

## Objectives

- Prove migrated data matches the live system.
- Prove new workflows produce expected outcomes.
- Detect balance, schedule, ledger, report, and provider-status mismatches before customers are moved.
- Train operations, support, finance, and compliance teams on the new system.

## Environments

| Environment | Purpose |
| --- | --- |
| Live old system | Source of truth during parallel run. |
| New staging system | Runs imported/mirrored live data and production-like configuration. |
| Provider sandbox/certification | MTN/Airtel/KYC/CRB integration testing without live money movement unless approved. |
| Reporting workspace | Stores comparison outputs, exception lists, sign-off evidence. |

## Run Modes

### Shadow read-only mode

- Import live data into the new system.
- Disable customer-facing writes in the new system.
- Disable live mobile money initiation.
- Generate reports and ledger postings from imported/mirrored data only.

### Controlled pilot mode

Use only after shadow mode passes and stakeholders approve.

- Select a small customer or staff cohort.
- Route only approved pilot workflows to the new system.
- Keep rollback path open for every pilot action.
- Reconcile pilot actions daily.

## Comparison Areas

### Users and profiles

- Compare total user count by status and role.
- Compare customer profile required fields.
- Compare admin users and role assignments.
- Investigate missing, duplicate, or inactive-account differences.

### KYC and consent

- Compare KYC status by customer.
- Compare KYC provider references and review timestamps.
- Compare consent purpose, policy version, status, grant/revoke timestamps.
- Confirm revoked consent blocks future credit processing in the new system.

### Credit lifecycle

- Compare loan application count and status.
- Compare product/term mapping.
- Compare decisions, reason codes, and manual review states.
- Compare offer status, expiry, acceptance evidence, and cancellation.

### Loan balances and repayment schedules

- Compare active loan count.
- Compare principal, interest, fees, penalties, arrears, and total outstanding.
- Compare schedule rows, due dates, paid amounts, and outstanding amounts.
- Compare customer-visible statement totals.

### Ledger and accounting

- Compare old trial balance or balance export to new opening ledger balances.
- Compare ledger entries generated for disbursements and repayments.
- Confirm every production ledger transaction balances.
- Confirm correction and reversal records are explicit and auditable.

### Mobile money and payments

- Compare MTN/Airtel provider references.
- Compare transaction direction, amount, status, callback payload, and settlement status.
- Confirm duplicate webhook/event handling does not duplicate ledger entries.
- Confirm failed, reversed, and pending transactions remain explainable.

### Reports

- Compare KYC report totals.
- Compare consent report totals.
- Compare credit decision report totals.
- Compare loan book report totals.
- Compare mobile money settlement reports.
- Compare ledger/trial balance reports.
- Compare audit export totals and sample records.

### Admin actions

- Recreate common admin workflows in the new system using imported test records.
- Compare status transitions and audit logs.
- Confirm support users cannot perform operations-only actions.
- Confirm platform admins cannot bypass required audit logging.

## Acceptance Thresholds

| Area | Threshold |
| --- | --- |
| Customer/user counts | 100% match or approved exception list. |
| Active loans | 100% match. |
| Loan balances | 100% match for principal/interest/fees/outstanding, or documented rounding rule approved by finance. |
| Repayment schedules | 100% match for active loans unless a schedule correction is approved. |
| Mobile money references | 100% mapped for active/pending/recent transactions. |
| Ledger/trial balance | Balanced and signed off by finance. |
| Reports | Totals match or exceptions approved by compliance/finance. |
| RBAC | No critical unauthorized access findings. |
| Audit logs | Sensitive actions produce audit logs. |

## Daily Parallel Run Routine

1. Import or mirror latest source data into staging.
2. Run migration validation checks.
3. Run balance and schedule comparison.
4. Run ledger and mobile-money reconciliation.
5. Generate report comparisons.
6. Review exception list with owners.
7. Record approvals or remediation tasks.
8. Repeat until acceptance thresholds are met for the agreed run period.

## Exit Criteria

Parallel run can end when:

- Acceptance thresholds pass for the agreed period.
- All critical exceptions are resolved.
- Remaining exceptions have named owners and written business approval.
- Operations, finance, compliance, support, and engineering sign off.
- Cutover and rollback rehearsals have passed.
