# OpFin Integrated Delivery Plan

**Status:** Canonical consolidation and implementation programme  
**Date:** 26 September 2026  
**Language:** English (United Kingdom)  
**Repository:** `lynelk/OpFin`  
**Baseline:** `56f34efc0c901f7a6779b7a1f5daaa79ae86bca2`

## 1. Decision

OpFin is one integrated financing and financial-wellbeing platform. It helps individuals, households, groups and organisations **Manage | Finance | Save | Invest | Protect | Give**. Lending remains supported but is not the product boundary.

The programme therefore has one architecture, one implementation backlog and one Definition of Done. Essentials, conventional credit, Islamic finance, Capital/asset finance, Participatory Finance, investments, protection, social finance, legal/contract intelligence and the public website are coordinated capabilities of the same platform, not independent applications.

## 2. Stable architecture

The stable model is:

```text
Person / Organisation
  -> Financial Space + authority
  -> Financial Intent
  -> Financial Principles
  -> Suitability
  -> Eligibility / Risk
  -> FinancialProduct version
  -> Contract / Economic Transaction
  -> Asset / Supplier / Funding where applicable
  -> CPay payment / settlement
  -> Contract-aware accounting
  -> Servicing / support / hardship
  -> Reconciliation / reporting
  -> Financial history and better outcomes
```

For asset and capital products the durable relationship is:

```text
Person / Organisation <-> Asset <-> Finance <-> Capital
```

### Platform invariants

1. One person identity; many governed Financial Spaces.
2. One server-authoritative state across App, Web, Workspace, Partner APIs and assisted channels.
3. One OpFin double-entry ledger and reconciliation model.
4. CPay is the preferred canonical money-movement/reconciliation route.
5. All OpFin gnuGrid access is through Cito; no direct gnuGrid fallback is authorised.
6. Initial credit is partner-funded unless a future direct-lending model passes explicit legal, regulatory, finance and product activation.
7. Conventional and Islamic financing are parallel rails over shared capabilities, with contract, pricing/profit, funding, accounting, delinquency, disclosure and governance separated where required.
8. No religion is collected or inferred from financing preference.
9. Financial Space authority is exact. Platform access never implies borrowing authority.
10. State-changing financial commands are idempotent, correlated and audited.
11. Provider acknowledgement is not accounting finality.
12. Source implementation, deployment, provider activation and production acceptance remain separate states.
13. AI may assist and explain but may not approve finance, Sharia status, protected legal deviations, ledger changes or compliance overrides.
14. New providers, countries, asset classes, lenders and rails use configuration/adapters rather than product forks.
15. No new infrastructure or paid service is provisioned without explicit owner approval.

## 3. Canonical product families

### Manage
Financial Passport, budgeting, calendar, linked accounts, financial health, cash-flow forecasting, bills and reminders.

### Finance
- conventional partner-funded credit;
- Bills & Essentials;
- salary/employer-linked finance;
- OpFin Capital: Auto, Device Finance and productive Asset Finance;
- approved Murabaha, Ijarah, Musharakah, Mudarabah, Salam, Istisna and Qard Hasan;
- Wakalah as an agency structure;
- Participatory Finance.

### Save
Savings pockets and approved partner savings products.

### Invest
Partner-led conventional and Sharia-reviewed investments, capital mandates and approved investment participation.

### Protect
Conventional insurance and Takaful through approved partners.

### Give
Future sponsored Qard Hasan, Zakat, Sadaqah and Waqf capabilities under approved governance.

## 4. Cross-cutting control plane

Every regulated or money-moving product must use:

- **Legal Product Passport**: jurisdiction, regulated activity, booking/lending/funding/servicing parties, licences/approvals, disclosures, tax/accounting references, restrictions and effective dates.
- **Canonical Legal Relationship Record**: accepted legal versions, execution evidence, agreements, consents, mandates, amendments, disputes and immutable history.
- **Consent & Mandate Registry**: purpose-specific, versioned authority with capture/withdrawal evidence.
- **Contract Registry + Clause Library**: executed versions, obligations, deviations, approvals and protected clauses.
- **Contract Engine**: explicit versioned state machines and fail-closed commands.
- **Obligation Engine**: machine-readable post-execution obligations, deadlines, evidence and breach/remediation states.
- **Delivery & Feature Register**: requirement -> implementation -> test -> documentation -> training -> release -> monitoring evidence.

