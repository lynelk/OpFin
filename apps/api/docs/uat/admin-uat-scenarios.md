# Admin UAT Scenarios

Date: 2026-05-22

Primary sign-off owner: Operations lead
Supporting owners: Compliance lead, finance lead, engineering lead

## Admin Test Data

- Platform admin user.
- Operations user.
- Support user.
- Compliance/reporting user if applicable.
- Customers covering verified KYC, pending KYC, active loans, failed payments, and revoked consent.
- Imported audit logs and ledger records.

## Scenarios

| ID | Flow | Data required | Test steps | Expected result | Pass/fail criteria | Sign-off owner |
| --- | --- | --- | --- | --- | --- | --- |
| ADMIN-01 | Login | Platform admin credentials | Open admin login, submit credentials. | Admin dashboard loads. | Pass if authenticated and correct admin navigation appears. | Operations lead |
| ADMIN-02 | Invalid admin login | Admin phone, wrong password | Submit wrong password. | Error displays and session is not created. | Pass if protected admin pages remain inaccessible. | Engineering lead |
| ADMIN-03 | Access restriction | Customer credentials | Login as customer and open admin route. | Customer is redirected or forbidden. | Pass if admin data is not displayed. | Engineering lead |
| ADMIN-04 | Customer search | Customer A/D/E records | Search by phone, name, customer ID, national ID where permitted. | Matching customers display with masked sensitive fields. | Pass if search results match source and masking rules hold. | Support lead |
| ADMIN-05 | Customer profile review | Customer D | Open customer profile. | Profile, KYC, consent, applications, loans, payments, support cases show. | Pass if displayed data matches source and role permissions. | Support lead |
| ADMIN-06 | KYC review | Customer B pending KYC | Open KYC review queue, approve or reject. | KYC status updates, reviewer/timestamp stored, audit log created. | Pass if customer status updates and audit log exists. | Compliance lead |
| ADMIN-07 | Consent review | Customer C/F | Open consent records. | Purpose, version, channel, status, grant/revoke timestamps display. | Pass if consent records match source and revoked status is visible. | Compliance lead |
| ADMIN-08 | Loan application review | Pending application | Open review queue, inspect application, update allowed status. | Application status updates according to role and workflow. | Pass if unauthorized roles cannot update and audit log exists. | Operations lead |
| ADMIN-09 | Decision review | Application with decision | Open decision details. | Status, reason codes, policy inputs, reviewer/manual review status display. | Pass if decision fields match backend record. | Compliance lead |
| ADMIN-10 | Loan account review | Active loan | Open loan account. | Principal, interest, fees, outstanding, status, dates display. | Pass if values match source and ledger/schedule links exist. | Finance lead |
| ADMIN-11 | Repayment schedule review | Active loan | Open repayment schedule. | Schedule rows display due dates, paid, outstanding, overdue state. | Pass if rows match source system and customer view. | Finance lead |
| ADMIN-12 | Ledger review | Loan with disbursement/repayment | Open ledger view. | Balanced ledger transactions and entries display. | Pass if debits equal credits and references map to payment/loan events. | Finance lead |
| ADMIN-13 | Audit trail review | Recent sensitive actions | Open audit trail, filter or inspect recent events. | Actor, subject, event, metadata, timestamp display. | Pass if sensitive action logs exist and no secrets are exposed. | Compliance lead |
| ADMIN-14 | Reports | Reporting data | Generate/view KYC, consent, credit, loan book, ledger, settlement reports. | Reports generate with correct period and totals. | Pass if totals match parallel-run report comparisons. | Compliance lead |
| ADMIN-15 | Error handling | Admin user | Trigger validation, forbidden, unauthenticated, server error states. | Safe error states display with no stack trace. | Pass if no secret or internal stack detail is exposed. | Engineering lead |

## Admin UAT Exit Criteria

- All admin roles can complete assigned workflows.
- Unauthorized roles are blocked.
- Audit logs are created for privileged actions.
- Reports and ledger views match source/comparison evidence.
