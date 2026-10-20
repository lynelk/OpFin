# OpFin Finance: Evidence-Led Merchant Credit and Portfolio Control Programme

**Status:** Revised growth mandate adopted 17 October 2026: **immediate lending-volume and investor-participation expansion**. No blanket pilot, vintage-count, calendar, volume-growth or investor-channel moratorium. Actual transactions remain subject to existing lawful provider, capital, affordability, contractual, settlement and customer-protection controls. **Prepared:** 17 October 2026. **Market of initial evidence:** Uganda. **Canonical owner:** OpFin Finance, governed within the universal OpFin financial-life platform.
**Related:** [Canonical Product Breakdown](OPFIN_CANONICAL_PRODUCT_BREAKDOWN.md), [Universal Financial Life P0–P1 Programme](OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md), [Financial Life Architecture](../architecture/FINANCIAL_LIFE_CONTINUITY_AND_DIGNITY.md), [Financial Spaces Domain Model](../architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md).

## Implementation and acceptance baseline (20 October 2026)

See [OpFin Business Lending Operational Policy, Evidence and Growth Delivery](../operations/OPFIN_BUSINESS_LENDING_EXECUTION_20261020.md) for the candidate merchant product matrix, controlled Stolets shadow/pilot protocol, business affordability and review contract, real contribution-margin calculation, Cito integration gates, portfolio and stop-loss definitions, and the explicit distinction between experimental three-vintage evidence and **immediate authorised lending/investor growth**. It is an execution specification, not a signed live-product policy or permission to move money.

## Strategy reconciliation: both requirements stay

OpFin is a **universal financial operating platform**, not a lending-only marketplace. Inside that platform, **OpFin Finance** must become a controlled, evidence-led credit and financial-products capability that protects customers, lenders and capital providers. The near-term **credit commercial priority** is responsible, purpose-linked merchant finance powered by separately governed business evidence, **alongside immediate growth of every already authorised and operationally supported lending and investor channel**. New underwriting models can be validated in shadow mode without suspending properly authorised existing live services. Individuals who cannot or choose not to borrow retain OpFin Today, financial education, planning, personal goals and support.

**No universal mandatory credit funnel:** age, income, geography, sex or unavailable alternative data never removes a person's right to appropriate financial information. Customer-selected aspirations never silently become credit inputs. Underwriting, eligibility, advice, consent, borrower safeguards, group Space rights and commercial priority remain separately governed.

**Stolets is independent:** merchant activity stays owned by Stolets. OpFin may request specifically consented, purpose-limited and freshness-bound evidence through an approved, signed, versioned API contract. Direct database access or replication of raw merchant tables is prohibited. Both applications must remain independently functional.

**Cito** is the approved external service orchestration boundary for identity verification, credit-information services, communications, payments, reconciliation evidence, consent events and activated channel services; OpFin retains full accountability for its credit policy, affordability, loan agreement, lender identity, accounting and compliance. Cito's presence is **not** licence or market authorisation.

## Immediate growth directive (supersedes prior artificial phase restrictions)

**Grow now.** OpFin should invite, onboard and serve eligible borrowers, lenders and investors **immediately**, using existing legitimately activated products and authorised funding arrangements. Expand merchant acquisition, credit origination and investor participation **concurrently**, without blanket cohort-size, vintage-count, waiting-period or internally imposed volume ceilings. Investor participation is not postponed until portfolio packaging or a fixed number of repayments.

A product can only execute a transaction when actual legal/product authority, lender or investor agreement, available funding, individual affordability/eligibility, custody and reconciliation controls exist. These are transaction-specific safeguards, **not** an excuse for a universal growth freeze. Where a particular product or funding route is not yet authorised, keep other authorised channels available and complete the missing activation work in parallel. Performance monitoring is continuous; intervention is triggered by observed loss, fraud, liquidity, settlement or consumer harm, not calendar age.

**No automatic freezes on expansion:** loan-vintage count, elapsed months, pilot cohort number or an arbitrary percentage limit increase are **not** prerequisites to market growth. Commercial teams can scale acquisition and investor participation immediately; financial execution remains conditional on validated capacity and actual risk policy. Keep audited override and kill-switch capability for exceptional safety events, not routine suppression of growth.

## Fourteen mandatory credit workstreams