Master Terms are never blanket consent.

## 5. Product-specific consolidation

### Bills & Essentials
Essentials is a recurring-bills capability, useful even when the customer never borrows. It provides bill planning/reminders, own-money payment, and optional partner-funded gap finance. Recurring affordability must include the next bill and overlapping obligations. Rent uses tenancy, beneficiary and period-specific controls. Durable assets remain in Capital.

### Capital and assets
Use one Universal Asset Registry/Passport. Auto, Device Finance and productive assets share lifecycle, evidence, finance, protection, servicing, recovery and release primitives. Remote device restriction remains disabled unless separately approved and must never become surveillance.

### Islamic finance
Use the shared FinancialProduct/FinancingArrangement abstraction with rail-specific Contract Engine handlers. Islamic products never call conventional interest-pricing logic. Funding/books/reporting must be segregatable. Sharia status is established only by approved governance.

### Participatory Finance and investment
Replace P2P Lending as the canonical label with Participatory Finance. Capital/investor suitability, concentration, loss allocation, liquidity, economic interests and disclosures are first-class. Principal, investor capital, charitable funds and custodial balances are not OpFin revenue.

### Ecosystem integrations
Stolets may provide consented, scoped merchant/business signals and distribution. Shamba may later provide agricultural production evidence. Neither becomes an OpFin dependency or grants legal borrowing authority. Cito supports communications/integration and CPay money movement/reconciliation.

## 6. Public website

There is one public OpFin marketing website. Canonical source belongs under `sites/opfin-public/` in this repository. ChatGPT Sites is the intended publisher/host once actual source-linking and account capabilities are verified. Railway remains the authenticated application/backend host, not a second independently maintained marketing website.

The public site is evidence-driven. A source merge, configured provider or product concept never equals a live public claim. Maintain a claim/availability register and trace source commit -> build artifact -> saved host version -> observed deployment.

## 7. Dependency-ordered implementation

### Gate 0 — Stabilise current release
- restore API/Web candidate builds and preserve checks;
- close unresolved Essentials financial controls;
- verify immutable accounting, canonical intents, exact-Space grants, concurrency-safe collection, deletion with open obligations and pending exposure;
- reconcile current documentation and acceptance evidence.

**Exit:** existing financial integrity is trustworthy enough to extend.

### Gate 1 — Governance and financing foundation
- adopt this integrated plan in the canonical backlog;
- create Delivery & Feature Register and Change Impact Matrix;
- implement Legal Product Passport, Legal Relationship Record, Consent/Mandate Registry, Contract Registry and Clause Library;
- introduce Financial Intent, Financial Principles and financing-centric domain abstractions;
- preserve legacy loan/credit endpoints through compatibility adapters;
- implement product/version lifecycle and maker-checker activation.

**Exit:** no product can activate without versioned legal, authority, product and approval evidence.

### Gate 2 — Contract, funding and accounting core
- implement Contract Engine and contract-specific state machines;
- implement FundingPool/capital mandates and segregation;
- implement contract-aware journal instruction sets;
- enforce integer minor units, balanced journals and compensating reversals;
- certify CPay settlement/control reconciliation;
- implement obligation extraction and monitoring.

**Exit:** duplicate/replayed/out-of-order commands cannot create duplicate financial effects and all movements reconcile.

### Gate 3 — Customer financing experience
- migrate universal navigation/entry from Borrow to Finance without breaking conventional credit;
- implement need-led product matching and transparent rail/economic-structure comparison;
- complete Essentials planning, own-money pay, contribution-plus-finance, recurring affordability, rent controls and support;
- preserve App mobile completeness, accessibility, low-connectivity recovery and assisted-channel parity.

**Exit:** customers can manage and finance needs without being pushed into credit.

