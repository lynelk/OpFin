# Live System Replacement Plan

Date: 2026-05-22

This plan treats the current OpFin system as live and customer-impacting. The new `OpFin-BE` and `OpFin-FE` build must not replace it until data migration, operational workflows, provider integrations, reconciliation, support processes, and rollback have been proven in staging and signed off by business, operations, compliance, and engineering.

## 1. Replacement Strategy

### Options

| Option | Description | Benefits | Risks |
| --- | --- | --- | --- |
| Big bang replacement | Migrate all users, loans, payments, admin workflows, integrations, and reporting to the new system in one cutover window. | Short transition period, one live system after cutover, less long-term dual-running complexity. | Highest risk of customer disruption, data mismatch, duplicate payment posting, broken admin workflows, provider callback loss, and difficult rollback. |
| Phased replacement | Move bounded capabilities or customer cohorts to the new system in controlled phases while the old system remains the system of record for non-migrated scope. | Lower operational risk, supports parallel validation, allows rollback per phase, gives teams time to validate balances and workflows. | Requires temporary integration between old and new systems, more operational coordination, duplicate reporting until migration finishes. |

### Recommended approach

Use a phased replacement with a formal parallel run and a final controlled cutover.

Recommended sequence:

1. Discovery and data inventory.
2. Migration dry run in staging.
3. Backend and frontend production readiness remediation.
4. Parallel run using read-only or shadow-mode production data.
5. Limited pilot cohort if permitted by operations and compliance.
6. Final cutover after reconciliation and sign-off.
7. Post-cutover hypercare.

### Rationale

OpFin includes regulated and money-moving workflows: KYC, consent, credit decisions, loan accounts, repayment schedules, mobile money, ledger, support, and compliance reporting. A phased approach reduces the chance of losing financial state, duplicating payments, misreporting balances, or leaving operations without a usable support path.

### Strategy decision

Do not use big bang replacement unless the current live system is very small, the migration is fully rehearsed, provider callback routing can be switched atomically, and rollback has been tested with real cutover data volumes.

## 2. Current Live System Discovery

Collect the following before migration design is finalized.

### User and customer data

- User records: IDs, names, phone numbers, emails, authentication identifiers, password strategy, account statuses, lockouts, created/updated timestamps.
- Customer profiles: national ID/NIN, date of birth, address, employment, employer linkage, income fields, contact preferences, customer status.
- Admin users: names, roles, permissions, last login, access status, privileged actions.
- Roles and permissions: role definitions, permission mappings, route/menu access, maker-checker rules if present.

### KYC, consent, and compliance evidence

- KYC records: status, provider, provider reference, evidence, review notes, reviewer, timestamps, expiry, rejection reason, retry history.
- Consent records: purpose, policy version, status, channel, grant/revoke timestamps, consent text/evidence, IP/device if captured.
- Audit logs: actor, subject, event, metadata, IP, user agent, timestamps, retention rules.
- Reports: generated reports, report parameters, files, recipients, submission status, regulator/CRB exports.

### Credit and loan lifecycle

- Product settings: product IDs, names, terms, interest rates, fees, duration, eligibility, status, institution mapping.
- Loan applications: customer, product, term, amount, reason, status, reviewer, decision records, timestamps, rejection/refer reasons.
- Active loans: loan IDs, principal, status, disbursed amount, disbursed date, maturity, outstanding principal, interest, fees, penalties, arrears.
- Repayment schedules: due dates, principal, interest, fees, paid amounts, outstanding amounts, overdue flags, schedule versioning.
- Repayment history: payment reference, amount, allocation, channel, status, reversal/correction history, timestamps.

### Payments, ledger, and balances

- Wallet/payment transactions: mobile money provider, direction, amount, phone, provider reference, internal reference, status, callback payload, retry state.
- Mobile money provider records: MTN/Airtel transaction IDs, settlement files, webhook IDs, reconciliation status, reversal records, failed transactions.
- Ledger or balance records: chart of accounts, journal entries, balances, trial balance, account mapping, corrections, write-offs.
- Configurations: environment settings, provider credentials inventory, callback URLs, SMS/OTP providers, queues, cron jobs, feature flags.
- Notifications: SMS/email/push templates, delivery history, opt-in/out status, failed delivery handling.
- Integrations: KYC/NIN provider, CRB provider, MTN, Airtel, SMS, email, reporting exports, analytics/monitoring.

