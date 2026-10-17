# OpFin Finance: Evidence-Led Merchant Credit and Portfolio Control Programme

**Status:** Preserved, prioritised product/engineering requirements; financial product activation remains separately gated. **Prepared:** 17 October 2026. **Market of initial evidence:** Uganda. **Canonical owner:** OpFin Finance, governed within the universal OpFin financial-life platform.
**Related:** [Canonical Product Breakdown](OPFIN_CANONICAL_PRODUCT_BREAKDOWN.md), [Universal Financial Life P0–P1 Programme](OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md), [Financial Life Architecture](../architecture/FINANCIAL_LIFE_CONTINUITY_AND_DIGNITY.md), [Financial Spaces Domain Model](../architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md).

## Strategy reconciliation: both requirements stay

OpFin is a **universal financial operating platform**, not a lending-only marketplace. Inside that platform, **OpFin Finance** must become a controlled, evidence-led credit and financial-products capability that protects customers, lenders and capital providers. The near-term **credit commercial priority** is responsible, purpose-linked merchant finance powered by separately governed business evidence. Individuals who cannot or choose not to borrow retain OpFin Today, financial education, planning, personal goals and support.

**No universal mandatory credit funnel:** age, income, geography, sex or unavailable alternative data never removes a person's right to appropriate financial information. Customer-selected aspirations never silently become credit inputs. Underwriting, eligibility, advice, consent, borrower safeguards, group Space rights and commercial priority remain separately governed.

**Stolets is independent:** merchant activity stays owned by Stolets. OpFin may request specifically consented, purpose-limited and freshness-bound evidence through an approved, signed, versioned API contract. Direct database access or replication of raw merchant tables is prohibited. Both applications must remain independently functional.

**Cito** is the approved external service orchestration boundary for identity verification, credit-information services, communications, payments, reconciliation evidence, consent events and activated channel services; OpFin retains full accountability for its credit policy, affordability, loan agreement, lender identity, accounting and compliance. Cito's presence is **not** licence or market authorisation.

## Fourteen mandatory credit workstreams

| ID | Workstream | Preserved requirements | Engineering and governance acceptance |
| --- | --- | --- | --- |
| CF-01 | **Purpose-linked merchant finance** | Prioritise Stolets-embedded inventory/stock top-ups, supplier finance, sales-linked working capital, seasonal loans, small credit-builder facilities and short-term emergency liquidity for otherwise healthy merchants. Retain short-tenor personal loans only where appropriate. Offer evaluated 14/30/60/90-day merchant tenors aligned to cash conversion. | Each use case has named lender, verified purpose, eligible borrower/Space, disbursement beneficiary, product-approval contract, consumer/business protections and permitted repayment frequency; tenors are **candidates**, not universally available. |
| CF-02 | **Replace single-score decisions** | Deliver four *separate* outputs: (a) probability of default, (b) affordable repayment amount, (c) suitable amount/tenor/frequency and (d) decision confidence with comprehensible reason codes. Optional customer 0–100 representation cannot approve alone. | Immutable policy/model version, source freshness, rule reason trace, affordability gate and manual override governance. No fabricated PD when model data is not validated. |
| CF-03 | **Permissioned Stolets Business Passport** | Versioned indicators: sales and margin stability, inventory turnover, supplier repayment, receivables ageing, cash-flow volatility/seasonality, refunds/reversals/adjustments, trading continuity and data reliability. | Scoped consent, purpose, owner and Space, expiry/revocation, signed requests/events, anti-replay/idempotency, field-level provenance, country and feature policy, no unrestricted Stolets database access. Missing/stale data neutral to base eligibility unless a reviewed product legitimately requires it. |
| CF-04 | **Independent affordability** | Net disposable personal/business cash flow; existing debt-service; essential household/business expenditure; liquidity buffer; seasonality and shocks; cash remaining after instalment. | Separate affordability decision from PD and an optional public score; amounts in consistent currency/time basis; stress testing. Transaction counts, airtime and raw mobile-money volume never stand alone as income evidence. |
| CF-05 | **Cost- and risk-based pricing** | Funding cost + expected credit loss + operating/collections costs + capital cost + required margin. Re-evaluate historical 3% access fee and UGX 2,000 disbursement charge, especially for small loans. | Verified configuration, licence and store/country limits, immutable accepted quotation, before-acceptance **all-in UGX cost** and instalment schedule; disclosed terms retained after disbursement. No pricing that hides effective burden. |
| CF-06 | **Controlled progression** | Behaviour states: improving, stable, early deterioration, persistent deterioration, recovered after intervention. No one-payment automatic limit increase; pilot gradual rises, ordinarily **no more than 30–50% per review cycle** where affordable and permitted. | Policy-controlled maximum, checks for sustained repayment and business activity, renewal affordability, customer interest, audit, hardship precedence. The 30–50% band is a *proposal*, not pre-approved lending policy. |
| CF-07 | **Portfolio risk and unit economics** | PAR1/PAR7/PAR30/PAR90, migration/roll rates, NPLs, expected losses, restructuring/cures/write-offs/recoveries, stacking/repeat loans, concentration by lender/product/sector/geography/vintage. Measure margin after funding, Cito, onboarding, support, collections and realised/expected losses. Stress 15% and 22% NPL cases. | Period-based reconciled ledger data and loan cohorts; unknown != zero; dashboard freshness, contribution reconciliation and separate shock scenarios. Model assumptions recorded. |
| CF-08 | **Exposure and stop-loss controls** | Per-person/business exposure, first-loan cap, concentration bands, daily/monthly disbursement ceilings, manual-review limits, lender/product/vintage ring-fence, stop-loss and kill switches. Require **at least three satisfactorily matured loan vintages** before material scale. | Policy-as-code limits evaluated under concurrency at reservation/disbursement time, maker-checker policy release, suspension and human override logs; stop-loss **fails closed** at finance execution, never blocks learning/planning. |
| CF-09 | **Explainability and customer protection** | Review/correct data, purpose-level consent withdrawal, human reconsideration, complaint tracking, consequences/reporting status, total cost and repayment disclosures. Missing alternative data reduces confidence or triggers review, not blanket denial. | Evidenced reason codes, independent dispute rights and corrections, supervised adverse-action/underwriting fairness, expiry, redress and accessible plain-language messages; no use of vulnerable status to sell loans. |
| CF-10 | **Fair inclusion measurement** | Measure by voluntarily provided sex, age cohort, location, business type and alternative-data availability: eligibility, application, approval, limit, pricing, take-up, repayment, restructure/loss, complaints, repeat borrowing and graduation. | Strict analytics/credit feature separation; lawful consent/basis, suppress small cohorts and protect minors. Protected attributes not unvalidated underwriting variables. Monitor fairness and access, not demographic marketing exploitation. |
| CF-11 | **Cito-only service orchestration** | Approved identity/KYC, credit-information exchange, payments and reconciliation evidence, OTP/SMS/WhatsApp/email/IVR/USSD, consent event transport and notifications. | Provider-specific permissions, lawful processing and failure/replay/reconciliation handling; no assumption that orchestration equates to regulatory authorisation. |
| CF-12 | **Model/policy governance and audit** | Model inventory, feature dictionary/provenance, validation/approval, immutable decisions/overrides, versioned rules, drift/quality monitoring, retention/deletion, duplicate-safe reporting, ledger/payment/credit-report reconciliation, Uganda regulatory policy pack and DPIA. Start with interpretable rules; use complex/ML models only in shadow mode until locally validated. | Independent risk/legal/privacy review; data lineage, fairness, explainability and versioned rollback; reproducible decision outcome, not merely a stored score. |
| CF-13 | **Borrower/merchant and investor App workspaces** | Single Flutter App. Borrower: readiness, consent, offer comparison, application, facilities, repayments, health, dispute/help. Investor: governed portfolio, risk band, concentration, returns and explicit liquidity warnings. Avoid unrestricted high-risk microloan picking during pilot. | Exact role/Space isolation, approved investor suitability, customer-friendly costs, mobile low-data performance and live verified provider/custody truth. |
| CF-14 | **Controlled capital model** | Initially diversified UGX-denominated portfolios with standard loan-level evidence, lender/product/vintage caps, first-loss/guarantee if truly contracted, visible servicing and reconciled outcomes. No uncapped retail P2P before performance, legal and consumer/investor acceptance. | Actual creditor/lender identity, legally enforceable investor rights, custody, funding waterfall, concentration, loss allocation, liquidity, risk disclosures and capital authority. No invented guarantees. |

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

