# Inclusive Finance and Programme Delivery Framework

Status: Canonical product and implementation contract  
Updated: 22 September 2026  
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
- customers can edit voluntary inclusion details after opting in and can choose "prefer not to say" for sensitive categories;
- accessibility preferences continue to use the existing user accessibility settings, including large text, simple language, reduced motion and a branded high-contrast mode;
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

The fair_treatment_assessments table adds an automated reason-code review for prohibited programme-measurement markers. The programme-measurement data store remains technically separate from the production credit-decision path.

A fair-treatment assessment is not an approval decision and is not certification of an external model or provider's fairness. A legitimate affordability decline can still pass the reason-code review, while broader model governance remains a separate control.

## 6. Financial Institution and partner delivery

Existing Financial Spaces and Partner Catalogue remain the institutional foundation.

An approved bank, MFI, Tier IV institution, SACCO, employer or other partner may use OpFin for:

**acquire → identify → consent → assess → offer → service → collect → report**

according to its configured legal/product role.

The programme layer links a programme to an optional sponsor Financial Space and partner without creating a parallel institutional identity model.

## 7. Inclusive-finance programmes

The inclusive_finance_programmes and inclusive_finance_enrolments tables support configurable interventions.

Participation is voluntary. A customer can end an existing participation through the authenticated programme exit endpoint. Exit is idempotent, records `exited_at` and a `programme_exited` impact event, and keeps earlier programme evidence for historical reporting. The current single-period enrolment model does not silently re-enrol a customer after exit; any re-entry requires an explicit future lifecycle design or controlled programme-operations decision.

A programme can define:

- sponsor/partner;
- target population;
- eligibility evidence;
- product configuration;
- reporting configuration;
- start/end dates;
- lifecycle status.

Programme eligibility uses an explicit allow-list of fields and operators. Supported inclusion fields may determine **programme participation only**. KYC state and the non-score financial-reputation stage can also be used. Missing voluntary information produces an incomplete eligibility result rather than silently inferring the customer's demographic status.

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
- an inclusion dimension is suppressed when any of its groups is smaller than five, preventing simple differencing from programme totals;
- the impact surface returns aggregate outcomes rather than individual sensitive records.

Programme evidence should progressively cover access, first formal use, affordability, repayment, financial reputation and customer capability outcomes.

Credit outcomes are counted only after enrolment and before programme exit. Exited customers remain part of historical participant totals, while post-exit credit/capability events are excluded from the programme observation window. Capability events observed during participation are reported separately from direct programme events and are not described as programme-caused unless the intervention itself is explicitly attributed.

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

Accessibility remains a product-wide concern. Existing simple-language, large-text, screen-reader and reduced-motion preferences are reused rather than reimplemented as a parallel inclusion setting. A branded high-contrast mode is also available. Material controls retain padded targets and semantic labels so platform assistive technologies such as VoiceOver and TalkBack can operate against the same customer journeys. Assistive-technology support still requires device/UAT certification before a production accessibility claim is made.

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

## 12. OpFin Impact & Outcomes extension

The impact extension makes programme strategy and monitoring configurable around OpFin without turning OpFin into a donor project-management system.

### 12.1 Indicator Registry

The `impact_indicator_definitions` register stores versioned, reusable programme indicators with:

- code and definition;
- outcome domain;
- value type and unit;
- calculation methodology;
- collection method and source;
- verification requirement;
- frequency and baseline requirement;
- privacy classification;
- framework/version;
- disaggregation dimensions;
- valid-from/valid-to lifecycle.

Supported outcome domains are:

1. access and inclusion;
2. financial health and resilience;
3. livelihood and enterprise;
4. dignified work;
5. agency and economic empowerment;
6. market systems;
7. optional climate/resilience classification.

Indicators are measurement artefacts. The implementation forces `credit_decision_eligible=false`; configuring an indicator never alters credit eligibility, pricing, score or limit.

### 12.2 Programme Theory of Change

Each inclusive-finance programme can maintain a versioned theory of change:

**problem → inputs → interventions → outputs → outcomes → longer-term impact**

Assumptions, risks and evidence sources are recorded alongside the pathway. Programme-specific terminology and targets are configuration, not permanent OpFin domain concepts. This allows Mastercard Foundation-, FSD-, CARE-, government-, employer- or other partner-aligned programmes to use the same platform without hard-coding any partner's strategy into OpFin.

### 12.3 Financial health and resilience

Customers can complete a short financial-health check-in that records transparent resilience indicators such as:

- income stability;
- essential-expense coverage;
- emergency savings;
- monthly income/debt-service context where provided;
- repayment stress;
- relevant insurance protection;
- recent financial shocks;
- savings direction.

The customer-facing status is deliberately transparent:

- `struggling`;
- `stabilising`;
- `resilient`;
- `progressing`.

The status is not a probability-of-default model or second credit score. The API exposes the reasons behind the classification and explicitly marks the snapshot non-credit-eligible.

### 12.4 Livelihood, enterprise and dignified-work outcomes

Optional outcome snapshots support:

- employment/self-employment state;
- business activity and continuity;
- income/revenue;
- productive assets;
- workers;
- jobs created/retained;
- income reliability;
- weekly work hours;
- self-reported work satisfaction, dignity and sense of purpose.

Programme-linked records require active programme measurement consent and active enrolment. These observations describe measured outcomes; they do not prove causality by themselves.

### 12.5 Economic agency and empowerment

Programme-only voluntary observations can record:

- control over income;
- control over savings;
- financial decision-making role;
- control over productive assets;
- independent use of financial services;
- personal device/account access;
- financial confidence;
- group participation.

These fields remain measurement-only and cannot be introduced into credit decisioning by programme configuration.

### 12.6 Community finance bridge

The `community_finance_evidence` record supports portable evidence from VSLAs, savings groups and other community-finance arrangements without turning OpFin into group-accounting software.

Customer-reported evidence may include membership state, savings balance, contribution streak, completed group loans, repayment history, leadership role and guarantee capacity. It remains `credit_decision_eligible=false`.

If an approved programme later wants a verified group-derived signal to influence underwriting, that signal must separately pass through OpFin's governed alternative-data pathway with explicit credit-processing consent, provider provenance, verification and an approved scoring/product policy.

### 12.7 Programme MEL and partner portal

Programme observations support baseline, 30-day, 90-day, 6-month, 12-month, 24-month, exit, post-programme and general check-in stages.

The reporting layer separates:

- participant observations;
- institutional/market-system observations;
- system-of-record delivery outcomes;
- direct programme events;
- contextual capability events.

Participant outcome cohorts smaller than five are suppressed. Boolean/category distributions are also withheld where a small subgroup would recreate differencing risk.

Dedicated `programme_partner` accounts receive explicit programme-level grants. The partner portal is aggregate-only in this release and does not expose individual participant records. Access labels support read-only, auditor, MEL officer and programme administrator governance, but none bypasses the aggregate reporting boundary.

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

Sensitive inclusion, provider-signal, support-instrument and programme configuration mutations are audit logged without copying raw sensitive values into the audit metadata.

These are operational gates, not missing licence strings to be invented in source code.
