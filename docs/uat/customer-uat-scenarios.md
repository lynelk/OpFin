# Customer UAT Scenarios

Date: 2026-05-22

Primary sign-off owner: Product owner
Supporting owners: Support lead, compliance lead, engineering lead

## Customer Test Data

- Customer A: active account, verified KYC, active consent, no active loan.
- Customer B: pending KYC.
- Customer C: verified KYC, no credit-processing consent.
- Customer D: active loan with repayment schedule.
- Customer E: failed or pending payment.
- Customer F: revoked consent.

## Scenarios

| ID | Flow | Data required | Test steps | Expected result | Pass/fail criteria | Sign-off owner |
| --- | --- | --- | --- | --- | --- | --- |
| CUST-01 | Login | Customer A credentials | Open frontend, enter phone/password, submit login. | Customer is authenticated and lands on dashboard. | Pass if dashboard loads and no admin navigation is visible. | Product owner |
| CUST-02 | Invalid login | Customer A phone, wrong password | Enter valid phone and wrong password. | Error message is shown and no session is created. | Pass if user remains unauthenticated and error is clear. | Product owner |
| CUST-03 | Session expiry | Customer A expired/revoked token | Revoke/expire token, open protected route. | User is redirected to login or shown unauthorized state. | Pass if protected data is not displayed. | Engineering lead |
| CUST-04 | Profile | Customer A | Login, open profile/dashboard customer details. | Name, phone, role, institution, and allowed profile fields match source data. | Pass if fields match migrated record and sensitive fields are not overexposed. | Support lead |
| CUST-05 | KYC status verified | Customer A | Open KYC screen. | Verified KYC status displays with masked sensitive ID. | Pass if status matches source and National ID/NIN is masked. | Compliance lead |
| CUST-06 | KYC pending | Customer B | Open KYC screen. | Pending status and review guidance display. | Pass if no verified-only actions are enabled. | Compliance lead |
| CUST-07 | Submit KYC evidence | Customer B | Submit allowed KYC evidence. | KYC case is created as pending review and audit logged. | Pass if operations can see case and customer sees pending status. | Compliance lead |
| CUST-08 | Consent grant | Customer C | Open consent screen, grant credit-processing consent. | Consent record is created with purpose, policy version, channel, and timestamp. | Pass if status becomes granted and audit log exists. | Compliance lead |
| CUST-09 | Consent revoke | Customer F or Customer C after grant | Revoke consent. | Consent status becomes revoked and future credit processing is blocked. | Pass if new loan processing is blocked until consent is granted again. | Compliance lead |
| CUST-10 | Loan application valid | Customer A | Start application, choose product/term, enter amount/reason, submit. | Application is created and customer receives status. | Pass if application fields match submission and audit log exists. | Product owner |
| CUST-11 | Loan application missing KYC | Customer B | Attempt loan application. | Submission is rejected or referred due missing verified KYC. | Pass if no loan proceeds to decisioning without KYC. | Compliance lead |
| CUST-12 | Loan application missing consent | Customer C | Attempt loan application without consent. | Submission or decisioning is blocked/referred due missing consent. | Pass if no credit processing occurs without consent. | Compliance lead |
| CUST-13 | Loan decision approved | Customer A with eligible data | Submit application or open decision. | Decision is displayed with clear status and reason codes. | Pass if status/reason codes match backend decision record. | Product owner |
| CUST-14 | Loan decision declined/referred | Customer with adverse/manual-review data | Submit/open decision. | Declined or referred status and reason codes display. | Pass if customer sees clear, non-misleading result. | Product owner |
| CUST-15 | Loan offer view | Customer with approved offer | Open offer screen. | Principal, repayment amount, duration, rate, expiry, and disclosure display. | Pass if all terms match backend offer record. | Product owner |
| CUST-16 | Loan offer acceptance | Customer with pending offer | Accept offer. | Loan account is created, schedule generated, disbursement initiated/recorded, audit logs exist. | Pass if financial state changes are atomic and ledger entries balance. | Finance lead |
| CUST-17 | Loan account view | Customer D | Open loan account screen. | Active loan, outstanding balance, status, and key dates display. | Pass if values match source/migrated loan state. | Finance lead |
| CUST-18 | Repayment schedule view | Customer D | Open schedule screen. | Due dates, principal, interest, paid/outstanding state display. | Pass if schedule matches source system row by row. | Finance lead |
| CUST-19 | Payment status view | Customer E | Open payment/loan account status. | Pending/failed/successful payment state is visible. | Pass if provider/internal status matches payment records. | Operations lead |
| CUST-20 | Notifications | Customer with recent application/payment | Trigger or inspect notification history. | Customer sees expected SMS/email/in-app notification state if supported. | Pass if notification content/status matches live rules. | Support lead |
| CUST-21 | Error handling | Customer A | Force validation, unauthorized, forbidden, server unavailable cases in staging. | User receives safe, recoverable messages with no stack trace or secret exposure. | Pass if no sensitive data is shown and route remains stable. | Engineering lead |

## Customer UAT Exit Criteria

- All scenarios pass or have accepted non-critical defects.
- No Critical or High defect remains open.
- Product, support, compliance, finance, and engineering owners sign off where assigned.
