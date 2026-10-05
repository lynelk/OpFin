# OpFin Delivery & Feature Register

**Status:** Canonical continuity ledger  
**Established:** 26 September 2026  
**Source plan:** `docs/development/OPFIN_INTEGRATED_DELIVERY_PLAN_2026-09-26.md`

This register tracks material delivery from requirement through production evidence. Source implementation does not equal activation.

## Status model

`PROPOSED | APPROVED | IN_PROGRESS | BLOCKED | IMPLEMENTED | TESTED | DOCUMENTED | TRAINED | RELEASED | MONITORED | DONE`

A row may only be `DONE` when every applicable Definition-of-Done gate is evidenced. Use `N/A: <reason>` rather than silently omitting a gate.

## Register

| ID | Capability | Current status | Primary gate | Required closure evidence |
| --- | --- | --- | --- | --- |
| OPF-FEAT-0001 | Contract Registry | APPROVED | G1 | Schema, permissions, version/hash evidence, tests, docs |
| OPF-FEAT-0002 | Clause Library | APPROVED | G1 | Protected clause classes, approvals, version history, tests |
| OPF-FEAT-0003 | Contract Intelligence Engine | APPROVED | G1/G4/G5 | Extraction/comparison, protected hard stops, human approvals, audit |
| OPF-FEAT-0004 | Obligation Engine | APPROVED | G6 | Machine-readable obligations, evidence, alerts, breach/remediation |
| OPF-FEAT-0005 | Delivery & Feature Register | IMPLEMENTED | G0 | Canonical register linked from backlog and maintained by changes |
| OPF-FEAT-0006 | Financial Intent & Principles | APPROVED | G0/G5 | Need-led journey, preference without religious inference, matching tests |
| OPF-FEAT-0007 | Financing abstraction | APPROVED | G0/G7 | FinancialProduct/Arrangement model plus legacy compatibility |
| OPF-FEAT-0008 | Contract Engine | APPROVED | G1/G5 | Versioned state machines, fail-closed commands, transition audit |
| OPF-FEAT-0009 | Funding Pools & capital mandates | APPROVED | G3/G5 | Segregation, allocation controls, provenance, reconciliation |
| OPF-FEAT-0010 | Universal Asset Registry/Passport | APPROVED | G0/G5 | Shared asset lifecycle, evidence provenance, uniqueness/privacy tests |
| OPF-FEAT-0011 | Bills & Essentials | IN_PROGRESS | G3/G5/G6 | Existing R1 findings closed plus own-money, gap finance and recurring-affordability acceptance |
| OPF-FEAT-0012 | Murabaha pilot | APPROVED | G0-G10 | Acquisition/possession, contracts, Sharia approval, accounting, CPay and pilot evidence |
| OPF-FEAT-0013 | OpFin Auto | APPROVED | G0-G10 | Uganda country pack and full asset-finance lifecycle acceptance |
| OPF-FEAT-0014 | Device Finance | APPROVED | G0-G10 | Device identity, merchant settlement, fraud/lifecycle acceptance; remote control excluded unless separately approved |
| OPF-FEAT-0015 | Participatory Finance | APPROVED | G1/G3/G5 | Suitability, concentration, economic interests, loss/distribution and liquidity controls |
| OPF-FEAT-0016 | Protection/Takaful | APPROVED | G1/G2/G3/G5 | Partner roles, fund/accounting, coverage/claims, approval and disclosures |
| OPF-FEAT-0017 | Investment screening | APPROVED | G1/G2/G3 | Underlying assets/income, methodology, return classification and purification controls |
| OPF-FEAT-0018 | Social Finance | APPROVED | G1/G2/G3 | Sponsored Qard/Zakat/Sadaqah/Waqf records and non-revenue accounting |
| OPF-FEAT-0019 | Public website consolidation | IN_PROGRESS | WEB | Candidate repository source and claim register established under `sites/opfin-public/`; host/source parity, live-link verification, accessibility/performance acceptance and safe duplicate retirement remain required |
| OPF-FEAT-0020 | Digital capital extensions | PROPOSED | G0-G10 | Separate approval for each anchoring/tokenisation/stablecoin/transfer capability |
| OPF-FEAT-0021 | Lightweight & Sponsored Data Governance | IN_PROGRESS | G0/G4/G5/G10 | Client/server byte metering, feature attribution, whitelist manifest, sponsorship state, low-data/offline rules, bounded sync, KYC/upload budgets, operator reconciliation hooks and release data budgets |
| OPF-FEAT-0022 | Employer HR lifecycle & payroll-linked finance | APPROVED | G1/G4/G5/G6 | Standalone/connected/B2B2C HR modes; employee onboarding/offboarding, leave, suspend/restore/exit, payroll-cycle metadata, deduction instructions, consent/minimisation, retries/audit and employer reporting |
| OPF-FEAT-0023 | Financial engagement rewards & referrals | APPROVED | G0/G4/G5 | Versioned points/streaks/milestones/referrals, genuine financial benefits, anti-gaming controls, disclosures, accounting/economics and customer acceptance |
| OPF-FEAT-0024 | Money Autopilot & Financial Shock Centre | APPROVED | G0/G4/G5/G6 | Customer-controlled automation rules, pause/override/recovery, non-credit shock alternatives, consent, execution evidence and interrupted-network behaviour |
| OPF-LEGAL-0001 | OpFin Master Terms & Conditions | APPROVED | G1 | Versioned legal approval and product mapping |
| OPF-LEGAL-0002 | OpFin Product Privacy Notice | APPROVED | G1/G4 | Data-flow mapping, approved notice and version evidence |
| OPF-LEGAL-0003 | Electronic Contracting & Records | APPROVED | G1 | Execution/evidence standard and acceptance implementation |
| OPF-LEGAL-0004 | Partner Protection Schedule | APPROVED | G1 | Approved partner controls and deviation workflow |
| OPF-LEGAL-0005 | Legal Product Passport | APPROVED | G1 | Machine-readable activation hard stop and product linkage |
| OPF-AI-0001 | Automated Decisioning & AI Schedule | APPROVED | G1/G4 | AI role classification, prohibited actions, review and evidence routes |
| OPF-AI-0002 | AI coaching, explainable intelligence & portfolio optimisation | APPROVED | G1/G4/G5 | Provider abstraction, customer coaching/personalisation, explainable scoring/recommendations, default-risk and portfolio/loan-book intelligence, model/version provenance and human/rules ownership of monetary decisions |
| OPF-DATA-0001 | Canonical Legal Relationship Record | APPROVED | G1/G4 | Immutable accepted versions, agreements, consents, disputes and retention |
| OPF-DATA-0002 | Consent & Mandate Registry | APPROVED | G1/G4 | Purpose-specific authority, revocation, runtime validation and audit |
| OPF-COMP-0001 | Regulatory reporting & CRB dispute/correction operations | APPROVED | G1/G4/G5/G6 | Exact Uganda filing datasets/templates, validation and resubmission, secure submission evidence, CRB reporting/corrections/disputes, complaint/support workflow and auditable operational ownership |
| OPF-REL-0001 | Multi-store distribution & signed-release acceptance | IN_PROGRESS | G5/G10 | Android signed AAB/APK and Play rollout evidence, iOS signing/TestFlight/App Store Connect acceptance, Huawei/AppGallery non-GMS build/device evidence, versioned release manifest and rollback path |
| OPF-MGT-0001 | Profitability & operating cadence | APPROVED | G6/G9 | Reconciled revenue/cost/unit-economics/portfolio data, management cadence, actions/owners, profitability trajectory and evidence that decisions use the canonical operating metrics |
| OPF-QA-0001 | QA Traceability Framework | APPROVED | G5 | Requirement -> code -> test -> evidence -> docs -> release traceability |
| OPF-TRAIN-0001 | Training Content Lifecycle | APPROVED | G6 | Versioned manuals/videos linked to feature changes and releases |

