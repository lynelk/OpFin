# Production Readiness Matrix

Date: 2026-05-22

| Capability | Frontend status | Risk | Replacement verdict |
| --- | --- | --- | --- |
| Login | Partial | High | Backend login wired; production recovery/session hardening pending. |
| Admin login | Partial | High | Demo/admin shortcuts are guarded but production auth hardening remains. |
| Route protection | Partial | High | Token cookie is required, but runtime token validity still depends on backend API checks. |
| Role-aware navigation | Partial | Medium | Implemented, but backend authorization remains source of truth. |
| Customer dashboard | Partial/demo | High | Demo-oriented; production loan/payment/account details incomplete. |
| KYC status | Partial | Critical | Reads status only; no production KYC workflow. |
| Consent management | Demo only | Critical | Demo consent is not production consent management. |
| Loan application | Partial | Critical | Demo submit exists; production eligibility/state workflow incomplete. |
| Decision result | Demo only | Critical | Mock decisioning; no production manual review/referral flow. |
| Loan offer | Demo only/partial | Critical | Offer display exists; production disclosures and acceptance evidence incomplete. |
| Loan account | Partial/demo | Critical | Reads demo dashboard; production servicing incomplete. |
| Repayment schedule | Partial/demo | High | Displays schedule; production recalculation/payment context incomplete. |
| Repayments | Missing | Critical | No production repayment initiation/status flow. |
| Customer statements | Missing | Critical | Required for replacement. |
| Admin dashboard | Placeholder | High | Not replacement-grade. |
| Admin credit review | Partial/demo | Critical | Uses investor snapshot; no real queue/maker-checker. |
| Admin audit trail | Partial/demo | High | Uses demo snapshot; no search/export/filtering. |
| Support console | Missing | Critical | Required for replacement operations. |
| Reconciliation console | Missing | Critical | Required for live mobile money operations. |
| Compliance reports | Missing | Critical | Required before regulated rollout. |
| Savings | Placeholder | Low/Medium | Exclude unless live system requires it. |
| Insurance | Placeholder | Low/Medium | Exclude unless live system requires it. |
| Investments | Placeholder | Low/Medium | Exclude unless live system requires it. |
| Employer portal | Placeholder | Low/Medium | Exclude unless live system requires it. |
| Error states | Partial | Medium | Generic notices; field-level recovery needed. |
| Empty/loading states | Partial | Medium | Basic states exist. |
| Accessibility | Unknown/partial | Medium | Needs audit. |
| Mobile responsiveness | Unknown | Medium | Needs device/browser verification. |
| Tests/build | Unknown | Critical | npm unavailable; cannot verify. |

## Verdict

Critical rows block production replacement. The frontend should not be cut over until production backend contracts exist, mock mode is disabled in production, and checks pass.
