# Parallel Run Test Plan

Date: 2026-05-22

This plan defines how the current live system and new OpFin system are compared before replacement cutover. The current live system remains the source of truth during the parallel run.

## Parallel Run Modes

| Mode | Purpose | Writes allowed in new system |
| --- | --- | --- |
| Shadow read-only | Compare migrated/imported data and reports. | No customer-impacting writes. |
| Shadow processing | New system computes decisions, schedules, ledger, and reports from mirrored events. | Internal shadow records only. |
| Controlled pilot | Approved small cohort uses new system. | Only approved pilot workflows. |

## Data Set

Use the same records in both systems:

- Same customer records.
- Same admin and operations users.
- Same products and product terms.
- Same KYC and consent records.
- Same active loans.
- Same repayment schedules.
- Same payment records.
- Same ledger or opening balance records.
- Same reports and reporting periods.
- Same provider settlement/status files.

## Comparison Tests

| ID | Area | Data required | Test steps | Expected result | Pass/fail criteria | Sign-off owner |
| --- | --- | --- | --- | --- | --- | --- |
| PAR-01 | Customer records | Full migrated customer set | Export customer counts and key fields from both systems. | Counts and required fields match. | Pass if 100% match or approved exception list. | Product owner |
| PAR-02 | KYC records | KYC source/target records | Compare status, provider reference, review date, expiry. | KYC state matches per customer. | Pass if every mismatch is explained and approved. | Compliance lead |
| PAR-03 | Consent records | Consent source/target records | Compare purpose, version, status, grant/revoke timestamps. | Consent state matches. | Pass if revoked consent blocks new credit processing. | Compliance lead |
| PAR-04 | Active loans | Active loan portfolio | Compare loan IDs/mapping, status, principal, interest, fees, arrears, outstanding. | Active loan balances match. | Pass if 100% match or finance-approved correction. | Finance lead |
| PAR-05 | Repayment schedules | Active loan schedules | Compare schedule row count, due dates, principal, interest, paid/outstanding. | Schedules match row by row. | Pass if every active schedule matches or approved adjustment exists. | Finance lead |
| PAR-06 | Payment records | MTN/Airtel/payment records | Compare internal ref, provider ref, direction, amount, status, timestamps. | Payment records match or exceptions are listed. | Pass if all active/pending/recent provider refs are mapped. | Operations lead |
| PAR-07 | Outstanding balances | Active loan balances | Compare customer-visible and admin-visible balances. | Same outstanding balance in old and new systems. | Pass if finance signs off on every difference. | Finance lead |
| PAR-08 | Ledger entries | Ledger/opening balance records | Compare opening balances, disbursement entries, repayment entries, corrections. | New ledger is balanced and maps to old accounting records. | Pass if trial balance is signed off. | Finance lead |
| PAR-09 | Reports | Same reporting periods | Generate KYC, consent, credit, loan book, payment settlement, ledger, audit reports. | New reports match old reports or approved mapping differences. | Pass if compliance/finance approve totals and samples. | Compliance lead |
| PAR-10 | Admin actions | Standard admin workflow set | Perform same review/status/support actions where parallel mode allows. | Status transitions and audit logs are equivalent. | Pass if every action is authorized and audited. | Operations lead |
| PAR-11 | Error handling | Simulated failed provider/callback/server errors | Trigger failures in staging. | New system records safe exception states and does not corrupt balances. | Pass if no duplicate/missing ledger or payment state occurs. | Engineering lead |

## Daily Parallel Run Checklist

1. Confirm source data extract timestamp.
2. Import or mirror data into new staging system.
3. Run record count comparison.
4. Run active loan balance comparison.
5. Run repayment schedule comparison.
6. Run payment/provider reference comparison.
7. Run ledger/trial balance comparison.
8. Generate report comparisons.
9. Review new defects and exceptions.
10. Assign owners and target dates.
11. Record daily sign-off or blocker status.

## Acceptance Thresholds

| Area | Threshold |
| --- | --- |
| Same customer records | 100% match or approved exception list. |
| Same active loans | 100% match. |
| Same outstanding balances | 100% match or finance-approved correction. |
| Same repayment schedules | 100% match for active loans or finance-approved correction. |
| Same payment records | 100% mapped for active, pending, failed, reversed, and recent successful records. |
| Same reports | Totals match or compliance/finance-approved mapping difference. |
| Same admin actions | Equivalent state transition and audit evidence for every tested action. |

## Exit Criteria

Parallel run passes when:

- Acceptance thresholds are met for the agreed run period.
- Critical and High defects are closed.
- Remaining Medium/Low defects are approved with owners and dates.
- Finance signs off balances, ledger, and reconciliation.
- Compliance signs off KYC, consent, audit, and reports.
- Operations signs off admin/support workflows.
- Engineering signs off technical readiness.
