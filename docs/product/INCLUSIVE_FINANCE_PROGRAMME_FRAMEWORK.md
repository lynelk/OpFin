# Inclusive Finance and Programme Delivery Framework

Status: Canonical product and implementation contract  
Updated: 21 September 2026  
Language: English (United Kingdom)

## Purpose

This document defines how OpFin supports inclusive-finance programmes such as the Building an Inclusive Financial System (BIFS) architecture while preserving OpFin's product identity and existing Financial Spaces architecture.

OpFin is the financial operating and inclusive-finance platform. It is not a POS, inventory or merchant-operations system.

**Stolets is a separate SME automation, digitisation and commerce product.** OpFin and Stolets may exchange explicitly consented signals through governed APIs or refer customers to one another, but they must never be presented as the same product, a single merged proposition, or interchangeable user journeys.

## Programme fit

OpFin's primary fit is **Data & the Digital Finance Ecosystem**, with supporting contributions to financial capability and evidence/learning.

| Programme area | OpFin contribution |
| --- | --- |
| Financial capability and household enablement | Contextual guidance, affordability information, repayment planning, financial health and resilience |
| Data and digital systems | Identity, consent, CRB, verified alternative-data signals, decisioning, APIs, audit and interoperable provider adapters |
| Institutional capability | Financial Institution and partner Spaces, product catalogue, organisation onboarding and governed product configuration |
| Inclusive products and customer journeys | Mobile-first financial journeys, progressive verification, accessibility preferences, alternative credit support and programme enrolment |
| Evidence and learning | Capability events, programme enrolments, aggregated outcomes, cohort suppression and system-of-record credit outcomes |
| Policy and ecosystem learning | Auditable evidence on access, affordability, repayment, financial reputation and inclusive delivery without treating programme demographics as risk signals |

## Non-negotiable inclusion boundary

Voluntary inclusion attributes exist for:

- service adaptation;
- accessibility;
- programme eligibility where lawfully required;
- aggregate programme measurement.

They are **not credit-risk inputs**.

The following measurement-only fields are technically separated from credit decisioning:

- gender;
- age cohort;
- disability status;
- refugee/displaced-person status;
- rural/urban classification;
- employment category;
- self-declared first-time formal borrower status.

Credit decisioning continues to use the governed identity, consent, CRB, approved scoring inputs, credit profile, affordability, product policy and reason-code controls already present in OpFin.

A provider signal can only be marked risk eligible when:

1. it is not a measurement-only field;
2. it has independently verifiable provider provenance;
3. active credit-processing consent exists; and
4. the approved scoring/product policy explicitly recognises it.

Marking a signal risk eligible does not itself add it to a credit score. Approved scoring adapters/model versions remain the decision boundary.

## 1. Inclusive customer profile

The inclusive_finance_profiles table stores optional programme measurement and service-preference data separately from credit profiles.

Rules:

- programme measurement is opt-in;
- voluntary measurement attributes are cleared when consent is withdrawn;
- accessibility preferences continue to use the existing user accessibility settings;
- programme measurement data is not exposed as a credit-profile component;
- no essential financial journey depends on agreeing to programme measurement.

## 2. Financial capability and resilience

The customer API provides a contextual capability snapshot using existing financial truth:

- current amount due;
- total outstanding exposure;
- next due date;
- financial-reputation pathway;
- practical next steps such as identity verification, budgeting, repayment planning, savings goals and reputation building.

The financial_capability_events table provides the evidence chain:

**intervention shown → customer action → outcome**

This supports programme learning without inventing behavioural claims that cannot be traced to system events.

## 3. Financial reputation builder

The reputation pathway is deliberately separate from the OpFin Composite Score.

Stages:

- identity_building;
- starter;
- building;
- established.

The pathway uses verified identity and actual repayment/reporting behaviour. It is an understandable progress indicator, not a second risk score.

## 4. Alternative-data governance

The alternative_data_signals table creates a provenance and consent layer around third-party or programme signals.

Sources may include:

- CRB;
- MNO;
- employer;
- approved partner;
- transactional provider;
- warehouse/WRS provider;
- other independently verified sources.

Customer-reported signals remain non-risk-eligible. Provider signals start non-risk-eligible and must be independently verified before a governed policy may use them.

