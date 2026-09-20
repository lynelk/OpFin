# OpFin Operational Manual

Version: 20 September 2026
Audience: OpFin operations, support, finance, compliance and authorised institutional administrators

## Operating model
OpFin separates Person identity, Financial Spaces, membership/roles, capabilities, entitlements and product eligibility. CPay is the execution/reconciliation boundary where configured for money movement. Revenue events attribute commercial economics and do not replace the financial ledger.

## Daily controls
Review health/readiness, worker and scheduler status, provider callbacks, reconciliation queues, failed financial actions, support cases, KYC/consent exceptions and security alerts. Never resolve a money-state exception by editing customer-facing status without provider/ledger evidence.

## Financial Space administration
A person can belong to many Spaces. Validate role before any administrative action. Personal Space information must not be disclosed to employers, groups or other organisations because of membership alone.

## Institutional onboarding
Business/SACCO/Fund/Partner progression is profile → KYB → regulatory evidence → products → integration → certification. Do not activate regulated product distribution merely because a technical capability is enabled.

## Employer operations
Employer is a capability on a Business Space. Employment relationships may support benefits or financial-wellness services, but employee Personal Space data remains private unless a specific lawful consent/process authorises data sharing.

## Partner Catalogue
Maintain partner status, product lifecycle, territory, eligibility, pricing/disclosures and integration configuration. Customer need/eligibility/suitability precedes commercial economics.

## Revenue operations
Revenue event types include subscription, commission, revenue share, transaction, platform fee, API fee and servicing. Reconcile each applicable event to CPay/provider references and settlement evidence. Investigate duplicates through source idempotency rather than deleting evidence.

## Incident handling
For partner outage, preserve pending state, avoid duplicate execution, retry only under idempotent rules, reconcile provider truth, communicate customer-safe status and retain audit evidence. Escalate suspected financial-integrity or privacy incidents immediately.

## Release operations
A release requires migrations, API/client tests, cross-Space isolation, financial integrity/idempotency, partner failure/recovery, accessibility/mobile-completeness evidence, revenue reconciliation and documentation alignment. Production success is not a substitute for CI evidence.
