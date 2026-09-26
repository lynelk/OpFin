# OpFin canonical implementation backlog

Status: Current delivery and acceptance backlog  
Reviewed: 24 September 2026  
Language: English (United Kingdom)

Existing requirement identifiers are retained below. A source implementation marker is not a production-acceptance marker. The [concept and plan comparison](CONCEPT_AND_PLAN_COMPARISON.md) preserves the original four-phase plan and separates later changes. The [implementation register](CANONICAL_IMPLEMENTATION_STATUS.md) records achievements; the [delivery evidence](../operations/DELIVERY_EVIDENCE_2026-09-24.md) records current failures.

## Immediate delivery priorities

| Priority | Work | Accountable function | Completion evidence |
| --- | --- | --- | --- |
| R0 | Restore API/Web builds | Engineering/release | Treasury baseline failures and role-catalogue assertion resolved without weakening checks; Web typecheck/build passes at the candidate |
| R1 | Close Essentials financial review | Backend, Finance, Risk and independent reviewer | Expected immutable accounting, canonical intent/reconciliation, exact-Space grants, concurrency-safe collection, open-advance deletion, pending exposure and approved capital-mandate lifecycle |
| R2 | Accept historical treasury records and statements | Finance/backend/operations | Stable opening baseline, safe imports, duplicate/overlap review, justified exceptions and immutable separate-currency statements |
| R3 | Prove essential channel completeness | Product/QA/support | Accepted App/Web/assisted workflows, devices, accessibility, permission denial and interrupted-network recovery |
| R4 | Activate genuine providers/programmes | Partnerships/Compliance/Operations | Contracts, credentials, exact capability routes, approved terms and controlled settlement/recovery/field exercises |
| R5 | Close remaining original phase-2/3 scope | Product/engineering | Accepted vertical slices, including club member capital/NAV/distributions where approved |
| R6 | Demonstrate outcomes and scale | Management/Finance/data/platform | Reconciled adoption, wellbeing, portfolio, commercial, service-level and recovery evidence |

These are this review's priorities, not a retrospective rewrite of the original plan. The latest six API failures and unresolved Essentials controls are internal work, not external credentials. Keep GitHub Actions disabled under the owner's instruction while retaining equivalent candidate-specific checks. Do not remove tests, audits or required independent review.

## P0: Foundation

- FS-001: Financial Space schema, Personal Space bootstrap and backfill.
- FS-002: Memberships, roles and invitations.
- FS-003: Authenticated Space context resolution.
- FS-004: Space capability state.
- FS-005: Plans/entitlements separate from roles and eligibility.
- FS-006: Cross-Space behavioural tests and audit context.
- DOC-001: Synchronised Blueprint/domain/gap/API documentation with mechanically checked change impact; use approved equivalent verification while Actions is disabled.

Source foundations exist. Re-test all new paths against these boundaries; in particular, Essentials partner grants must not inherit another Space's authority.

## P1: Onboarding convergence

- ONB-001: Register once and create Personal Space.
- ONB-002: Intent routing for personal money, groups, organisations and invitations.
- ONB-003: Progressive and resumable verification.
- ONB-004: Invitation-first group/employer/SACCO membership.
- ONB-005: Assisted onboarding with actor/audit/consent separation.

## P1: Individual mobile-complete slice

- IND-001: Everyday money and transactions.
- IND-002: Budgets and goals.
- IND-003: Debt, receivables and payables.
- IND-004: Assets, liabilities and net worth.
- IND-005: Safe-to-spend and financial-health projections.
- IND-006: Savings/investment/protection partner journeys.
- IND-007: Mobile, poor-connectivity and accessibility acceptance.

## P1: Savings Group mobile-complete slice

- GRP-001: Create/join/invite.
- GRP-002: Officials, roles, member lifecycle and governance.
- GRP-003: Contributions, savings, expenses and fees.
- GRP-004: Member loans, repayment, guarantors and approvals.
- GRP-005: Statements, audit history and enabled meetings/voting.
- GRP-006: Group goals, investments and protection.
- GRP-007: Mobile-completeness and low-literacy acceptance.

Treasury accounts, cashbook and statements contribute to these requirements but do not close full member-capital, NAV/unitisation, distributions, investment valuation/performance or essential mobile administration. The current detailed import/reconciliation workflow is Web-based.

## P2: Web and Workspaces

- WEB-001: Space switching consistent with App semantics.
- WEB-002: Enhanced Individual/Household analysis, forecasting and reports.
- WEB-003: Group productivity, bulk operations and advanced reporting.
- WEB-004: Business/Employer Workspace.
- WEB-005: SACCO/Cooperative Workspace.
- WEB-006: Regulated Partner Workspace.

