# Admin UAT scenarios

Updated: 18 September 2026

Primary sign-off: Operations lead  
Supporting: Compliance, Finance, Support, Engineering

Use authorised test data only. No Critical/High defect may remain open at production release.

| ID | Area | Scenario | Expected result |
| --- | --- | --- | --- |
| ADMIN-01 | Access | Platform admin/operations/support login and role restrictions | Correct navigation; unauthorised roles receive 403/no sensitive data |
| ADMIN-02 | Customer | Search/review customer with masked sensitive fields | Profile, KYC, consent, loans, payments, support visible according to role |
| ADMIN-03 | KYC | Review pending/inconclusive KYC | Review decision, reviewer/time and audit event persist |
| ADMIN-04 | Credit | Inspect composite score/decision evidence | Components, coverage, affordability and reason codes match backend |
| ADMIN-05 | Offer | Review formal disclosure snapshot | Pricing, fees, default terms, complaints and reporting disclosure match immutable offer |
| ADMIN-06 | Complaints | Open customer complaint | Regulatory due date and procedure visible |
| ADMIN-07 | Complaints | First response / near-deadline / overdue case | First response stored; approaching/overdue SLA surfaced |
| ADMIN-08 | Credit reporting | View outbound reporting register | Positive/negative, status, due date, attempts/provider ref visible |
| ADMIN-09 | Credit reporting | Missing consent | Record remains blocked; no external request sent |
| ADMIN-10 | Credit reporting | Incomplete verified identity | Record remains data-quality blocked |
| ADMIN-11 | Credit reporting | Valid consent/data/provider | Submission becomes submitted with provider reference |
| ADMIN-12 | NPL | Evaluate overdue loan | Loan gets NPL date/principal-at-NPL/cap values; overdue schedule status updated |
| ADMIN-13 | NPL | Accrue within default-interest ceiling | Amount accepted and control evidence updates |
| ADMIN-14 | NPL | Attempt above ceiling with enforcement on | Request rejected; no hidden balance mutation |
| ADMIN-15 | NPL | Toggle enforcement | Only authorised admin path succeeds; state remains visible/reportable |
| ADMIN-16 | Guarantor | Add two guarantors | Both invitations pending with independent confirmation |
| ADMIN-17 | Guarantor | Attempt third contact | Rejected; address book not accessed |
| ADMIN-18 | Term governance | Attempt direct interest-rate edit | Blocked |
| ADMIN-19 | Term governance | Maker submits / checker approves unchanged-rate terms | Governed request proceeds |
| ADMIN-20 | Term governance | Interest-rate change without UMRA approval evidence | Cannot approve/apply |
| ADMIN-21 | Term governance | Interest-rate change with UMRA reference/date | Applies to future term after maker-checker; accepted offers unchanged |
| ADMIN-22 | Receipts | Inspect final disbursement/repayment | Exactly one receipt per final event with receipt hash/reference |
| ADMIN-23 | Compliance reports | Generate UMRA digital-credit evidence packs | Report validates and appears in register |
| ADMIN-24 | Books/records | Open generated books-and-records report | Loan/payment/ledger/receipt/complaint/register data inspectable |
| ADMIN-25 | Maker-checker | Same officer attempts incompatible maker/checker action | Blocked |
| ADMIN-26 | Audit | Inspect privileged actions | Actor, subject, reason/evidence timestamp visible; secrets absent |
| ADMIN-27 | Error safety | Trigger validation/forbidden/provider error | Safe actionable error; no stack trace/secret/raw identity image |

## Exit criteria

- All regulated/admin controls above pass on the exact candidate.
- Compliance and Finance reconcile sampled report/receipt/ledger figures to source tables.
- No route permits a silent interest-rate mutation.
- No report can be represented as externally filed merely because it was generated.