## Archived-conversation reconciliation — 27 September 2026

A review of prior and archived OpFin discussions was reconciled against the current repository and register. The rows added above capture requirements that were discussed or accepted previously but were not cleanly represented as distinct execution items.

The audit also confirmed several historical requests are **not missing**, because they have deliberately evolved:

- P2P/private loan books are represented by **Participatory Finance** and broader Capital/partner-finance work rather than being restored as a separate legacy P2P architecture.
- Microbusiness/POS/inventory/purchasing operations belong to **Stolets**; OpFin retains the financial-wellbeing/finance side of microbusiness needs.
- The customer entry is migrating from **Borrow** to **Finance** under `FIN-010`, preserving legacy route compatibility during transition.
- Fixed historical product assumptions must not be reintroduced where the current architecture has moved to versioned lender/channel/product policy.

Items with source foundations but without full live acceptance remain tracked through their existing rows and gates, including savings/protection/investments, provider activation, USSD/WhatsApp/device acceptance, club capital/NAV/distributions, regional scale and production recovery.

## Immediate evidence queue

The following remain the first closure targets:

- **R0:** API/Web candidate build failures.
- **R1:** Essentials immutable accounting, exact-Space grants, concurrent repayment/collection, open-obligation deletion, pending funding/reversal exposure and capital-mandate usability.
- **DATA-LIGHT:** complete `OPF-FEAT-0021` and make its budgets/whitelist/sponsorship checks inherited by relevant future work.
- **FIN-FOUNDATION:** Financial Intent, financing abstractions, Legal Product Passport, Contract Engine, FundingPool and contract-aware accounting.
- **DOC-CONTINUITY:** same-change updates to APIs, manuals, UAT, legal artefacts and AI knowledge where affected.
- **WEB-FOUNDATION:** prove actual ChatGPT Sites source-linking/version/recovery capability before cutover.
- **CONVERSATION-GAPS:** create/advance delivery slices for `OPF-FEAT-0022`, `OPF-FEAT-0023`, `OPF-FEAT-0024`, `OPF-AI-0002`, `OPF-COMP-0001`, `OPF-REL-0001` and `OPF-MGT-0001` without displacing R0/R1.
- **WEB-FOUNDATION:** candidate canonical source, public-claim register and source-level link validation are now established under `sites/opfin-public/`. Prove actual host source-linking/version/recovery capability, live external links, accessibility/performance and observed deployment before cutover.

## Change-impact requirement

Every material PR must identify affected register IDs and assess Product, Legal, Privacy, Risk, Security, Data, API, UX, Operations, Documentation, Training, AI, QA, Commercial and Compliance impact. A release is incomplete while a required impact remains stale or unevidenced.


## CI execution note — 26 September 2026

GitHub Actions was re-enabled by the repository owner on 26 September 2026. Candidate-specific workflow results remain execution evidence only for the exact commit tested; they do not by themselves establish provider, regulatory, Sharia, financial-control or production activation.

## PR 142 candidate acceptance update (2 October 2026)

Account deletion rejects unresolved obligations immediately and preserves servicing; optional-data deletion retains the account and regulated evidence. Payroll undertaking is case-bound and atomic with reservation request; cancellation/expiry awaits evidenced provider release. Apply `docs/releases/2026-10-02-pr142-release-acceptance.md` to OPF-FEAT-0022, FIN-FOUNDATION, DOC-CONTINUITY and OPF-REL-0001. This entry is implementation/validation work, not production or store acceptance.