The unified Web sign-in and role-routed Workspaces are distinct from separate accounts for every portal. The current type-only login-context correction must retain that behaviour, safe internal routing and secure cookies.

## P2: Partners and monetisation

- PAR-001: Partner Catalogue and product lifecycle.
- PAR-002: Need/eligibility/suitability before marketplace presentation.
- PAR-003: Standard adapters/webhooks and failure states.
- REV-001: Plans/subscriptions/entitlements.
- REV-002: Effective-dated commercial agreements.
- REV-003: Immutable revenue events and allocations.
- REV-004: Cito/CPay preferred routes and explicitly certified direct fallback. Source and historical deployment evidence exist; each new path/provider requires its own acceptance.
- REV-005: Revenue disclosure, reconciliation and Finance reporting.
- REV-006: Universal service-economics events and reports, implemented in source.
- REV-007: Capital/loan-book reports and funding-source exceptions, implemented foundation.
- REV-008: Insurance/premium/claims and economics reports, implemented foundation.
- REV-009: Savings/investment reports with principal/revenue separation, implemented foundation.
- REV-010: Financial Account Behaviour Report, implemented foundation.
- EMP-002: Positive-only employment enrichment, implemented in source. Reconcile permitted fields/purposes with the original minimal-employment-data boundary.
- KYC-001: Cito-primary NIN/phone routing and controlled fallback, implemented foundation. The Essentials direction prohibits direct gnuGrid access.
- KYC-002: Evidence-capable biometric/document provider until an equivalent certified Cito binary-evidence contract is available; activation remains provider-specific.

## Inclusive-finance implemented foundations

- IF-001: Customer/profile measurement-consent boundary.
- IF-002: Capability guidance and event/outcome evidence.
- IF-003: Non-score financial reputation.
- IF-004: Alternative-data provenance and risk-eligibility gate.
- IF-005: Fair-treatment evidence and protected-field exclusion.
- IF-006: Programmes, enrolment, voluntary exit and participation-window reporting.
- IF-007: Alternative collateral/support, including warehouse receipts.
- IF-008: Mobile Financial Resilience.
- IF-009: Genuine provider/programme certification and approved policy mapping.

IF-001 through IF-008 have implementation foundations; observed programme tests provide scoped evidence. That does not close defects elsewhere in the current release.

## P3: Institutional journeys

- BUS-001: Business KYB, roles and informal-to-formal progression.
- EMP-001: Business employer capability and employee privacy.
- SAC-001: SACCO verification, membership and product configuration.
- REG-001: Fund Manager/insurer/lender/regulated-partner onboarding and certification.

## Programme/commercial P0-P2 delivery markers

These preserve the 23 September source-delivery milestones, not a claim that every live programme is activated.

| ID | Implemented source scope | Remaining distinction |
| --- | --- | --- |
| P0-01 | Versioned dynamic instruments, typed questions, validation, indicator mapping, consent classification, scheduling and shared responses | Real questionnaires require partner and participant validation |
| P0-02 | App/Web/WhatsApp/USSD/assisted instrument journeys | Configured channels and field acceptance still required |
| P0-03 | English plus configurable reviewed translations for Swahili, Luganda, Runyankole-Rukiga, French, Arabic and Acholi; explicit English fallback | Supported locale slots do not prove all translations supplied |
| P0-04 | Acquisition/cost/funnel/repeat/NPL/revenue/contribution reporting | Metrics depend on complete, reconciled recorded evidence |
| P1-01 | Transparent programme-to-commercial graduation | Analytics only, not underwriting |
| P1-02 | Aggregate CSV/XLSX/ZIP partner/MEL packs with suppression/notices | Real partner authorisation and safe distribution required |
| P1-03 | Youth/women resilience, VSLA bridge, refugee/PWD inclusion, employer wellness and MSME/livelihood templates | Templates create editable configurations, not live programme agreements |
| P1-04 | Dedicated partner invitations, verified-phone activation, grants, listing and revocation | Real users/programmes must be authorised |
| P1-05 | Operations dashboard, follow-ups, baseline gaps, consent exceptions and assisted capture | Field delivery and exception handling must be demonstrated |
| P2-01 | Recorded-data financial-health enrichment with provenance | Missing data stays missing; result remains non-credit |
| P2-02 | Governed gnuGrid/CRB, MNO, employer, VSLA, Stolets and other adapter types | Allow-lists, purpose/consent/legal gates and non-risk ingestion remain mandatory |

