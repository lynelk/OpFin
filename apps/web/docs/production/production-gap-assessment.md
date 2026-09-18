# Web production gap assessment

Updated: 18 September 2026

The May 2026 “investor-demo scaffold” assessment is superseded.

## Current position

The Next.js web application now supports production-shaped customer and admin workflows backed by the current Laravel API, including borrower state, KYC/consent, formal offers, loan accounts, support/complaints, governance and regulatory evidence.

The primary remaining gaps are external activation and operational proof, not missing web scaffolding.

## Implemented web/admin areas

- phone/PIN account access and protected portal;
- customer borrower-state views;
- KYC/consent;
- loan application/decision/offer/account;
- exact formal offer disclosures and credit-reporting consent;
- support/complaint entry;
- admin credit review;
- reconciliation and ledger review;
- support/complaint SLA;
- Compliance Centre with UMRA control metrics;
- regulatory report generation/register/detail;
- current production mock/demo guards.

## Remaining external/operational gates

- production legal/lender/UMRA identity;
- identity/bureau/affordability/CPay provider activation;
- official complaint contacts;
- production monitoring/backups/restore evidence;
- real-device/accessibility UAT;
- signed store/reviewer evidence;
- final operational/regulatory sign-off.

## Production rule

Do not enable mock API or demo shortcuts in production. Do not represent a capability as live when its external provider/regulatory gate is not active.

For current blockers use `blockers-and-risks.md`; for API contracts use `../../../../apps/api/docs/api/current-endpoints.md`.