## Sequence and release gates

### Immediate: months 0–3 (build trusted readiness, not automatic live origination)

- CF-01: define **approved** merchant product set, verified purpose and proposed 14/30/60/90 day term conditions.
- CF-03: contract, sandbox and permissioned Stolets Passport, data dictionary, approval and quality metrics.
- CF-02/04/09: four-output decision contract, responsible affordability, explanations, human review, complaint/correction and consent.
- CF-05/07/08: cost model, full-cost disclosures, real portfolio dashboards, exposure caps, reconciled losses and stop-loss.
- CF-11/12: Cito integration readiness, Uganda legal/policy/DPIA, immutable decision audit.

### Months 3–6: shadow validation and strictly approved pilots

- Run Stolets-enhanced underwriting in **shadow mode** on lawful historical/current Uganda outcomes; assess PD calibration, affordability, fairness and missing-data stability without changing live lending decisions.
- Validate provider availability, lender/capital authority, policy audit, repayment/payment/reversal/reconciliation and security.
- Pilot with **two carefully chosen merchant cohorts**, conservative limits and manual oversight **only if** legal, product, lender/funding, data-consent and financial-control gates pass. Otherwise remain shadow-only.
- Observe distress, complaints, refinancing, repeat borrowers, unit economics and data coverage.

### Months 6–12: gated controlled lending and portfolio participation

- Expand to capped live merchant lending only if approval evidence is complete.
- Require at least **three mature satisfactory loan vintages**, independently reviewed, before material scale.
- Test gradual limit progression with renewed affordability and stop-loss supervision.
- Diversify qualifying portfolios for approved providers/investors only under sound custody, legal, reconciliation and suitability arrangements.
- Expand only when **better financial inclusion at equivalent expected loss** or **lower loss at equivalent approval**, accompanied by positive consumer outcomes, can be demonstrated with appropriately designed evidence.

**Important:** Calendar milestones are planning horizons, not permission to disburse, accept investor funds or advertise approved credit products. Country/store rules, lenders, funding, regulator, contracts and appropriate customer protections control release.

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
