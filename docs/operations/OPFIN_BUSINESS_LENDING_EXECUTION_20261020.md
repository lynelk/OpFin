# OpFin Business Lending: Operational Policy, Evidence and Growth Delivery

**Decision baseline:** 20 October 2026  
**Status:** engineering and commercial execution specification; **NOT** a signed credit policy, funded product activation, regulator approval or proof of production acceptance.  
**Scope:** OpFin Finance in Uganda, independently operated within the OpFin platform. Stolets is an optional, separately owned commerce-data provider; Cito is the intended external-service orchestration boundary.  
**Supersedes:** any interpretation of two merchant cohorts, three mature vintages or shadow scoring as a compulsory *platform-wide* bar on growth. This implementation programme complements [OpFin Finance credit strategy](../product/OPFIN_FINANCE_EVIDENCE_LED_CREDIT_PROGRAMME.md), [merchant integration issue #176](https://github.com/lynelk/OpFin/issues/176) and [authorised growth issue #190](https://github.com/lynelk/OpFin/issues/190).

## 1. Decisions and non-negotiable distinction

1. **Grow authorised services now.** Onboard, lend and accept eligible investor participation on each already approved, funded and operational route, subject to its actual lender-of-record, suitability, credit, pricing, consent, safeguarding, custody, settlement and reporting conditions. Do not create a general two-cohort, three-vintage, time or volume cap.
2. **Validate a new Stolets-dependent underwriting route separately.** First receive versioned, independently consented evidence, run shadow decisions beside the existing authorised policy, assess Uganda outcomes and then operate a *new-product* pilot with two defined merchant cohorts and conservative **product-specific** limits. The cohorts are a controlled test design, not exclusivity over all merchant lending.
3. **Promote model versions, not the entire platform.** Review at least three fully mature *new-policy* vintages as a preferred evidence milestone for material automatic limit increases or a new model's wide rollout. This does **not** prohibit lawful existing products, investor offerings or customer acquisition. An earlier expansion of a specific product needs an explicit documented risk/finance approval supported by sufficient observed outcomes; it is never an automatic calendar exemption.
4. **Build the marketplace and AI concurrently.** Research, investor UX, suitability, portfolio packaging, ML and investor reporting can progress independently. Actual investment subscriptions, capital movement, model-controlled lending and representations of available liquidity require their own factual authority and acceptance.
5. **Fail closed on money and consent, not on ordinary non-credit user access.** Fraud, provider uncertainty, oversubscribed capital, expired consent or absent issuer approval must not trigger irreversible credit actions. Do not disable planning, education or access to the customer's historical records.

## 2. Actual source baseline and engineering gap (20 October 2026)

| Capability | Existing evidence in source | Remaining delivery |
| --- | --- | --- |
| Credit economics | `apps/api/app/Services/CreditEconomicsService.php` calculates repayment cash flows, all-in cost and equivalent APR under effective-dated regulatory pricing rules. | Actual funding and fee cost inputs by product; profitability attribution, stress and signed price approvals. |
| Affordability | `apps/api/app/Services/AffordabilityService.php` assesses verified monthly income and debt obligations against configured max DSR, currently 35% by default. | Business operating cash flow, owner drawings, necessary expenses, seasonality, debt stacking, liquidity cushion and fully explainable repayment capacity. Preserve the existing live rule until replacement is validated. |
| Product limits | `apps/api/config/opfin.php` has credit limit bands of 50,000–500,000 minor units and default 3% access fee / 2,000 minor-unit disbursement fee; `LoanProduct` also supports product-specific min/max amounts. | **Do not treat personal score bands as approved merchant policy.** Approve separate lender/product/country limits and fee schedules and check UGX minor-unit mapping. |
| Stolets preflight | `SignedMerchantEvidencePreflight` validates exact raw HMAC signature, bounded packet and anti-replay; `MerchantStockFinanceHandoff` checks order arithmetic and envelope fields. | No live partner endpoint, authoritative merchant consent, linked identity/Space verification, passport indicator ingestion or lending permission yet. Existing preflight explicitly returns `consent_independently_verified=false`. |
| Cito orchestration | Config supports Cito service routing, but `direct_provider_fallback_enabled` currently defaults to true. | Audit all active routes; migrate verified external services to Cito and explicitly retire unintended direct fallback only after parity, delivery/reversal tests and named operational approval. No silent duplicate charge or payment. |
| Growth inventory | Admin-only `/api/admin/growth-readiness` exposes product and mandate configuration, without asserting that unknown funding/settlement/legal evidence is verified. | Build authoritative live-capital inventory and instrument outcomes and utilisation. |
| Financial change governance | `docs/FINANCIAL_CHANGE_GOVERNANCE.md` demands branch/PR, CI and independent review; separate reviewer gate is temporarily paused with owner accountability. | Reinstate independent review when an authorised second reviewer exists; record auditable owner/finance sign-off for pricing, ledger and collection changes. |

This is a source review, **not** verification of production provider connectivity, a complete code audit, local build, issuer certification or legal opinion. Historical config values are not evidence of commercial or regulatory approval.

## 3. Proposed business-product policy matrix: sign-off required

These are **candidate product definitions**, not enabled production offers. Amount bands deliberately describe an *evaluation envelope*; actual offers use the minimum of approved product/country/lender exposure, available committed funding, independently verified affordability, merchant cash conversion evidence, linked-borrower aggregate exposure and fraud/collection constraints. Any amount below the lender's economic minimum is not viable merely because this table displays it.

| Candidate product | Purpose and repayment source | Suggested term candidates | Indicative initial exposure envelope* | Preconditions |
| --- | --- | --- | --- | --- |
| Stock replenishment | Itemised, verified inventory purchase / sales receipts; preferably pay approved supplier | 14 / 30 days | UGX 100,000–500,000 | Confirmed merchant and supplier; suitable turnover; independently evidenced affordability |
| Supplier invoice finance | Supplier-specific verified purchase and delivery / sales proceeds | 30 / 60 days | UGX 250,000–1,000,000 | Approved supplier, delivery/refund protections, no duplicate PO finance, approved settlement |
| General merchant working capital | Traceable working-capital use and operating cash flow | 30 / 60 / 90 days | UGX 100,000–500,000 | Higher cash-flow evidence standard, concentration limits, no artificial transaction-volume-as-income shortcut |
| Small merchant credit-builder | Small, fully understood finance with positive affordability evidence | 14 / 30 days | UGX 50,000–250,000 | No coercive borrowing, fee affordability, reasonable repayment amount |

*Provisional amounts are **design hypotheses only**: they are not statistical risk estimates, statutory caps, approved limits, live product settings or authority to disburse. A lender can approve a narrower or wider product-specific range on evidence, law, operational capacity and recorded governance. No blanket borrower count or portfolio growth ceiling is imposed by this table.

**Shared decision formula:**  
`approved_principal <= min(product_max, lender_max, borrower_aggregate_remaining, funded_pool_available, independently_verified_affordable_amount, fraud_and_policy_limits)`  
Allow **manual review** for thin files, ambiguous evidence, high-value/long-tenor arrangements, cash-flow shocks or independent consent issues; manual review may never override missing authorisation, consent, settlement finality or a legally binding exclusion. Default is no disbursement on an unknown critical gate.

**Versioned policy fields:** country and legal product passport; lender-of-record and funding pool; eligible channel/business type and minimum evidence; principal and per-group concentration; tenor and frequency; DSR and cash-flow stress thresholds; negative lists; latest consent; repayment and collections; fee treatment; review threshold; effective dates; maker/checker identity; rollback reference. A review-only change cannot silently switch a product to live.

## 4. Business Passport interface and consent

**Stolets owns the data and remains independently deployable.** Build a versioned outward-facing Passport API on Stolets and a narrow inward-facing OpFin adapter. Do not give OpFin direct access to Stolets data stores, and do not use Cito as an unapproved substitute for data-owner consent.

- **Envelope:** version; emitting/receiving service identity and audience; merchant/tenant opaque ID; OpFin linked business/Financial Space ID; passport ID and indicator schema version; timestamps and expiry; signed exact body; nonce/idempotency key; correlation reference; source data coverage, currency, geography and evidence quality.
- **Purpose and permission:** granular opt-in `merchant_finance_assessment` tied to borrower, business and lender, fields and lookback; independently verify current grant and revocation at source before each permissible new assessment; distinguish owner/operator authorisation; capture lawful retention and data-subject correction.
- **Indicators:** reconciled sales and gross margin series, cost of goods, inventory turnover and stock-outs, refunds/reversals, supplier payment timeliness, receivables ageing, weeks active, missing-day ratio, last-sync time, offline adjustments, transaction reversals and outlier flags. Include availability/provenance and never turn absent data into zero income.
- **Security:** approved service credentials, key rotation, mTLS or equivalent strong partner authentication, scoped country and tenant, HMAC/JWS signature and bounded clock skew, nonce persistence, event authenticity, packet size limits, idempotent retries, rate limits, audit and data minimisation.
- **Failure states:** `not_linked`, `consent_required`, `revoked`, `stale`, `insufficient_coverage`, `conflicted`, `source_unavailable`, `accepted_for_review`. A valid signature alone cannot transition to `approved`. Failed consent/identity verification prevents use of those indicators in live credit.
- **Acceptance:** signed and unsigned fixtures, replay/duplicate delivery, old/new key rollover, stale offline periods, conflicting owner identity, revocation between quotation and acceptance, wrong tenant, denied scope, missing source data and provider outage. Published OpenAPI schema and two-sided versioned contract tests.

## 5. Underwriting, affordability, explanations and human review

Emit four **separate** decision objects: **credit risk** (calibrated probability of default if validated, else unknown), **repayment capacity** (disposable monthly/cycle cash flow and shock buffers), **suitable structure** (principal, tenor, instalment dates and customer purpose), and **decision confidence/reasons**. Keep decision and evidence immutable, hash referenced and linked to the exact accepted price snapshot.

For merchant cash flow, reconcile sales receipts to the actual deposits where permitted; estimate net available cash after stock replenishment, operating expenses, household drawings, essential expenses, taxes and all existing debt. Match instalments to observed cash conversion and seasonal troughs. Underwrite stress scenarios for sales shock, increased cost of goods, delayed receivables and already-committed lender obligations. The existing 35% monthly DSR is a current configured rule, **not** by itself a valid replacement for business disposable cash flow.

Reason codes must distinguish insufficient *independent* evidence, unaffordable repayments, inconsistent activity, policy limit, lender/product unavailability, failed consent and fraud review. Customers must receive plain-language reasons and a correction or manual-reconsideration path. Protect legally restricted attributes from unapproved decision features; segregate fairness monitoring from underwriting.

Reviewer role separation: an originator cannot approve its own exceptional override; overrides have scoped authority, a justification, before/after evidence, time, product/version, maker/checker, appeal trail and reversal route. An override cannot manufacture funds or legal authority.

## 6. Portfolio control, ledger evidence and operating dashboard

**Dashboard slices:** day and maturity cohort, product and version, lender/funding pool, merchant sector, geography, channel, business vintage and data-quality segment. Reconcile source-of-truth loan agreements, scheduled dues, cash receipts, Cito payment observations, settled bank/wallet entries, ledger movements, reversals and portfolio commitments.

| Metric | Definition and evidence |
| --- | --- |
| PAR1/7/30/90 | Outstanding principal on loans more than 1/7/30/90 days past due divided by outstanding principal in the eligible active book; retain restructures and due-date history to avoid hiding delinquency. |
| Vintage losses | Cohort by origination month/product/policy; origination amount, maturity date, outstanding, realised losses, recovery and net loss; do not show incomplete cohorts as mature. |
| Expected vs realised economics | Interest/fees actually collectible and recognised, funding and settlement costs, Cito/provider charges, onboarding/support/collections, expected loss, realised charge-offs/recoveries and margin after impairments. |
| Fund utilisation | Committed, reserved, disbursed, repaid, settled and withdrawable amounts by lender/pool; never conflate a commitment with cleared cash. |
| Stop-loss and concentration | Borrower/group exposure, sector/location/supplier concentration, fraud signals, funding-provider outages, settlement exceptions, PAR roll rates and consumer complaints. |
| Fairness and data quality | Approval, repayment, pricing and complaints differences by lawful aggregate cohorts; suppression of small cells; consent availability, invalid data and evidence freshness. |

**Hard execution stops:** unauthorised lender or route; revoked/absent consent; known duplicate disbursement; unreserved or unavailable funding; balance/ledger invariant failure; unresolved identity mismatch; unlawful pricing or missing accepted quote; failed provider finality. Restrict the affected product/provider if possible, not unrelated authorised products.

**Escalation rather than blanket stop:** abnormal PAR roll rate, loss trending above risk appetite, repeated negative net margin, unreasonable customer costs, data drift, rising complaints and sector concentration. Configure **effective-dated thresholds by product** using an approved baseline and reliable denominators; alert, triage and maker-checker approve change. Unknown data is never displayed as healthy zero.

## 7. Recalculate all-in lending and funding economics

**Per-loan expected contribution**, consistently on contractual cash-flow dates and nominal UGX:

`recognised_interest + earned_fees - (funding_cost + expected_credit_loss + Cito/settlement_cost + acquisition_onboarding + KYC/communications + servicing_collections + support + capital_and_warranty_cost + directly_applicable_taxes)`

Do not count principal repayment as revenue; distinguish expected cash, accrued revenue, provider-initiated payments and irrevocably settled cash. For each product calculate break-even all-in price, customer APR/effective rate, disbursed proceeds, total contractual cost and lender/investor net return; test early repayment, default timing, arrears, rollovers, reversals and risk allocation. Never apply a pricing rule merely because a configuration allows it.

**Fee burden check, not a new tariff:** the current configured default access fee is 3%, disbursement fee UGX 2,000, plus term-defined interest. At an *illustrative* 2.8% for one 30-day flat cycle and a UGX 100,000 principal, interest is UGX 2,800; other two fees total UGX 5,000; total customer finance charge is **UGX 7,800** before any product-specific rule and with no compounding or default charges. On UGX 50,000, the same assumptions yield UGX 4,900, or **9.8% of principal in 30 days**. These examples are not approved offers or the statutory cost calculation; show the borrower's exact net proceeds and repayment schedule using the canonical economics engine.

**Funding-model inputs must be evidenced:** term-and-amount weighted disbursements; funding/treasury annualised cost and day count; paid and accrued settlement charges; PD × LGD × EAD plus observed net losses and collection timing; Cito per-transaction costs; merchant verification/onboarding and support cost; lender fee/waterfall; liquidity/capital cost; applicable taxes; sector/country margin. Recompute for actual 14/30/60/90 day schedules and at base, stress and downside scenarios, including **15% and 22% NPL** stress cases as scenarios rather than forecasts. Approve floors/ceilings per lender and market and prevent negative-contribution products from auto-expanding without a documented response.

## 8. Shadow underwriting, Uganda back-testing and fairness

1. **Retrospective outcomes:** select consented, legitimate Uganda repayment history with origination timestamps, contractual schedules, payments, renewals, restructures, reversals and charge-offs. Distinguish available labels from unobservable defaults; no invented repayment data.
2. **As-of reconstruction:** compute only features knowable at each historical decision, using immutable Stolets snapshot timestamps where legally allowed. Exclude post-loan payments, future inventory/reviews and exposure to the prediction target. Hold out **later time periods and merchants**, not only random rows.
3. **Shadow route:** generate independently identified Stolets-enhanced proposal, policy/model version, reasons, confidence, price, term and amount with `execution_authorised=false` and **zero effect** on existing approvals, customer offers, ledger or settlements.
4. **Evaluation:** compare with existing policy on selection effects, coverage/missingness, calibration, AUC/PR-AUC where meaningful, first-payment default, PAR30/90, realised net losses, affordability breaches, expected margin and risk-adjusted benefit. Include drift and re-run on low-volume and non-Stolets segments. Do not invent numerical PD for a tiny dataset.
5. **Fairness:** with a lawful basis and protected aggregation, review outcomes by sex, age group, region and merchant type, adjusted for legitimate risk characteristics. Investigate unexplained disparities and incomplete data; never encode unapproved demographic proxies to increase approvals.
6. **Promotion:** independent model-owner, risk, legal/privacy and finance sign-off, feature dictionary, calibration and decision-replay evidence; immutable version, rollback plan and dashboard alerts. A model whose Uganda evidence is insufficient remains shadow-only; existing authorised underwriting continues.

## 9. Cito integrations and third-party isolation

Maintain an inventory for each country/product/route: KYC/identity, credit information, user consent transport, messaging, collection/disbursement, finality, reversals, disputes and reconciliation. For each verify contracted counterparties, legal permissions, credentials/environment segregation, monitoring, rate limits, error classification, callbacks, signatures, idempotency, timeouts, retry policy, webhook replay, settlement and refund evidence.

Cito mediates external services; **OpFin** remains authoritative for permissioned product activation, loan decisions, borrower records, immutable financial ledger and complaints. Stolets remains the owner of its merchant records and independently usable without OpFin. No cross-product direct database dependencies. Disable undesired direct external-service fallback only after a successful parity and fail-closed test; do not flip configuration blindly on a live route.

## 10. Two-cohort new-model pilot and mature-vintage review

**Pilot population applies only to the newly approved Stolets-dependent merchant product.**

- **Cohort A:** merchants with adequately reconciled, established sales/inventory history and independently verified owner/business identity.
- **Cohort B:** otherwise eligible merchants with limited or intermittent digital histories; manual review and explicit data-sufficiency records, not automatic decline for missing Stolets usage.
- Use sampling designed to measure risk and inclusion without demographic targeting; record recruitment, exclusions, consent and control/comparison group.
- Pilot-specific conservative exposure is approved by lender and risk according to the product matrix, verified cycle cash flow, concentration and initial loss tolerance. No invented enrolment count or capital commitment. Do not impose pilot bounds on separately authorised products.
- Each origination **vintage is mature** only when its scheduled contractual maturity has occurred and a predeclared observation/cure window has elapsed, with payments, defaults, recoveries, reversals and outstanding losses reconciled. A 90-day product takes materially longer to mature than a 14-day product.
- Review a minimum of **three mature experimental vintages** before using them as evidence for ordinary automatic new-model scale and limit progression; document performance, uncertainty and fairness by cohort. It is an evidence target, **not** a universal statutory threshold or a reason to stop concurrent authorised channels.
- Approved limits may rise for borrowers after fresh cash-flow, external-debt, affordability, risk, funding and concentration review. Higher limits are not automatic after one on-time repayment; maintain rollback and repeat assessment.

## 11. Governed capital packaging and marketplace

Tag loans with the immutable approved lender/product, original policy/offer, vintage, repayment/cure state, funding source, investment rights, fee waterfall, seniority/loss allocation, consent, risk class and cash receipts. Eligible exposure pools must reconcile to **existing** ledger records, avoid multiple pledges and show net returns after actual servicing/default/collection costs. Apply concentration, suitability, liquidity and redemption limits matched to **verified mandates**. A visual portfolio does not create custody or legal investment rights.

Marketplace product work, institutional portfolios, eligible investor onboarding, reports and AI-enabled research can advance immediately; live subscriptions and money movement are separately enabled when the actual route is authorised, funded, correctly disclosed and reconciled. Never imply capital protection or guaranteed returns without signed evidence.

## 12. Delivery sequence, owners and acceptance

| Track | Immediate increment (0–30 days) | Next increment (30–90 days) | Approver / go-live evidence |
| --- | --- | --- | --- |
| Product & credit policy | Product/legal/lender inventory, product passports, candidate limits and pricing structure, exact quote disclosures | Effective-dated merchant policies, manual review, cycle affordability and regression tests | Risk + lender-of-record + legal/finance; authorised product and funded mandate |
| Stolets Passport | Mutual contract/indicator dictionary, exact signing, scopes, adverse quality conditions, consent verification design | Reciprocal signed sandbox integration, revocation/replay tests and trustworthy attribution | Both data owners/privacy; real signed consent and mutually verified provider authority |
| Underwriting | Source-as-of capture, independent capacity, reason taxonomy, immutable decisions | Shadow engine and Uganda vintage back-tests, fairness/model validation | Risk/model governance; no model-driven production execution without approval |
| Cito integration | Inventory routes and missing credentials/market permissions | Production certification of allowed paths, payment finality and exception UAT | Provider/country authority + operations + finance |
| Portfolio controls | PAR/roll-rate/settlement/cost dictionary and read-only monitoring | Governed stop-loss, reconciliation exception queue, capital packaging and audit | Risk + finance + operations; actual source-to-ledger balances |
| Controlled new product | Design two cohorts and approved pilot protocol while current valid products grow | Launch bounded new merchant product once its specific permission gates pass; measure vintages | Lender/product/borrower/customer-protection acceptance |
| Expansion | Grow live approved routes and permitted investor channels in parallel | Validate three experimental mature vintages; evolve limits by observed risk and refreshed affordability | Explicit portfolio/finance review per product and investor mandate |

**Operational acceptance evidence:** source SHA, API/app/web candidates, tests and named reviewers; country and lender permission; consent/Space provenance; funding/custody acceptance; real settlement and refund proof; finance/reconciliation invariants; security/privacy and customer redress; portfolio monitoring and rollback; documentation and after-deploy health. No green build is itself proof of live product authorisation.

**Do not deploy speculative product limits, switch on investor rails, increase customer charges or initiate payments as a side effect of this document.** Every regulated money-control change remains subject to Financial Change Governance and the applicable external acceptance.