Programme activation needs real theory-of-change/KPI/target agreements, lawful processing arrangements, dedicated identities, permitted provider signals, reviewed translations and field validation. It does not require turning OpFin into NGO grant management, donor-specific forks, POS/inventory/purchasing or automatic causal-impact/underwriting machinery.

## Final acceptance requirements

- E2E-001: App/Web/API parity and Space isolation.
- E2E-002: Expected accounting, financial integrity, idempotency and reconciliation.
- E2E-003: Accessibility, low literacy, localisation and interrupted connectivity.
- E2E-004: Provider outage, retry, recovery and callback safety.
- E2E-005: Revenue attribution, settlement and reconciliation.
- E2E-006: Documentation/API consistency and production operations.

Use the existing UAT cases and new `DEL-` cases in the [manual supplement](../manuals/CURRENT_CAPABILITY_SUPPLEMENT.md). A slice is accepted only when implementation, permissions, audit, failure states, tests, documentation and applicable production evidence agree. Do not delete an outstanding requirement merely because later source work is extensive.


## OpFin Capital programme backlog — 26 September 2026

These requirements extend the existing backlog.

- **CAP-001**: OpFin Capital reuses Financial Spaces, ledger, lender orchestration and partner controls.
- **CAP-002**: Universal Asset Registry/Asset Passport with shared lifecycle and class-specific evidence.
- **CAP-003**: Capital mandates, funding provenance, allocation/concentration controls and portfolio reporting.
- **AUTO-001**: Vehicle/motorcycle/EV/fleet identity, dealer, valuation, inspection, protection/security and end-to-end servicing/recovery/release.
- **AST-001**: Extend shared asset contracts to solar, machinery, agricultural/productive equipment and approved future classes.
- **DEV-001**: Phone/tablet/laptop passport with validated IMEI/serial/SKU, merchant, purchase and activation evidence.
- **DEV-002**: Merchant/SKU eligibility, supplier settlement and duplicate-finance/stolen-device/merchant-collusion controls.
- **DEV-003**: Device affordability, deposit/LTV, tenor, pricing, funding and partner policy configuration.
- **DEV-004**: Warranty/protection, loss/theft, repair/replacement, trade-in/resale, recovery, settlement and release lifecycle.
- **DEV-005**: Optional lawful OEM/enterprise device-management integration with explicit consent, warning/grace/dispute/release, full audit and no surveillance/OS-security bypass.
- **DEV-006**: SACCO/FI/employer/merchant/API/white-label reuse with strict Space/tenant isolation.
- **PF-001**: Versioned partner Product Factory for asset/device finance; actual lender/funder/principal and disclosures remain explicit.
- **PF-002**: Partner APIs/webhooks for quote, application, decision, evidence, supplier settlement, servicing, repayment and status.
- **DGT-001**: Provider-neutral cryptographic anchoring of selected agreements and lifecycle events; no customer PII on public chain.
- **DGT-002**: Approved digital investment interests reconcile to enforceable legal records and the OpFin ledger.
- **DGT-003**: Stablecoin funding/settlement only through approved jurisdiction, VASP/custodian, AML, custody, FX and accounting controls.
- **DGT-004**: Secondary transfer, public tokenisation and crypto-backed finance remain separately activated later-phase capabilities.
- **CAP-E2E-001**: Person/Organisation ↔ Asset ↔ Finance ↔ Capital reconciles end-to-end.
- **CAP-E2E-002**: Blockchain/VASP/OEM outages cannot block ordinary repayment or servicing.
- **CAP-E2E-003**: Identifier privacy, duplicate-finance, partner isolation, maker-checker, funding provenance and immutable records pass security/UAT.
- **CAP-E2E-004**: Device-control consent, safety, warning, dispute, release and audit pass legal/security/UAT before activation.


## Integrated programme control — 26 September 2026

The cross-programme source of delivery sequencing is [OpFin Integrated Delivery Plan](../development/OPFIN_INTEGRATED_DELIVERY_PLAN_2026-09-26.md). The canonical continuity ledger is the [OpFin Delivery & Feature Register](../governance/OPFIN_DELIVERY_FEATURE_REGISTER.md).

The integrated plan consolidates Essentials, financing-centric conventional/Islamic rails, Capital/assets, legal/contract intelligence, delivery continuity and the repository-owned public website. Existing identifiers in this backlog remain valid and are not deleted merely because the architecture has been generalised.

### Financing foundation additions

