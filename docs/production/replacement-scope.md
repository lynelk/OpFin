# Replacement Scope

Date: 2026-05-22

This document defines the minimum scope required before OpFin can safely replace the current live system.

## Replacement principle

The replacement system must handle the live system's current customer, loan, payment, support, audit, and reporting obligations without data loss, ledger imbalance, duplicate payments, untracked customer consent, or unsupported operational exceptions.

## Minimum in scope for cutover

### Customer identity and access

- Customer login, logout, password reset, and account recovery.
- Admin and support login with privileged-access controls.
- Session management and token lifecycle controls.
- Role-based access for customer, operations, support, employer admin if applicable, and platform admin.

### Customer data and KYC

- Customer profile.
- KYC status, provider verification evidence, failed/retry states, expiry, and manual review.
- National ID/NIN handling with privacy controls.
- Audit history for customer data changes.

### Consent and privacy

- Consent capture by purpose and policy version.
- Consent revocation with future-processing blocks.
- Consent evidence export.
- Data retention and deletion handling consistent with policy and law.

### Credit lifecycle

- Product catalog and terms.
- Eligibility checks.
- Loan application submission.
- Affordability checks.
- CRB checks.
- Decisioning with reason codes.
- Manual review and maker-checker approvals.
- Offer creation, disclosure, expiry, acceptance, and cancellation.

### Loan servicing

- Atomic loan account creation.
- Repayment schedule generation.
- Customer loan balance and statement.
- Repayment allocation across principal, interest, fees, and penalties if applicable.
- Arrears and overdue handling if live system requires it.
- Early settlement and partial payment handling if live system requires it.

### Ledger and financial controls

- Integer minor-unit money storage.
- Immutable double-entry ledger.
- Balanced journal enforcement.
- Transaction idempotency.
- Reversals and corrections with audit evidence.
- Trial balance and exception reporting.

### Mobile money

- MTN and Airtel production-ready disbursement.
- MTN and Airtel production-ready collection.
- Provider status lookup.
- Webhook validation and replay protection.
- Reconciliation workflow.
- Retry, failure, reversal, and settlement handling.
- Provider credential and secret management.

### Admin and support operations

- Customer lookup.
- Application review.
- Loan account review.
- Payment/reconciliation exception review.
- Audit trail search/export.
- Support case workflow.
- Role/access management.
- Operational dashboards and alerts.

### Reporting and compliance

- KYC reports.
- Consent reports.
- CRB request/submission reports.
- Credit decision reports.
- Loan book reports.
- Ledger and trial balance reports.
- Mobile money settlement reports.
- Audit exports.
- Complaints/support reports where required.

## Out of scope unless required by the live system

- Savings products.
- Insurance products.
- Investment products.
- Employer-linked benefits.
- AI chat/RAG features.

If any of these are active in the current live system, they move into cutover scope and must not remain placeholders.

## Migration requirements

- Inventory all live data tables and operational reports.
- Map live customers, KYC statuses, consents, products, applications, loans, schedules, repayments, ledger entries, mobile-money references, support records, and audit records.
- Reconcile opening balances before import.
- Run dry-run migrations in staging.
- Compare old and new balances, schedules, and customer-visible statements.
- Define rollback criteria and rollback execution steps.
- Run a parallel period before production cutover.

## Cutover acceptance checklist

- [ ] Production scope approved by product, operations, compliance, and engineering.
- [ ] Critical blockers resolved.
- [ ] Fresh backend migrations pass.
- [ ] Backend tests pass.
- [ ] Frontend tests, lint, typecheck, and build pass.
- [ ] Live-provider sandbox certification completed.
- [ ] Ledger reconciliation passes.
- [ ] Migration dry run passes.
- [ ] Support team validates operational workflows.
- [ ] Compliance validates reports and audit exports.
- [ ] Rollback plan rehearsed.
- [ ] Production monitoring and incident response are active.

## Current scope decision

Current OpFin implementation is below replacement scope. It should continue as a foundation build until the cutover scope above is implemented and verified.
