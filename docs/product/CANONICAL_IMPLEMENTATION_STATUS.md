# Canonical OpFin implementation status

Status: Current implementation evidence index  
Reviewed: 24 September 2026  
Language: English (United Kingdom)  
Source baseline: `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`

## Current interpretation

This register identifies source capabilities and historical milestones. It does not label the entire platform accepted or fully deployed. The [concept/plan comparison](CONCEPT_AND_PLAN_COMPARISON.md) preserves the original requirements, and the [delivery evidence](../operations/DELIVERY_EVIDENCE_2026-09-24.md) records the current build failures and unresolved Essentials findings.

The current API build executed 263 tests: 257 passed and six failed. Web compiled but failed TypeScript checking. Worker/scheduler deployment successes do not establish API/Web parity. Internal defects, missing acceptance evidence and external activation are separate categories.

## Financial Space and commercial foundations

Implemented source foundations include:

- Financial Spaces, memberships, invitations, capability configuration and Personal Space backfill for existing wellbeing records;
- financial-life assets, obligations/receivables, net worth and safe-to-spend;
- plans/entitlements separated from roles and product eligibility;
- Partner and Partner Product catalogue;
- commercial agreements and idempotent revenue events;
- Universal Service Economics Events with provider/customer/partner/Cito/OpFin fees, cost, tax, settlement and margin, preserving unknown versus known zero;
- reports for service economics, capital/loan books, insurance, savings/investments, positive employment behaviour and financial-account behaviour;
- funding provenance through `loans.funding_pool_id`, with unassigned sources surfaced as exceptions;
- revenue/provider reconciliation references;
- Business, SACCO, Investment/Fund and Partner onboarding cases;
- Employer as a Business capability and positive-only employment enrichment;
- cross-Space, invitation, employer and financial-life test suites.

Existing identity, consent, credit, offers, repayments, savings, protection, investment suitability/orders, employer programmes, community/SACCO foundations, ledger/reconciliation, provider/webhook controls, USSD/WhatsApp and financial-wellbeing services remain part of the source. A newly added financial path must independently satisfy their controls.

## Inclusive-finance foundation milestone: 21 September 2026

The implemented foundation covers voluntary programme-measurement consent and withdrawal; protected-field separation from credit inputs; capability guidance and intervention/outcome evidence; a non-score financial-reputation pathway; alternative-data provenance, verification, consent and eligibility gates; configurable programmes and idempotent enrolment/exit with participation windows; support evidence such as salary undertakings, guarantees, savings pledges, receivables and warehouse receipts; fair-treatment explanations; five-person cohort suppression; mobile Financial Resilience; high contrast and other accessibility preferences; operations programme configuration; restricted audit metadata; and associated consent/privacy/isolation/verification tests.

Programme measurement is not underwriting. Provider provenance or a risk-eligibility marker alone does not alter a credit decision, and support-instrument verification alone is not approval.

## Impact framework milestone: 22 September 2026

Source includes a versioned indicator registry, programme theory of change, assignments/targets/frequency, staged observations, financial-health/resilience snapshots with reasons, optional livelihood/enterprise/dignified-work records, programme-only agency/empowerment measures, a non-risk community/VSLA evidence bridge, privacy-safe aggregation, suppression of small participant counts and values, dedicated programme-partner identities/grants, aggregate partner portal, operator Impact framework and mobile health check-ins.

Impact/programme data remains `credit_decision_eligible=false`; participant measurements require applicable consent and enrolment. Programme-specific terminology belongs in configuration. Stolets remains independent. Field validation, genuine agreements, approved translations and physical-device evidence are not fabricated by source implementation.

## Programme and commercial P0-P2 milestone: 23 September 2026

The implementation adds metadata-driven instruments/questions; staged follow-ups and hourly maintenance; reviewed localisation and explicit English fallback; App/Web/verified WhatsApp/USSD/assisted response capture with actor separation; five configurable programme templates; programme-delivery operations; dedicated partner invitation/OTP activation/list/revoke; aggregate CSV/XLSX/ZIP packs; acquisition attribution; governed costs; commercial funnel/portfolio/unit-economics views; programme-to-commercial graduation; recorded-data financial-health enrichment; and allow-listed provider-adapter evidence for gnuGrid/CRB, MNO, employer, VSLA, Stolets and future providers.