This layer complements, rather than replaces, the existing CreditScoreComponent and external scoring services.

## 5. Responsible finance and fair treatment

The inclusive-finance layer reuses the existing OpFin controls for:

- KYC and consent;
- affordability and debt-service ratio;
- credit reason codes;
- UMRA disclosures;
- credit-information reporting;
- complaints;
- NPL/default-interest controls;
- electronic receipts;
- hardship;
- governed product and term changes.

The fair_treatment_assessments table adds evidence that decision reason codes remain explainable and that programme-measurement attributes have not crossed into the decision path.

A fair-treatment assessment is not an approval decision. A legitimate affordability decline can still pass fair-treatment controls.

## 6. Financial Institution and partner delivery

Existing Financial Spaces and Partner Catalogue remain the institutional foundation.

An approved bank, MFI, Tier IV institution, SACCO, employer or other partner may use OpFin for:

**acquire → identify → consent → assess → offer → service → collect → report**

according to its configured legal/product role.

The programme layer links a programme to an optional sponsor Financial Space and partner without creating a parallel institutional identity model.

## 7. Inclusive-finance programmes

The inclusive_finance_programmes and inclusive_finance_enrolments tables support configurable interventions.

A programme can define:

- sponsor/partner;
- target population;
- eligibility evidence;
- product configuration;
- reporting configuration;
- start/end dates;
- lifecycle status.

Target-population fields support outreach, lawful programme eligibility and reporting. They do not become credit-risk variables.

## 8. Alternative collateral and credit support

The credit_support_instruments table records evidence such as:

- salary undertaking;
- employer guarantee;
- group guarantee;
- savings pledge;
- receivable;
- insurance guarantee;
- warehouse receipt;
- asset evidence;
- development-finance guarantee.

External collateral such as a warehouse receipt requires provider identity and external reference before operator verification.

**Verification is not automatic credit approval.** A lending product must explicitly recognise the instrument through governed product/decision rules before it affects eligibility, limit or pricing.

OpFin does not become a warehouse-management or inventory platform by supporting warehouse-receipt evidence.

## 9. Impact and inclusion evidence

The admin impact API reports, for enrolled programme populations:

- enrolled people;
- loan applications;
- decision outcomes;
- average approved amount;
- NPL count where available;
- recorded programme/capability events;
- consented inclusion cohorts.

Privacy rules:

- only profiles with active programme-measurement consent enter cohort reporting;
- cohort groups smaller than five are suppressed;
- the impact surface returns aggregate outcomes rather than individual sensitive records.

Programme evidence should progressively cover access, first formal use, affordability, repayment, financial reputation and customer capability outcomes.

## 10. Mobile experience

The Flutter application exposes **Build financial resilience** from Home.

The screen includes:

- financial-reputation stage;
- current credit position;
- contextual next steps;
- optional programme-measurement consent;
- fair-treatment explanation;
- available inclusive-finance programmes;
- alternative credit-support evidence.

Accessibility remains a product-wide concern. Existing simple-language, large-text, screen-reader, reduced-motion and audio-guidance preferences are reused rather than reimplemented as a parallel inclusion setting.

## 11. Product boundary with Stolets

The integration rule is:

**separate products, explicit interfaces, minimum necessary data.**

Valid examples:

- a customer explicitly consents to an approved Stolets-derived business-performance signal being provided to OpFin;
- OpFin refers a business-owning customer to Stolets;
- both products consume shared Cito/CPay capabilities through independent contracts.

Invalid examples:

- copying the Stolets merchant database into OpFin;
- treating Stolets business operations as an OpFin module;
- presenting OpFin as "Stolets for finance";
- exposing one product's customer data to the other merely because the same person has accounts in both.

## Production activation gates

The code foundation does not fabricate external readiness. Production use still requires the relevant:

- provider credentials and certified API contracts;
- legal basis and data-processing terms;
- programme agreements;
- regulated product/provider approvals;
- verified warehouse/WRS or collateral providers;
- accessibility/UAT evidence;
- production database migration and rollback evidence;
- programme KPI definitions agreed with the relevant implementation partner.

These are operational gates, not missing licence strings to be invented in source code.