| ID | Workstream | Preserved requirements | Engineering and governance acceptance |
| --- | --- | --- | --- |
| CF-01 | **Purpose-linked merchant finance** | Prioritise Stolets-embedded inventory/stock top-ups, supplier finance, sales-linked working capital, seasonal loans, small credit-builder facilities and short-term emergency liquidity for otherwise healthy merchants. Retain short-tenor personal loans only where appropriate. Offer evaluated 14/30/60/90-day merchant tenors aligned to cash conversion. | Each use case has named lender, verified purpose, eligible borrower/Space, disbursement beneficiary, product-approval contract, consumer/business protections and permitted repayment frequency; tenors are **candidates**, not universally available. |
| CF-02 | **Replace single-score decisions** | Deliver four *separate* outputs: (a) probability of default, (b) affordable repayment amount, (c) suitable amount/tenor/frequency and (d) decision confidence with comprehensible reason codes. Optional customer 0–100 representation cannot approve alone. | Immutable policy/model version, source freshness, rule reason trace, affordability gate and manual override governance. No fabricated PD when model data is not validated. |
| CF-03 | **Permissioned Stolets Business Passport** | Versioned indicators: sales and margin stability, inventory turnover, supplier repayment, receivables ageing, cash-flow volatility/seasonality, refunds/reversals/adjustments, trading continuity and data reliability. | Scoped consent, purpose, owner and Space, expiry/revocation, signed requests/events, anti-replay/idempotency, field-level provenance, country and feature policy, no unrestricted Stolets database access. Missing/stale data neutral to base eligibility unless a reviewed product legitimately requires it. |
| CF-04 | **Independent affordability** | Net disposable personal/business cash flow; existing debt-service; essential household/business expenditure; liquidity buffer; seasonality and shocks; cash remaining after instalment. | Separate affordability decision from PD and an optional public score; amounts in consistent currency/time basis; stress testing. Transaction counts, airtime and raw mobile-money volume never stand alone as income evidence. |
| CF-05 | **Cost- and risk-based pricing** | Funding cost + expected credit loss + operating/collections costs + capital cost + required margin. Re-evaluate historical 3% access fee and UGX 2,000 disbursement charge, especially for small loans. | Verified configuration, licence and store/country limits, immutable accepted quotation, before-acceptance **all-in UGX cost** and instalment schedule; disclosed terms retained after disbursement. No pricing that hides effective burden. |
| CF-06 | **Controlled progression** | Behaviour states: improving, stable, early deterioration, persistent deterioration, recovered after intervention. Permit immediate customer limit increases and repeat finance according to refreshed affordability, repayment performance, risk policy and funding, **without a blanket 30–50% progression ceiling or waiting for multiple historical cycles**. One repayment is evidence, not automatic approval. | Policy-controlled maximum, checks for sustained repayment and business activity, renewal affordability, customer interest, audit, hardship precedence. No fixed universal progression cap is imposed by this roadmap. Authoritative borrower, product, lender and country limits remain enforceable where required. |
| CF-07 | **Portfolio risk and unit economics** | PAR1/PAR7/PAR30/PAR90, migration/roll rates, NPLs, expected losses, restructuring/cures/write-offs/recoveries, stacking/repeat loans, concentration by lender/product/sector/geography/vintage. Measure margin after funding, Cito, onboarding, support, collections and realised/expected losses. Stress 15% and 22% NPL cases. | Period-based reconciled ledger data and loan cohorts; unknown != zero; dashboard freshness, contribution reconciliation and separate shock scenarios. Model assumptions recorded. |
| CF-08 | **Exposure and stop-loss controls** | Maintain risk-sensitive per-person/business exposure, available lender capital, concentration monitoring, manual-review triggers, and exceptional stop-loss/kill switches. **Remove universal daily/monthly expansion ceilings and the three-mature-vintage prerequisite as growth policy.** Deploy dynamic guardrails justified by validated risk and available liquidity. | Policy-as-code limits evaluated under concurrency at reservation/disbursement time, maker-checker policy release, suspension and human override logs; stop-loss **fails closed** at finance execution, never blocks learning/planning. |
| CF-09 | **Explainability and customer protection** | Review/correct data, purpose-level consent withdrawal, human reconsideration, complaint tracking, consequences/reporting status, total cost and repayment disclosures. Missing alternative data reduces confidence or triggers review, not blanket denial. | Evidenced reason codes, independent dispute rights and corrections, supervised adverse-action/underwriting fairness, expiry, redress and accessible plain-language messages; no use of vulnerable status to sell loans. |
| CF-10 | **Fair inclusion measurement** | Measure by voluntarily provided sex, age cohort, location, business type and alternative-data availability: eligibility, application, approval, limit, pricing, take-up, repayment, restructure/loss, complaints, repeat borrowing and graduation. | Strict analytics/credit feature separation; lawful consent/basis, suppress small cohorts and protect minors. Protected attributes not unvalidated underwriting variables. Monitor fairness and access, not demographic marketing exploitation. |
| CF-11 | **Cito-only service orchestration** | Approved identity/KYC, credit-information exchange, payments and reconciliation evidence, OTP/SMS/WhatsApp/email/IVR/USSD, consent event transport and notifications. | Provider-specific permissions, lawful processing and failure/replay/reconciliation handling; no assumption that orchestration equates to regulatory authorisation. |
| CF-12 | **Model/policy governance and audit** | Model inventory, feature dictionary/provenance, validation/approval, immutable decisions/overrides, versioned rules, drift/quality monitoring, retention/deletion, duplicate-safe reporting, ledger/payment/credit-report reconciliation, Uganda regulatory policy pack and DPIA. Start with interpretable rules; use complex/ML models only in shadow mode until locally validated. | Independent risk/legal/privacy review; data lineage, fairness, explainability and versioned rollback; reproducible decision outcome, not merely a stored score. |
| CF-13 | **Borrower/merchant and investor App workspaces** | Single Flutter App. Borrower: readiness, consent, offer comparison, application, facilities, repayments, health, dispute/help. Investor: **participation available immediately for eligible investors**, with governed portfolio or permitted individual-loan selection options, risk band, concentration, returns and explicit liquidity warnings. Do not impose a blanket pilot-period investor participation freeze or force everyone into one portfolio structure. | Exact role/Space isolation, approved investor suitability, customer-friendly costs, mobile low-data performance and live verified provider/custody truth. |
| CF-14 | **Controlled capital model** | **Expand both lender funding and eligible investor participation immediately**, offering UGX-denominated diversified portfolios and individual opportunities where authorised and supportable. Provide standard loan-level evidence, lender/product/vintage risk monitoring, actual first-loss/guarantee protection if contracted, servicing visibility and reconciled outcomes. **No blanket delay to retail/individual participation or automatic volume ceiling.** | Actual creditor/lender identity, legally enforceable investor rights, custody, funding waterfall, concentration, loss allocation, liquidity, risk disclosures and capital authority. No invented guarantees. |