Observed programme/provider tests passed in the reviewed build. This does not establish complete commercial data, profitable operation, causal impact or activation of every adapter. Enriched/programme evidence remains non-credit unless it separately satisfies the approved non-protected risk-data pathway.

## Provider-independence milestone and historical production evidence

The 23 September register records Cito-preferred routing and controlled direct backup; gnuGrid/CRB and MNO routing through Cito where configured; Cito-primary NIN/phone checks with a separate evidence-capable biometric provider when required; no silent direct retry after ambiguity; positive-only employer enrichment with credit-processing consent; service-economics reporting; capital-mandate reserve/deploy/release/reverse; and principal/premium/capital separation from revenue.

The earlier register reported API, worker and scheduler success, current schema, first-attempt readiness, no observed fatal runtime errors and no new infrastructure for commit `e8e11b1d348f252cae7c5ee3870145c8cb733e10`. These are preserved as historical recorded assertions for that commit, not independently re-certified here and not evidence for the current head.

GitHub Actions remains disabled at the owner's direction. Do not interpret older wording about enabling Actions as permission to change that setting. Equivalent candidate-specific evidence remains required.

## Subsequent treasury, location and Essentials work

### Investment-club treasury and statements

Implemented source provides account records, cashbook transactions, mapped CSV imports, source-hash reuse, suggested matching, explicit exception decisions, confirmation and frozen account/consolidated statements with currency separation and HTML/CSV representations.

Five current historical-date/baseline regression failures prevent acceptance. Treasury is not full capital-call/member-capital, NAV/unitisation, distributions or investment-performance accounting. Detailed import/reconciliation is a Web workflow; complete mobile administration is not established. See [treasury scope](INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md).

### Optional Location Context

Registered location/service-discovery routes and current UAT cases cover purpose-specific capture, provider-backed places/maps/routes, manual fallback, Space authority and a non-credit boundary. Earlier successful API deployment `aa19a53481b2094530616340b8f54aaaabfc6172` records a correction preserving location authorisation errors and authorising before provider resolution. This is not certification of every location/provider/device path.

### Essentials lender orchestration

PR #105 introduced purpose-bound utilities/services/rent finance with a named third-party lender, Cito/CPay integration surfaces, customer platform permissions, quotes, provider settlement and repayment servicing. gnuGrid remains Cito-only for this capability.

The merge explicitly retained unresolved findings. Current review covers required immutable accounting, exact-Space grants, concurrent repayment prevention, open-advance account deletion, pending lender-funding/reversal exposure and capital-mandate usability. Do not classify this work as waiting only for credentials or agreements. See [Essentials scope](OPFIN_ESSENTIALS.md), the [source contract](../../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) and [acceptance supplement](../manuals/CURRENT_CAPABILITY_SUPPLEMENT.md).

## Contracts that remain in force

Individual and Savings Group essential journeys must be mobile-complete; a Web-only implementation does not fulfil that requirement by relabelling it. Institutions use deeper Space-aware Workspaces with progressive verification, and capabilities do not bypass roles, eligibility or approvals.

Recommendations and financial-health calculations remain upstream of commercial terms. Customer data and programme outcomes are not revenue-ranking inputs. Unknown commercial amounts remain unknown; principal/premium/capital are not platform revenue.

## Acceptance gates and next sequence

R0 restores API/Web builds without weakening tests. R1 closes Essentials financial controls with independent review. R2 accepts treasury history and statements. R3 demonstrates channel/accessibility completeness. R4 activates genuinely contracted providers. R5 completes wider original phase-2/3 requirements. R6 demonstrates business outcomes and scale. These priorities are detailed in the [comparison](CONCEPT_AND_PLAN_COMPARISON.md).

A release requires production-like migration evidence, applicable API/client tests, cross-Space denial, expected immutable accounting, replay/concurrency/reconciliation, provider failure/recovery, real customer/device acceptance, institutional role tests, commercial reconciliation and documentation alignment. A passing source build, publication label or previous deployment is not a substitute.
