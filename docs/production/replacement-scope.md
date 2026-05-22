# Replacement Scope

Date: 2026-05-22

## Required frontend scope for cutover

The frontend replacement must cover every current live-system workflow that customers, operations, support, and compliance teams depend on.

## Customer scope

- Login, logout, account recovery.
- Customer dashboard with real balances, application status, and payment state.
- Profile and KYC status.
- KYC submission/retry/review messaging.
- Consent capture, review, and revocation.
- Product selection and loan application.
- Application status tracking.
- Decision display with reason codes.
- Manual review/referral messaging.
- Offer disclosure and acceptance.
- Loan account detail.
- Repayment schedule.
- Repayment initiation and payment status.
- Customer statement and transaction history.
- Support/contact/escalation entry points.

## Admin and operations scope

- Admin dashboard with production metrics.
- Application review queue.
- Manual decision/referral workflow.
- Customer lookup.
- Loan account view.
- Payment/reconciliation exception queue.
- Audit trail search/filter/export.
- Mobile-money transaction status lookup.
- Support case management.
- Role-aware navigation and access boundaries.

## Compliance and reporting scope

- Consent register view/export.
- KYC register view/export.
- Credit decision report.
- Loan book and arrears reports if required.
- Mobile money settlement/reconciliation report.
- Audit export.

## Out of replacement scope unless live system requires it

- Savings.
- Insurance.
- Investments.
- Employer portal.

## Current frontend gap

The current app only covers a demo-grade subset of customer credit flow and admin snapshot review. It is below replacement scope and must not be used as the live replacement without completing the scope above.
