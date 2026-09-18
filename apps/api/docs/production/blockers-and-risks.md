# Current production blockers and risks

Updated: 18 September 2026

This replaces the May 2026 foundation-era list. Older statements such as “tests cannot run” or “compliance reporting is missing” are no longer current.

## External blockers

| Area | Required before claiming live |
| --- | --- |
| Legal lender | Verified licensed legal entity/trading name, UMRA licence/reference, business address |
| Identity | Production identity-provider credentials/certification |
| Credit reporting | Applicable authorised provider/mechanism schema, endpoint, credentials and certification |
| Affordability | Verified income/obligation feed for automatic approval |
| Payments | CPay production credentials/certification and callback health |
| Complaints | Official complaint contacts and staffed 30-day process |
| WhatsApp | Meta production credentials if launched |
| USSD | Aggregator/short-code provisioning if launched |
| Store | Signed candidate, declarations, reviewer evidence |
| Accessibility | Real-device TalkBack/VoiceOver/large-text/PWD-assisted UAT |
| Infrastructure | Private KYC storage, backups/restore evidence, monitoring/alerts |

## Operational risks and controls

| Risk | Control |
| --- | --- |
| External score/KYC source unavailable | Pending/referral; no fabricated success |
| Missing bureau consent | Outbound reporting blocked |
| Incomplete bureau identity data | Data-quality hold |
| Late/failed bureau report | Due date, retries, failure reason, admin register |
| Complaint SLA breach | Regulatory due date, breach flag, operations alert |
| Default-interest overcharge | NPL/default-interest cap tracking/enforcement/reporting |
| Silent rate change | Direct mutation blocked; governed maker-checker + UMRA evidence |
| Duplicate payment | Idempotency and provider finality |
| Receipt before finality | Receipt side effect after committed final transaction |
| Reconciliation drift | Persistent exception; no auto-balancing fiction |
| Stale API/docs | CI documentation-drift check |
| Long-range feature shown as live | Capability/provider gate |

## No production shortcuts

Do not:

- enable mock/demo behaviour in production;
- treat provider acknowledgement as finality;
- manually mark blocked credit reports submitted;
- alter accepted offer pricing;
- bypass governed term changes;
- issue final receipts for pending transactions;
- ask customers/guarantors for PIN/OTP through support;
- hide an NPL/default-interest breach by disabling visibility.
