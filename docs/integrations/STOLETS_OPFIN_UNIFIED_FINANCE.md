# Stolets × OpFin merchant-finance integration and unified delivery

**Review:** 10 October 2026  
**Status:** Cross-repository target and **initial code-level preflight validation**. No live integration endpoint, lender approval, production activation, merchant app journey or completed financial acceptance is asserted.

The canonical OpFin repository is **`lynelk/OpFin`**, not the archived historical `OpFin-BE` and `OpFin-FE` repositories. Stolets is separately maintained in `lynelk/Stolets`. The [Stolets programme and shared contract](https://github.com/lynelk/Stolets/blob/feat/stolets-opfin-unified-programme-20261010/docs/integrations/STOLETS_OPFIN_UNIFIED_PROGRAMME.md) contains the full all-workstream plan.

## Responsibility and product position

OpFin must stay a personal-first financial wellbeing and services platform, not become a copy of Stolets POS. The merchant relationship should enable responsible finance while preserving ordinary business operations without loans.

- **Stolets:** sales, stock, merchant receivables/payables, supplier/PO fulfilment, financial-health insights, merchant-controlled consent and Business Passport, productive-capital capacity (PCC) advice.
- **OpFin `apps/api`:** independent financial identity, KYC, consent, suitability, lender-of-record governance, underwriting, borrower/investor state, product factory, financing arrangements, offers, accounting, repayments, hardship, collections and portfolio/investor books.
- **OpFin `apps/web`/`apps/client`:** borrower and investor journeys consuming authoritative OpFin API, contextual Stolets deep-link/return only where approved.
- **Cito/CPay:** authorised messaging/KYC-provider access and actual payment execution/finality. Do not bypass Cito for gnuGrid evidence or bypass approved money-movement/reconciliation rules.
- All jurisdictions, institutional lenders, product types, future principal exposure, regulated investment structures and supplier terms are separately gated. Ownership/affiliation is never a regulatory licence.

## Implementation slice now in this branch

`apps/api/app/Services/Stolets/MerchantStockFinanceHandoff.php` implements a pure, fail-closed **untrusted packet preflight**, paired with `apps/api/tests/Unit/MerchantStockFinanceHandoffTest.php`. It matches the deliberately minimal Stolets-domain `validateStoletsOpfinHandoff` contract v1.

It verifies exact envelope names and types, the intended purpose/recipient, a maximum 24-hour unexpired UTC envelope, a grant-reference validity window, evidence coverage/freshness within 30 days, explicit evidence-level provenance, opaque identifiers, safe-integer UGX minor-unit arithmetic and equality of finance + contribution to order total. Unknown fields (including unintended customer PII) fail closed.

**This validator does NOT prove consent or partner authenticity.** It never authenticates a service, queries Stolets, issues an OpFin loan, calculates credit limits, posts a ledger entry or initiates CPay. Its output is explicitly `preflightOnly: true`. It has **no attached HTTP route**. No demo/mock approval or payment is fabricated.

## Mandatory receiver controls before any future route

1. Allowlist the actual authorised Stolets client/legal sender, environment and OpFin recipient through reviewed server-side configuration and time-limited service credentials; use mTLS or a reviewed signature/JWT audience scheme over the exact payload bytes.
2. Bind sender, merchant tenant, legal recipient, action, nonce, timestamp, idempotency and request-body fingerprint; reject replay, cross-environment tokens and attempts to change a previously used instruction key.
3. Check the source Stolets **authoritative** grant is active, purpose-bound, recipient-specific and not revoked **at read time**, not merely marked `granted` inside the supplied packet. Obtain the exact immutable Business Passport share under that grant.
4. Independently link the human borrower to the approved business authority; verify OpFin's own identity/KYC and borrower consent. A phone match alone or a shared device is insufficient.
5. Validate supplier identity, real accepted purchase order, quality/freshness, amounts and real financial obligations; avoid inferred financial truth from a sales total or unsynchronised/offline event.
6. Compare Stolets PCC advice with OpFin's independent affordability, credit risk, debt-service and responsible-financing policies. Neither a PCC score nor high Stolets usage permits automatic approval or limit increase.
7. Apply actual lender-of-record/contract, product/territory/funding, regulatory, price disclosure, risk, treasury, signed acceptance and customer protection gates before creating a legal financing arrangement.
8. Make supplier disbursement only via the approved governed payment path with provider finality, immutable accounting and reconciliation. Preserve partial delivery, failure/timeout, supplier return/refund, hardship and dispute cases.
9. Disclose which institution lends, who carries credit risk, applicable commissions and which party handles complaints; do not favour a more expensive financing offer due to cross-product revenue.
10. Keep customer BNPL debts, merchant-issued tabs, actual supplier trade credit, consignment and rented shelf space distinct. Never turn a simple Stolets credit record into an OpFin facility without a separate application and contract.

## All-in-one engineering workstreams, independent activation

- Merchant stock-credit: order → finance-or-optimise → consent → OpFin decision → disclosed offer → contribution → supplier payment → goods receipt → scheduled servicing → reconciled close.
- Merchant business-cash-flow and equipment/asset finance: needs-based advice, appropriate purposes and loan type, affordability and non-automated limits.
- Customer purchase financing: approved purchase handoff from Stolets customer surface, exact return/refund/booking/deposit semantics and identity/consent separation.
- Suppliers/distributors: partner-based receivables finance, financed purchase orders, consignment and pay-on-sale without fictional loans.
- Investor/institutional funding: risk controls, lender-of-record and actual source-of-funds verification, private/institutional loan books, concentration caps, no overfunding or duplicate pledging.
- Responsible repayment: capped authorised sales-linked mandates, hardship, reversals, exact schedules, complaint and collections safeguards.
- Joint support: traceable cross-platform case IDs and ownership for failed supplier payments, partial deliveries, goods rejection, payment ambiguity, disputes and remediation.
- Inclusion: basic-phone, assisted, rural and local-language pathways with separate network/OTP and trusted helper safety; unverified data does not mean rejection by default.
- Security/ops: negative tenant/consent tests, signed and idempotent transport, CI consumer-contract parity, API/guide/documentation drift, migration/rollback, incident/backup/restore and version-specific evidence.

Implement these workstreams concurrently in separate reviewed PRs. **Do not launch them concurrently without passing each capability's external/provider/regulatory gate.**

## Test plan and evidence

On the OpFin branch run:

```sh
cd apps/api
php -l app/Services/Stolets/MerchantStockFinanceHandoff.php
php -l tests/Unit/MerchantStockFinanceHandoffTest.php
php artisan test --filter=MerchantStockFinanceHandoffTest
```

On Stolets run `pnpm --filter @stolets/domain typecheck` and `pnpm --filter @stolets/domain test`, plus mainline documentation/integration checks. Reciprocal contract fixtures and exact consumer verification remain required before a live receiver.

The following remain **not done** by this branch: service authentication, authoritative consent lookup, persistence of packets, actual OpFin product/application and borrower binding, provider terms, payment lifecycle, customer UX, investment capital, production evidence and regulators' approvals. Do not publish a live API in current endpoint documentation until it actually exists.

## Commercial and controlled rollout

Initial cohort: suitably vetted Ugandan retailers and suppliers with actual POS history, an independent comparable control group, clear merchant benefit and agreed risk limits. Judge expansion by net merchant value, stock availability, cash stress, PAR 7/30, losses, complaints and risk-adjusted contribution. Stolets subscriptions and OpFin lending economics remain modelled separately; the Stolets base-case plan must not be retroactively filled with unverified lending commissions.

**Infrastructure:** no new Railway project, service, database, volume, validation environment or ongoing cloud cost is authorised by this programme. Retain Stolets single project and OpFin's independent approved infrastructure.
