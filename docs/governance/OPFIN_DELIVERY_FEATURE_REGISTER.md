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
| OPF-FEAT-0019 | Public website consolidation | APPROVED | WEB | Repository source, claim register, Sites parity, live verification and safe duplicate retirement |
| OPF-FEAT-0020 | Digital capital extensions | PROPOSED | G0-G10 | Separate approval for each anchoring/tokenisation/stablecoin/transfer capability |
| OPF-LEGAL-0001 | OpFin Master Terms & Conditions | APPROVED | G1 | Versioned legal approval and product mapping |
| OPF-LEGAL-0002 | OpFin Product Privacy Notice | APPROVED | G1/G4 | Data-flow mapping, approved notice and version evidence |
| OPF-LEGAL-0003 | Electronic Contracting & Records | APPROVED | G1 | Execution/evidence standard and acceptance implementation |
| OPF-LEGAL-0004 | Partner Protection Schedule | APPROVED | G1 | Approved partner controls and deviation workflow |
| OPF-LEGAL-0005 | Legal Product Passport | APPROVED | G1 | Machine-readable activation hard stop and product linkage |
| OPF-AI-0001 | Automated Decisioning & AI Schedule | APPROVED | G1/G4 | AI role classification, prohibited actions, review and evidence routes |
| OPF-DATA-0001 | Canonical Legal Relationship Record | APPROVED | G1/G4 | Immutable accepted versions, agreements, consents, disputes and retention |
| OPF-DATA-0002 | Consent & Mandate Registry | APPROVED | G1/G4 | Purpose-specific authority, revocation, runtime validation and audit |
| OPF-QA-0001 | QA Traceability Framework | APPROVED | G5 | Requirement -> code -> test -> evidence -> docs -> release traceability |
| OPF-TRAIN-0001 | Training Content Lifecycle | APPROVED | G6 | Versioned manuals/videos linked to feature changes and releases |

## Immediate evidence queue

The following remain the first closure targets:

- **R0:** API/Web candidate build failures.
- **R1:** Essentials immutable accounting, exact-Space grants, concurrent repayment/collection, open-obligation deletion, pending funding/reversal exposure and capital-mandate usability.
- **FIN-FOUNDATION:** Financial Intent, financing abstractions, Legal Product Passport, Contract Engine, FundingPool and contract-aware accounting.
- **DOC-CONTINUITY:** same-change updates to APIs, manuals, UAT, legal artefacts and AI knowledge where affected.
- **WEB-FOUNDATION:** prove actual ChatGPT Sites source-linking/version/recovery capability before cutover.

## Change-impact requirement

Every material PR must identify affected register IDs and assess Product, Legal, Privacy, Risk, Security, Data, API, UX, Operations, Documentation, Training, AI, QA, Commercial and Compliance impact. A release is incomplete while a required impact remains stale or unevidenced.


## CI execution note — 26 September 2026

GitHub Actions was re-enabled by the repository owner on 26 September 2026. Candidate-specific workflow results remain execution evidence only for the exact commit tested; they do not by themselves establish provider, regulatory, Sharia, financial-control or production activation.
