# Current web/product blockers and risks

Updated: 18 September 2026

This replaces the May 2026 scaffold-era blocker list. Many earlier items are now implemented; historical versions remain in Git history.

## External launch blockers

| Area | Required before claiming live |
| --- | --- |
| Legal lending identity | Confirm licensed entity/trading name, UMRA licence/reference and business address |
| Credit information | Configure/certify applicable authorised reporting endpoint and schema |
| Identity | Production identity-provider credentials/certification |
| Affordability | Verified income/obligation data source for automatic approval |
| Payments | Production CPay credentials/certification and callback health |
| Complaints | Official complaints email/phone/URL and staffed process |
| WhatsApp/USSD | Provider credentials/provisioning if launched |
| Store | Final declarations, signed artefact and reviewer path |
| Accessibility | Physical-device TalkBack/VoiceOver/large-text/PWD-assisted UAT |

## Product/operational risks

| Risk | Control |
| --- | --- |
| External source unavailable | Fail closed/pending/referral; no invented KYC/score/income |
| Credit report late/failed | Outbound register, due date, retries, admin counts |
| Incomplete bureau data | Data-quality hold |
| Missing reporting consent | External submission blocked |
| Complaint SLA breach | Regulatory due date + autopilot/admin alert |
| NPL/default-interest overcharge | Cap tracking/enforcement + breach reporting |
| Unilateral rate change | Direct edit blocked; maker-checker + UMRA approval evidence |
| Duplicate payment | Idempotency + provider finality |
| Receipt for rolled-back transaction | Receipt/report side effects run after financial commit |
| Stale documentation | CI documentation-drift check |

## Production guardrails

- Mock/demo flags remain disabled.
- Customer UI does not claim pending money has settled.
- Accepted offers remain immutable.
- Long-range features remain capability gated.
- Regulatory reports require responsible-officer review before external filing.
