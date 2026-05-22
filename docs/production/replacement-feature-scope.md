# Replacement Feature Scope

Date: 2026-05-22

This document classifies replacement features for the live-system cutover. If discovery shows a feature is active in the current live system, it moves into "Must exist before cutover" even if it is listed elsewhere here.

## Must Exist Before Cutover

| Capability | Required production behavior | Acceptance evidence |
| --- | --- | --- |
| Customer authentication | Login, logout, reset/recovery, token lifecycle, lockout controls. | Auth tests, support recovery test, failed-login test. |
| Admin authentication | Privileged login, role enforcement, support-safe recovery. | RBAC tests for platform admin, operations, support, employer admin if active. |
| Customer profile | Accurate migrated profile and customer-visible details. | Source-target profile count and field comparison. |
| KYC | Provider-backed or approved manual workflow, evidence, expiry, review, retry, audit. | KYC status comparison and review workflow test. |
| Consent | Purpose/version capture, revocation, evidence, reporting. | Consent report and revoked-consent gate test. |
| Product catalog | Active products, terms, rates, fees, eligibility, disclosures. | Product mapping sign-off and API/frontend tests. |
| Loan application | Submission, validation, status lifecycle, audit. | Application workflow test and migration count check. |
| Affordability and decisioning | Governed policy, CRB integration, reason codes, manual review. | Policy test pack, decision reason-code report. |
| Loan offers | Terms disclosure, expiry, acceptance evidence, cancellation. | Offer acceptance audit and disclosure evidence. |
| Loan account creation | Atomic account creation with ledger posting. | Transactional test and ledger balance evidence. |
| Repayment schedules | Accurate schedule generation/import, due dates, paid/outstanding state. | Schedule comparison report. |
| Repayment allocation | Principal, interest, fees, penalties if active, partial payments, overpayments. | Allocation tests and sample loan statements. |
| Mobile money disbursement | MTN/Airtel production-safe disbursement, idempotency, provider status. | Sandbox certification and provider status reconciliation. |
| Mobile money collection | MTN/Airtel production-safe collection, callbacks, replay protection. | Sandbox certification and duplicate webhook test. |
| Ledger | Immutable double-entry, integer minor units, balanced transactions, corrections/reversals. | Trial balance and ledger integrity tests. |
| Reconciliation | Provider settlement/status matching, exception workflow, finance sign-off. | Reconciliation report with approved exceptions. |
| Audit logging | Sensitive actions logged with actor, subject, metadata, request context. | Audit export and coverage tests. |
| Support operations | Customer lookup, support cases, payment/KYC/loan exception handling. | Support UAT checklist signed off. |
| Compliance reporting | KYC, consent, credit, loan book, ledger, settlement, audit exports. | Compliance report samples approved. |
| Monitoring and runbooks | Alerts, logs, queues, failed jobs, incident response. | Monitoring smoke test and runbook rehearsal. |

## Can Run In Parallel

| Capability | Parallel-run mode | Guardrail |
| --- | --- | --- |
| Credit decision simulation | New system computes shadow decisions beside live decisions. | Shadow decisions must not affect live offers until approved. |
| Ledger posting | New system posts shadow ledger entries from live events. | Shadow ledger must be read-only for reporting until reconciled. |
| Reconciliation | New system imports provider records and compares status. | Exceptions stay in review; no automatic balance changes. |
| Compliance reports | New system generates parallel reports. | Reports are compared against live reports before external use. |
| Admin dashboards | Operations can compare dashboards. | Live system remains source of truth until cutover. |
| Support case intake | Support can test case workflow. | Customer-impacting actions remain in live system until cutover. |

## Can Be Migrated Later

These can move after cutover only if they are not active in the live system:

- Savings products.
- Insurance products.
- Investment products.
- Employer-linked benefits.
- AI chat/RAG features.
- Marketing pages.
- Non-critical analytics dashboards.
- Historical report archives beyond the required regulatory retention window, if approved and preserved separately.

## Should Be Retired

| Item | Retirement condition |
| --- | --- |
| Demo routes and investor-demo flows | Retire or keep disabled in production after real workflows replace them. |
| Mock mobile money provider | Disable outside local/testing. |
| Sandbox login shortcuts | Disable outside local/testing. |
| Legacy decimal financial state mutation paths | Retire after all financial changes post through production ledger. |
| Placeholder admin/customer screens | Retire when production screens are complete. |
| Hardcoded demo credentials | Remove from production environments and public docs. |
| Unused product modules | Disable if savings, insurance, investments, or employer benefits are not active in the live system. |

## Scope Change Rules

1. If a live customer can use it today, it is cutover scope.
2. If it changes money, balances, loan state, consent, KYC, or credit decisions, it is cutover scope.
3. If operations need it to support customers, it is cutover scope.
4. If compliance or finance need it for reporting, it is cutover scope.
5. If it is not active in the live system, keep it disabled until built and approved.