## 3. Replacement Scope

Detailed scope classification is maintained in `replacement-feature-scope.md`.

Cutover scope must include all active live-system capabilities that affect customer access, money movement, credit decisions, loan balances, support operations, or required reporting. Features that are not active in the live system can stay out of cutover scope, but they must be explicitly disabled or labelled unavailable in the new system.

## 4. Migration Strategy

### Data extraction

- Export source data from the live system using repeatable scripts or database snapshots.
- Capture extraction timestamp, source environment, schema version, record counts, and checksum/hash summaries.
- Export provider-side records for MTN/Airtel settlement and transaction status where possible.
- Preserve raw extracts in encrypted storage with restricted access.

### Data cleaning

- Normalize phone numbers to one canonical Uganda format.
- Identify duplicate users, duplicate loans, duplicate transactions, missing provider references, orphaned schedules, and invalid dates.
- Flag inconsistent KYC, consent, loan, and payment states for operations review.
- Do not silently correct financial values; record every correction decision.

### Data mapping

- Map live user IDs to new user IDs.
- Map products and terms to new product/term records.
- Map KYC states to `kyc_cases`.
- Map consent states to `consent_records`.
- Map credit decisions and CRB data to production decision records.
- Map loans and schedules to new loan account and repayment schedule structures.
- Map old wallet/payment transactions to mobile money transaction records.
- Map old balances/journals to opening ledger transactions and production ledger accounts.
- Map admin users to role-based permissions.

### Data import

- Import into staging first.
- Use idempotent import jobs with batch IDs.
- Import identity and product records before loan/payment records.
- Import financial state only after opening-balance reconciliation is complete.
- Record import audit logs and import manifests.

### Validation

- Compare source and target record counts by table/module.
- Validate required fields, foreign keys, statuses, timestamps, and active/inactive flags.
- Validate every active loan has a customer, product, schedule, balance, and ledger opening entry.
- Validate every provider transaction has internal reference mapping or documented exception status.

### Reconciliation

- Reconcile customer balances.
- Reconcile active loan principal, interest, fees, arrears, and total outstanding.
- Reconcile mobile money transactions against provider settlement/status records.
- Reconcile ledger opening balances to live system trial balance or approved balance export.
- Produce exception lists with owner, status, and sign-off.

### Sign-off

Required sign-off:

- Engineering: migrations, tests, builds, observability, rollback.
- Operations: admin workflows, support handling, reconciliation queues.
- Finance: balances, ledger, trial balance, settlement.
- Compliance: KYC, consent, audit, reports, retention.
- Business owner: customer impact and launch decision.

## 5. Parallel Run Strategy

Detailed parallel-run steps are maintained in `parallel-run-plan.md`.

The new system should run side by side with the live system before cutover. During parallel run, the live system remains the source of truth. The new system runs imported or mirrored data and produces comparable outputs without initiating live money movement unless explicitly approved for a pilot.

Compare:

- Customer profile state.
- KYC and consent status.
- Loan application status and decisions.
- Active loan balances.
- Repayment schedules.
- Ledger entries and trial balance.
- Mobile money transaction state.
- Reports.
- Admin actions and audit logs.

## 6. Cutover Strategy

Detailed cutover steps are maintained in `cutover-strategy.md`.

Cutover must include:

- Readiness checklist.
- Freeze window.
- Final data sync.
- Provider callback routing or DNS/routing change.
- Admin access switch.
- Customer communication.
- Post-cutover validation.
- Hypercare window.

## 7. Rollback Strategy

Detailed rollback steps are maintained in `rollback-strategy.md`.

Rollback is required if the new system cannot safely serve customers, preserve balances, process payments, support operations, or meet reporting obligations. Rollback must account for data written in the new system during the cutover window, especially payments, ledger entries, support actions, and customer communications.

## 8. Production Gate

Do not cut over until:

1. Critical blockers in `blockers-and-risks.md` are resolved.
2. Backend migrations pass from a fresh database.
3. Backend tests, linting/formatting, and static checks pass.
4. Frontend tests, typecheck, lint, and build pass.
5. Live-provider sandbox certification is complete.
6. Migration dry run passes with approved exception list.
7. Parallel run meets acceptance thresholds.
8. Cutover and rollback rehearsals are complete.
9. Operations, finance, compliance, engineering, and business owners sign off.
