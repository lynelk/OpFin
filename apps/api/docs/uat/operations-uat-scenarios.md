# Operations UAT scenarios

Updated: 18 September 2026

Primary sign-off: Operations lead  
Supporting: Finance, Support, Compliance, Engineering

| ID | Flow | Scenario | Expected result |
| --- | --- | --- | --- |
| OPS-01 | Credit review | Referred application | KYC/consent/CRB/profile/affordability context visible and decision audited |
| OPS-02 | Provider outage | Disbursement/collection provider unavailable | No false finality or ledger mutation |
| OPS-03 | Duplicate callback | Replay same provider event | One economic transition/ledger posting only |
| OPS-04 | Repayment retry | Same idempotency key, same instruction | Safe replay; no duplicate collection |
| OPS-05 | Reconciliation | Matching provider/system record | Matched status with evidence |
| OPS-06 | Reconciliation | Amount/reference mismatch | Persistent exception; no manufactured balancing entry |
| OPS-07 | Reversal | Provider reversal of unrepaid disbursement | Append-only reversal state/ledger/reporting correction |
| OPS-08 | Receipt | Final transaction completes | E-receipt generated once after commit/finality; acknowledgement queued |
| OPS-09 | Complaint | New complaint | 30-day regulatory due date recorded |
| OPS-10 | Complaint | Case within five days of deadline | Operations work item/visibility raised |
| OPS-11 | Complaint | Deadline passes unresolved | SLA breach flag appears; escalation remains open |
| OPS-12 | Credit reporting | Scheduled run with valid eligible record | Provider called idempotently and reference stored |
| OPS-13 | Credit reporting | Consent revoked/missing | External submission blocked |
| OPS-14 | Credit reporting | Invalid/incomplete identity | Data-quality hold |
| OPS-15 | NPL | Scheduled UMRA control scan | Overdue loan evaluated and reporting event queued |
| OPS-16 | NPL | Accrual would exceed cap | Blocked when enforcement enabled |
| OPS-17 | Term change | Direct product-rate update attempt | Blocked; governed workflow required |
| OPS-18 | Term change | Approved future change | Maker-checker + UMRA evidence retained |
| OPS-19 | Guarantor | Independent confirm/reject | Status/evidence retained; borrower cannot self-confirm |
| OPS-20 | Regulatory report | Generate/open evidence pack | Validation/hash/payload visible before officer approval |
| OPS-21 | Security | Staff attempts to obtain customer PIN/OTP | Procedure rejects this; no credential capture field/log |
| OPS-22 | PWD support | Assisted KYC case | Support assistance does not lower KYC or require secret sharing |

## Exit criteria

- Operations can complete daily credit/payment/support/regulatory workflows without direct database edits.
- Finance signs off ledger/reconciliation/receipt integrity.
- Compliance signs off complaint, NPL, bureau, guarantor and term-change controls.
- No Critical/High defect remains open.
