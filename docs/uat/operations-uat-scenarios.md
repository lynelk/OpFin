# Operations UAT Scenarios

Date: 2026-05-22

Primary sign-off owner: Operations lead
Supporting owners: Finance lead, support lead, compliance lead, engineering lead

## Operations Test Data

- Pending manual review applications.
- Referred credit decisions.
- Failed mobile money disbursement.
- Failed mobile money collection.
- Pending provider callback.
- Duplicate webhook/payment event.
- Reversal candidate.
- Reconciliation exception.
- Escalated support case.

## Operations Scenarios

| ID | Flow | Data required | Test steps | Expected result | Pass/fail criteria | Sign-off owner |
| --- | --- | --- | --- | --- | --- | --- |
| OPS-01 | Manual review queue | Referred application | Open manual review queue, inspect application and decision reason codes. | Queue lists referred items with customer, KYC, consent, affordability/CRB context. | Pass if queue matches backend state and no unauthorized user can act. | Operations lead |
| OPS-02 | Manual review decision | Referred application | Approve, decline, or keep referred according to policy. | Decision state changes and audit log records actor/reason. | Pass if maker-checker rules apply if required. | Compliance lead |
| OPS-03 | Payment exception handling | Payment exception | Open reconciliation/exception item. | Exception details show provider/internal refs, amount, status, notes. | Pass if item can be resolved only by allowed role and audit log exists. | Operations lead |
| OPS-04 | Failed transaction handling | Failed disbursement or collection | Open failed transaction, inspect provider response, mark next action. | Failure reason, retry/reversal/escalation status visible. | Pass if no duplicate ledger entry is created. | Finance lead |
| OPS-05 | Duplicate webhook handling | Duplicate provider event | Replay duplicate webhook/event in staging. | Duplicate is ignored or marked duplicate. | Pass if one and only one ledger/payment state change exists. | Engineering lead |
| OPS-06 | Reversal review | Reversal candidate | Open reversal request, review evidence, approve/reject. | Reversal state changes with audit and ledger correction path. | Pass if balances remain correct and provider status is tracked. | Finance lead |
| OPS-07 | Support escalation | Escalated support case | Open support case, add internal note, assign owner, change status. | Notes and assignment persist; customer-impacting action is audited. | Pass if support and operations roles have correct access. | Support lead |
| OPS-08 | Reconciliation run | MTN/Airtel unreconciled records | Create reconciliation run for provider/business date. | Run creates items for unmatched/unreconciled transactions. | Pass if item count matches provider/system input. | Finance lead |
| OPS-09 | Reconciliation match | Reconciliation item | Match item with provider amount/reference. | Item and linked mobile money transaction become matched. | Pass if status updates and audit log records resolver. | Finance lead |
| OPS-10 | Reconciliation exception | Mismatched item | Mark item as exception with notes. | Exception remains open for finance review. | Pass if mismatch is visible in reports and not silently written off. | Finance lead |
| OPS-11 | Failed provider outage | Provider sandbox unavailable | Attempt status lookup or callback processing during simulated outage. | System records failure safely and prompts retry/escalation. | Pass if no balance mutation occurs from failed provider call. | Engineering lead |
| OPS-12 | Operational error handling | Operations user | Trigger validation/forbidden/server error states. | Safe, actionable message displays. | Pass if no stack trace, secret, or sensitive provider payload is exposed. | Engineering lead |

## Employer Admin Scenarios, If Applicable

Run these only if employer-linked benefits or employer eligibility are active in the live system.

| ID | Flow | Data required | Test steps | Expected result | Pass/fail criteria | Sign-off owner |
| --- | --- | --- | --- | --- | --- | --- |
| EMP-01 | Employer login | Employer admin user | Login and open employer portal. | Employer admin can access employer-only area. | Pass if customer/admin-only data is not visible. | Employer benefits owner |
| EMP-02 | Employee verification | Employee records | Search or verify employee eligibility. | Eligible/ineligible status is shown from approved source. | Pass if status matches live employer data. | Employer benefits owner |
| EMP-03 | Eligibility status | Employee with active/inactive employment | Open eligibility detail. | Employment and benefit eligibility reflect source system. | Pass if inactive employees cannot access employer-only benefits. | Employer benefits owner |
| EMP-04 | Aggregated reporting | Employer cohort | View aggregated report. | Aggregated counts/totals display without exposing unrelated customer PII. | Pass if report matches live system and privacy rules. | Compliance lead |
| EMP-05 | Access restrictions | Employer admin | Attempt access to other employer/customer/admin data. | Access is denied. | Pass if unauthorized records are not displayed. | Engineering lead |

## Operations UAT Exit Criteria

- Operations can resolve daily payment, review, reconciliation, and support workflows.
- Finance signs off on payment and ledger behavior.
- Support signs off on escalation behavior.
- No Critical or High operations defects remain open.