- **FIN-001**: Financial Intent and need-led discovery before product/amount selection.
- **FIN-002**: Financial Principles preference: ALL_SUITABLE, SHARIA_ONLY or CONVENTIONAL_ONLY, without collecting or inferring religion.
- **FIN-003**: Versioned FinancialProduct and FinancingArrangement abstractions with legacy Loan/Credit compatibility.
- **FIN-004**: Generic Contract Engine with explicit, audited, fail-closed state transitions.
- **FIN-005**: Legal Product Passport as a product activation hard stop.
- **FIN-006**: FundingPool and capital-mandate segregation, provenance and allocation controls.
- **FIN-007**: Contract-aware accounting/journal instructions with integer minor units, balanced entries and compensating reversals.
- **FIN-008**: CPay settlement/control reconciliation for every money-moving financing path.
- **FIN-009**: Maker-checker activation and exception controls for high-impact product, funding, accounting and governance changes.
- **FIN-010**: Conventional credit remains operational during migration; universal customer entry migrates from Borrow to Finance only with route/API/client compatibility evidence.

### Islamic finance additions

- **ISL-001**: Sharia Governance Centre with authority, approvals, templates, exceptions, remediation, audit and expiry/suspension handling.
- **ISL-002**: Murabaha state machine with supplier/asset verification and mandatory acquisition/possession evidence before sale.
- **ISL-003**: Ijarah contract/lifecycle support.
- **ISL-004**: Musharakah and Mudarabah economic-interest/profit/loss support.
- **ISL-005**: Salam and Istisna contract/lifecycle support.
- **ISL-006**: Qard Hasan and Wakalah support with genuine service-cost/agency treatment.
- **ISL-007**: Islamic/conventional funding, accounting and reporting remain segregatable; Islamic products cannot call conventional interest-pricing logic.
- **ISL-E2E-001**: SHARIA_ONLY never returns a conventional interest product and expired/missing approval blocks contracting.
- **ISL-E2E-002**: Islamic funding cannot allocate to prohibited conventional products.
- **ISL-E2E-003**: Sharia status is never established or overridden by AI or a developer flag.

### Essentials additions

- **ESS-001**: Bills registry, calendar, forecasts and reminders remain useful without borrowing.
- **ESS-002**: Own-money bill payment through approved existing rails with provider fulfilment and reconciliation.
- **ESS-003**: Optional contribution-plus-finance with atomic reservation and refund/release handling.
- **ESS-004**: Term-wide recurring-affordability including future bills, overlapping advances and necessary expenses.
- **ESS-005**: Separate lender eligibility, borrower authority, accepted drawdown and funding/provider finality.
- **ESS-006**: Exact-Space partner access and provider-neutral consented business signals.
- **ESS-007**: Rent-specific tenancy, beneficiary, period, duplicate-financing and dispute controls.
- **ESS-008**: Customer fulfilment artefacts such as electricity tokens remain distinct from authentication/API secrets.
- **ESS-009**: Commercial accounting excludes principal from revenue and flags unknown costs rather than inventing margin.
- **ESS-010**: App/Web/assisted/API/help/manual/UAT parity for the complete Essentials lifecycle.

### Legal and continuity additions

- **LEGAL-001**: Canonical Legal Relationship Record.
- **LEGAL-002**: Consent & Mandate Registry with runtime authority validation.
- **LEGAL-003**: Contract Registry and controlled Clause Library.
- **LEGAL-004**: Contract Intelligence Engine with protected hard stops and human approval routes.
- **LEGAL-005**: Obligation Engine for post-execution duties, evidence, deadlines and remediation.
- **TRACE-001**: Canonical Delivery & Feature Register and Change Impact assessment.
- **TRACE-002**: Requirement -> Feature -> Code/PR -> Test -> Evidence -> Documentation -> Training -> Release -> Monitoring traceability.
- **TRACE-003**: Documentation, API references, manuals, training and approved AI knowledge update in the same delivery lifecycle.

### Public website additions

- **SITE-001**: Confirm existing ChatGPT Site identity, ownership, editable source/linkage, saved-version and rollback capabilities.
- **SITE-002**: Inventory/reconcile the ChatGPT-hosted site and application-hosted marketing implementation.
- **SITE-003**: Establish canonical portable source under `sites/opfin-public/`.
- **SITE-004**: Implement public claim/availability registry; configured/implemented does not mean publicly available.
- **SITE-005**: Establish source commit -> build -> saved host version -> live verification traceability.
- **SITE-006**: Preserve App onboarding and Web/Workspace authentication while consolidating public marketing.
- **SITE-007**: Publish verified canonical site before retiring duplicate marketing code from `apps/web`.
- **SITE-008**: WCAG 2.2 AA target, security/privacy, link, performance and recovery acceptance.