## Required underwriting decision contract (target, NOT yet implemented)

```text
decision_reference
person_id + exact business / Financial Space ID + selected lender/product/country
purpose + period + request currency/amount
policy_version, model_version, feature_dictionary_version
source_evidence[] { provider, indicator, observed_at, verified_at, consent_scope, expiry, reliability }
credit_risk { pd: nullable, segment, status, reason_codes[] }
affordability { net_disposable_cash_flow, existing_debt_service, essential_costs,
                stress_buffer, maximum_affordable_repayment, confidence, reasons[] }
structure { suitable_amount, tenor_days, repayment_frequency, schedule_basis }
decision { eligible | manual_review | declined | unavailable,
           confidence, reason_codes[], customer_explanation, review_route }
pricing { lender, fee_components, effective_rate_or_APR_as_applicable,
          total_cost_ugx, amount_to_receive, repayment_schedule, expiry }
governance { override_authority, accepted_terms_snapshot, audit_ref }
```

Do **not** compute a bogus PD with insufficient sample size. `null / not validated` with a reason and an appropriate review route is more honest than fabricated precision. Client-friendly explanations may use a customer-visible score as context, but never as the sole underwriting input. Existing underwriting endpoints remain authoritative until a versioned contract has passed validation; this document does not change their behaviour.

## Business Passport contract: proposed handoff

- The merchant grants consent specifying receiving OpFin financial Space, data fields, purpose `merchant_finance_assessment`, maximum observation lookback, expiration and revocation. Consent does not imply lending approval.
- Stolets, as data owner, calculates versioned metrics. Only approved indicators, reliability and provenance are transmitted via signed versioned APIs/events; no raw unrestricted transaction extraction.
- OpFin verifies merchant/business authority, signatures, replay/idempotency, issuer, version, expiry, source coverage and permissions before storing the minimum necessary attributable evidence.
- A received Passport is an **input to an assessment**, not independently proof of income, custody, business identity or affordability. Adverse adjustments and quality anomalies lead to manual review where appropriate.
- Revocation blocks new uses and future refresh except retention/processing independently required by existing law or contract; accepted historical decisions retain lawful audit references and visibility rights.
- No protected programme variables, children's learning data or voluntary personal aspirations are silently transferred to underwriting.
- Cito service orchestration does not grant OpFin database-level access to Stolets.

## Immediate parallel growth plan and continuing release controls

### Start immediately: concurrent live growth and investor activation