### Gate 4 — First controlled dual-rail pilot
- implement Asset/Supplier evidence and merchant controls;
- implement Murabaha state machine with acquisition/possession hard stops;
- create segregated Islamic funding pool;
- use Stolets only as a governed distribution/data partner;
- certify contracts, Sharia approval, accounting, CPay reconciliation, hardship/support and pilot limits.

**Exit:** controlled merchant-inventory Murabaha pilot passes G0-G10 production gates.

### Gate 5 — Capital verticals
- Universal Asset Registry/Passport;
- Auto lifecycle and Uganda country pack;
- Device Finance lifecycle, merchant/SKU and duplicate/stolen/collusion controls;
- productive asset finance;
- partner Product Factory and APIs;
- capital allocation/mandates and portfolio reporting.

**Exit:** Person/Organisation <-> Asset <-> Finance <-> Capital reconciles end to end.

### Gate 6 — Investments, protection and social finance
- Participatory Finance;
- Takaful and conventional protection marketplace;
- investment screening/return classification;
- sponsored Qard Hasan and approved social-finance records;
- institution/Investment Club/SACCO capital participation where approved.

**Exit:** suitability, custody/funding, distributions, losses, disclosures and accounting are proven.

### Gate 7 — Public website consolidation
- inventory current Sites and application marketing implementations;
- establish `sites/opfin-public/` and portable build;
- implement claim/availability register and approved brand/content;
- verify Sites source linkage, candidate versioning and rollback;
- publish verified canonical site;
- only then retire duplicate marketing code from `apps/web`.

**Exit:** one recoverable public website, with live/source parity evidence and unaffected application sign-in.

### Gate 8 — Digital capital extensions
Only after canonical off-chain finance is proven:
- cryptographic anchoring/verifiable credentials;
- approved digital investment interests;
- regulated stablecoin funding/settlement;
- controlled eligible transfer;
- crypto-backed finance only under a separate approval.

Blockchain is never the accounting source of truth and must not block ordinary servicing.

## 8. Unified production gates

A regulated product progresses:

`DRAFT -> LEGAL_REVIEW -> SHARIA_REVIEW(if applicable) -> CONFIGURED -> TECH_VERIFIED -> FINANCE_VERIFIED -> UAT -> PILOT -> LIVE -> SUSPENDED/RETIRED`.

Production requires, as applicable:

- G0 Architecture;
- G1 Legal/Regulatory;
- G2 Sharia;
- G3 Finance/Tax;
- G4 Security/Privacy;
- G5 Engineering;
- G6 Operations;
- G7 Data/Migration;
- G8 Pilot controls;
- G9 Independent control;
- G10 cross-functional Go/No-Go.

No developer flag or source merge can bypass these gates.

## 9. Unified Definition of Done

A material slice is DONE only when all applicable items pass:

1. domain model/migration;
2. API/channel behaviour;
3. permissions and Space isolation;
4. legal/privacy/consent authority;
5. accounting and reconciliation;
6. idempotency/concurrency/failure recovery;
7. security and audit;
8. automated and UAT tests;
9. documentation/API references;
10. manuals/help/training;
11. AI knowledge/evaluations where applicable;
12. release notes and migration/rollback;
13. production verification/activation evidence;
14. monitoring/support/hardship;
15. Feature Register evidence.

`N/A` requires a recorded reason.

## 10. Immediate implementation tranche

The next engineering tranche is:

1. preserve and fix the current R0/R1 release baseline;
2. create the governance/feature register and legal control-plane schemas;
3. introduce Financial Intent + Financial Principles + FinancialProduct/FinancingArrangement compatibility model;
4. add Legal Product Passport;
5. add Contract Engine foundation;
6. add Asset/Supplier + FundingPool foundation;
7. add contract-aware accounting instructions and CPay reconciliation invariants;
8. complete Essentials against those shared primitives;
9. implement the controlled Murabaha merchant-inventory pilot;
10. establish the repository-owned public-site source/claim model in parallel, but do not cut over until host/source parity is proven.

This sequence consolidates the supplied plans without postponing their scope. Later gates are dependencies and activation boundaries, not permission to forget them.
