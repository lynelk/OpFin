# Production Readiness Matrix

Date: 2026-05-22

Legend:

- Ready: production use can be considered after normal verification.
- Partial: useful implementation exists but cannot be relied on for replacement cutover.
- Demo only: built for investor/demo use, not production.
- Missing: not implemented enough for production.
- Unknown: cannot be verified in this environment.

| Capability | Backend | Frontend | Risk | Replacement verdict |
| --- | --- | --- | --- | --- |
| Health check | Partial | N/A | Medium | Needs deployment/runtime monitoring. |
| Login/authentication | Partial | Partial | High | Token auth exists; hardening and runtime verification required. |
| Session protection | Partial | Partial | High | Frontend guard can pass role-only cookie; backend must remain source of truth. |
| RBAC | Partial | Partial | High | Roles exist; full policy coverage required. |
| Customer profile | Partial | Partial | Medium | Basic view exists; update/support workflows incomplete. |
| KYC | Partial | Partial | Critical | No full production provider lifecycle or evidence workflow. |
| Consent | Demo only | Demo only | Critical | Demo consent is not production consent management. |
| Product catalog | Partial | Partial | High | Products/terms exist; eligibility and disclosure controls incomplete. |
| Loan application | Partial | Partial | Critical | Legacy and demo paths coexist; production state machine required. |
| Affordability | Demo only | Demo only | Critical | Mock rules only. |
| CRB integration | Partial/missing | Missing | Critical | Not safe for production credit decisions. |
| Decisioning | Demo only | Demo only | Critical | No production policy engine or manual review governance. |
| Loan offers | Demo only/partial | Partial | Critical | Offer lifecycle and disclosure acceptance evidence incomplete. |
| Loan account creation | Partial | Partial | Critical | Needs atomic production invariants and ledger coupling. |
| Repayment schedules | Partial | Partial | High | Exists, but edge cases and production recalculation rules incomplete. |
| Ledger | Partial | Partial admin view | Critical | Decimal money, no complete immutable balanced-posting guarantee. |
| Mobile money disbursement | Sandbox/partial | Demo only | Critical | Mock adapter and placeholders cannot process live money safely. |
| Mobile money collections | Partial legacy/sandbox | Missing | Critical | Production collections/reconciliation workflow incomplete. |
| Webhooks/idempotency | Partial | N/A | High | Adapter layer exists; production provider behavior unverified. |
| Reversals | Partial/sandbox | Missing | High | Operational reversal workflow missing. |
| Reconciliation | Partial | Partial admin snapshot | Critical | No production reconciliation console/reporting. |
| Audit logging | Partial | Partial admin view | High | Audit coverage/export/retention incomplete. |
| Admin dashboard | Partial legacy/demo | Placeholder | High | Production operations console missing. |
| Credit review | Partial/demo | Partial/demo | Critical | Needs real queue, maker-checker, reasoned decisions. |
| Customer support | Missing/partial legacy | Missing | Critical | No replacement-grade support console. |
| Compliance reporting | Missing | Missing | Critical | Required before regulated rollout. |
| Savings | Missing | Placeholder | Medium | Out of replacement scope unless live system has it. |
| Insurance | Missing | Placeholder | Medium | Out of replacement scope unless live system has it. |
| Investments | Missing | Placeholder | Medium | Out of replacement scope unless live system has it. |
| Employer portal | Missing | Placeholder | Medium | Out of replacement scope unless live system has it. |
| API consistency | Partial | Partial | High | Legacy response shapes vary. |
| Error handling | Partial | Partial | Medium | Needs global backend and field-level frontend handling. |
| Accessibility | N/A | Unknown/partial | Medium | Needs browser audit. |
| Mobile responsiveness | N/A | Unknown | Medium | Needs browser/device audit. |
| Tests | Unknown | Unknown | Critical | Toolchain unavailable; cannot verify. |
| Builds | Unknown | Unknown | Critical | Toolchain unavailable; cannot verify. |
| Fresh migrations | Unknown | N/A | Critical | Toolchain unavailable; cannot verify. |

## Replacement readiness summary

Critical replacement areas are not ready: KYC, consent, credit decisioning, CRB, ledger, live mobile money, reconciliation, support operations, compliance reporting, tests/builds, and migration validation.

The current implementation should not be presented as a production replacement until every Critical row is either resolved or explicitly removed from replacement scope with business approval.