- **Lending volume:** grow applications, repeat lending and purpose-linked merchant finance across **already authorised** lenders, funded products and countries. Do not wait for three vintages or a calendar gate. Approve and disburse on each transaction's credit, affordability, contract, funding and settlement merits.
- **Investor participation:** open acquisition, onboarding, permitted account/Space access and investment choices to all eligible investor segments **now**. Where a funding/product/custody route is genuinely authorised, permit subscriptions and participation immediately with transparent risks, liquidity and settlement state; no two-cohort restriction.
- **Distribution:** activate approved borrower, merchant and investor workflows in the single App/Web, including qualified Stolets commerce journeys when the signed consented evidence interface is ready. Absence of that interface does not pause existing permitted lending products.
- **Pricing and capital:** publish transparent total cost in UGX, connect live lender mandates to available capital, reconcile transactions and protect funds before treating confirmations as settled.
- **Safety by exception:** observe actual portfolio deterioration, fraud, funding concentration, negative contribution margin, liquidity mismatch, reversals and harm; intervene when a specific safeguard triggers. No arbitrary global acquisition throttle.

### First 30–90 days: build and optimise while live authorised services grow

- Complete candidate merchant products and term structure (14/30/60/90 days where actually approved); deliver consented versioned Stolets Business Passport and data dictionary.
- Introduce independent PD, affordability, appropriate structure, decision-confidence and explainability outputs. **New experimental models stay in shadow validation while existing approved policy continues to operate**, rather than using shadow mode as a reason to freeze lending.
- Build live PAR1/PAR7/PAR30/PAR90, roll rates, loss, vintage, lender/sector exposure, real contribution-margin and investor-risk views; stress at 15% and 22% NPL.
- Complete Cito service-provider verification, legal policy pack, DPIA, repayment/settlement/reversal/reconciliation paths, complaints and investor communications.
- Review fees and overall customer value, especially very small loan economics.

### Months 3–6: continuous evidence-led expansion, no cohort cap

- Broaden eligible merchant segments and investor funding channels **as capacity and product rights permit**, without a blanket two-cohort or vintage-count gate.
- Use observed Uganda repayments to validate/shadow-test new alternative data and modelling; promote each model individually on governance evidence while approved existing models remain live.
- Improve borrower and investor experience, offer comparison, customer explanations, concentration visibility and timely servicing; use live loss and customer-outcome data to tune risk, price and growth.

### Months 6–12: deepen capability rather than postpone market entry

- Optimise capital mix, diversified and individual investment pathways where authorised, loss distribution, protection, governance and portfolio reporting.
- Continue growth as permitted by actual finance contracts, capital, borrower affordability and investor suitability, without universal scale limits or a mandatory three-vintage wait.
- Report inclusion, risk-adjusted returns, contribution margin and customer outcomes as continuous management indicators, not prerequisites to starting market participation.

**Release distinction:** The direction for growth, acquisition and investor engagement is effective **immediately**. It is not approval to bypass a legal requirement, fund an unauthorised product, exceed a lender's actual mandate, misrepresent investments, weaken customer rights or move funds before verified authority and settlement safeguards. Inactive provider routes remain inactive until specifically certified; working authorised routes do not wait for arbitrary calendar milestones.

## Integration into the universal OpFin roadmap

| Universal workstream | Credit-specific integration |
| --- | --- |
| FL-01 Today | Show credit obligations and upcoming payments factually, without making loan solicitation Home's default |
| FL-02 Universal access | Declined credit still leaves education, goals and appropriate support available, including young people |
| FL-03 Continuity | Preserve governed repayment and decision evidence across authorised life changes without transferring Space data |
| FL-04 Aspiration | A user's ambition may inform a *voluntarily initiated purpose* but cannot be silently used as risk/credit data |
| FL-05 Dignity | Explanations, human review, hardship and manual review; no shame, abusive collections or exploitative nudges |
| FL-06 Compass | Credit costs, servicing and debt obligations feed financial truth; PD and affordability are distinct |
| FL-07 Passport | Purpose-limited, validated records shared only with lawful approval; no uncontrolled score mining |
| FL-08 Delivery | Merchant/borrower/investor views in the single App; local devices, language, accessibility and channel controls |

### Evidence and delivery record

This document **preserves strategy and defines acceptance**, not proof of feature completion. The present source contains lending/marketplace, lender-orchestration, risk/portfolio and product-config foundations at mixed maturity, with open financial-control evidence for certain paths. No new finance contract, loan offer, pricing, consent or Stolets data exchange is activated merely by documenting the target. All new policy paths require code, tests, external authority and independent financial acceptance.
