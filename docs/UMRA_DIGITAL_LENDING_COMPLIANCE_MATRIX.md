# OpFin UMRA Digital Lending Compliance Matrix

**Basis:** Uganda Microfinance Regulatory Authority, *Digital Lending Guidelines for Tier 4 Microfinance Institutions and Money Lenders*, January 2024, Vol. 1.  
**Updated:** 18 September 2026  
**Purpose:** technical and operational control mapping. This document is not a legal opinion and does not itself establish licensing or regulatory approval.

## Status definitions

- **Implemented**: a technical control exists in the OpFin source and is covered by release/test controls.
- **Operational evidence required**: the technical control exists, but production configuration, provider evidence or operating records are still required.
- **Entity/regulatory evidence required**: the requirement belongs primarily to the licensed legal entity, governance programme or regulatory relationship and cannot be satisfied by source code alone.
- **Future activation control**: implemented architecture remains disabled/gated until the related regulated product is activated.

## Matrix

| Guideline | Requirement | OpFin control/evidence | Status |
|---|---|---|---|
| 3–5 | Digital-credit business must be conducted by an appropriately licensed provider and UMRA assesses systems, policies, governance and sources of funds. | Release documentation does not invent licence status. OPFIN_LICENSED_ENTITY_NAME, business address and UMRA licence reference are production configuration. Licensing evidence remains an external launch gate. | **Entity/regulatory evidence required** |
| 9.1–9.5 | Protect confidentiality; do not share customer information without consent; do not use contact lists, frequently dialled contacts or messages for e-KYC/delinquency. | Private KYC storage, masked NIN, signed callbacks, role controls. Android launch app does not request contacts/SMS/gallery permissions. Guarantors are deliberately typed and electronically confirmed; OpFin never reads the borrower contact list. | **Implemented**, plus production security verification |
| 10.1–10.3 | Exchange positive/negative credit information and ensure submitted information is complete and accurate. | CreditReferenceReportingService, credit_reference_submissions, payload validation, positive/negative classification, evidence hash, idempotency key, provider reference, retry/backoff, daily portfolio snapshots and event-driven disbursement/repayment/NPL updates. Admin UMRA Control Desk exposes queue health and retries. | **Implemented**; production CRB reporting endpoint/certification required |
| 10.2 | Obtain authentic customer consent before credit information is submitted/shared. | Separate credit_reporting consent is created only after authenticated acceptance of the exact loan-offer disclosure hash. Outbound submission fails closed without active consent. | **Implemented** |
| 12.3 | Borrower limits must follow credit policy/UMRA requirements. | Server-authoritative profile limit, current exposure, affordability/DSR gate and one-active-loan controls. | **Implemented** |
| 12.4(a–e) | Disclose charges/fees, interest and basis, total cost of credit, due dates, and complaint handling procedure. | LoanDisclosureService produces an immutable umra-loan-disclosure-v1 snapshot including principal, amount received, interest calculation, fees/treatment, total cost, total repayment, payment timing and complaints process. Mobile/web offer review displays these before acceptance. | **Implemented** |
| 13.1 | Take reasonable steps to satisfy ability to repay before advancing credit. | Risk profile limit is not sufficient for automatic approval. AffordabilityService requires verified income/obligation data and configured DSR; otherwise request is referred. | **Implemented**, source-provider evidence required |
| 14 | Default-interest/NPL recovery ceilings. | loan_npl_controls freezes principal at NPL onset, tracks initial interest, default-penalty ceiling, interest-recovery ceiling, total recovery cap and recovery since NPL. NplRecoveryPolicyService supports track/enforce, default enforce; collection initiation is blocked above remaining configured cap. Hourly NPL evaluation stages negative CRB updates. | **Implemented as a conservative policy interpretation**; exact UMRA/legal calculation should be confirmed if further regulatory interpretation is issued |
| 15 | Prohibit abusive collection practices and unauthorised contact-list outreach; disclose authorised recovery agents. | Current app never reads customer contacts and regulated collection state is auditable. Recovery-agent registry/assignment notice remains a separate collections-operating control and must be in place before third-party recovery is activated. | **Partial / external collections control remains** |
| 16.1 | Generate e-receipt or instant message acknowledging transactions. | transaction_receipts creates one immutable provider-confirmed receipt per successful collection/disbursement, with evidence hash, provider reference and in-app receipt. SMS acknowledgement is queued separately and is not falsely marked delivered merely because it was queued. | **Implemented** |
| 17.1–17.3 | Complaints channel, resolution within 30 days, retained complaint/outcome records. | All customer support/complaint cases are classified umra_consumer_complaint, receive sla_due_at = +30 days, and cannot be resolved/closed without a customer-facing resolution_summary. Admin shows SLA date; UMRA reports include overdue open complaints. | **Implemented** |
| 18 | Secure authorised systems and protect confidentiality. | Release/security gates, HTTPS, signed callbacks, secret scanning, dependency audits, private KYC storage contract, immutable ledger/reconciliation and access controls. | **Implemented technically**; UMRA application authorisation is external |
| 19.1(a–c) | Key information must be summarised, fair, clear, transparent, current and easily available. | Focused borrower UX, progressive disclosure, plain-language offer sections, accessible text/screen-reader support and server-authoritative terms. | **Implemented** |
| 19.1(d–f) | Disclose regulated/licensed identity and educate customers on protection of personal details. | Offer disclosure supports configured licensed entity, UMRA regulator and business address. Privacy/security copy protects NIN/PIN/OTP. These fields must be populated with verified production legal details before public launch. | **Operational evidence required** |
| 20.1–20.2 | Explain fees, charges, penalties, interest and how/when liabilities are calculated/accrue. | Offer disclosure includes configured rate, cycle/type, term rate, interest formula, fee amounts/treatment/calculation, total cost, payment timing and NPL/default-limit notice. | **Implemented** |
| 20.3 | Solicit only one or two guarantor contacts and obtain electronic confirmation. | Product terms configure 0/1/2 guarantors only. Borrower deliberately enters each number. A guarantor-specific consent SMS is sent; sharing the one-time code evidences electronic confirmation. Max-two database/service controls; decisioning pauses until required contacts are verified. | **Implemented** |
| 21 | No false/misleading advertising on approval/status/rates/costs. | Store/product copy avoids guaranteed approval; profile limit explicitly is not approval. Provider/regulator-gated features remain unavailable until configured. | **Implemented as launch-copy/control rule**, ongoing marketing review required |
| 22.1 | No unilateral increase/variation of accepted credit terms without customer consent. | Accepted offer/disclosure hash is immutable. Proposed term changes are separate credit_term_variations; customer receives exact proposed-change hash and must explicitly accept. Generic live-loan mutation is deliberately disabled/fail-closed. | **Implemented** |
| 22.2 | Interest rate may not be changed without prior written UMRA approval. | Existing loan interest-rate variation cannot be presented for customer consent until UMRA approval reference + SHA-256 evidence hash is recorded. Editing an existing product catalogue rate also requires prior UMRA approval evidence. | **Implemented technical gate**; actual approval evidence remains external |
| 24 | Satisfy customer identity and use licensed name. | NIN + ID front/back + selfie-with-ID, liveness, face match and NIN/phone link; manual review/fail-closed provider errors. Licensed legal identity remains production configuration. | **Implemented KYC; entity naming evidence required** |
| 26 | UMRA on/off-site monitoring, periodic reports/returns. | RegulatoryReportingService generates dedicated UMRA profiles; reports have evidence hashes, validation, maker-checker approval and authenticated JSON/CSV exports. | **Implemented reporting engine**; exact UMRA prescribed templates/submission channel must be configured when supplied |
| Part IX opening / 26 | Books and records must be available for inspection. | umra_books_and_records aggregates loan book, CRB exchange, receipts, complaints and term variations from source-of-truth data. Separate registers exist for CRB exchange, NPL controls, receipts, variations and guarantors. Admin can generate/export evidence packs. | **Implemented** |

## UMRA Admin capabilities

Admin navigation exposes **UMRA controls** and **Compliance reports**.

### UMRA Control Desk

- positive/negative CRB queue summary;
- outbound submission evidence hashes/provider references;
- retry of failed/pending CRB submissions;
- NPL principal/cap/recovery register and enforcement mode;
- manual NPL re-evaluation;
- governed term-variation register;
- prior UMRA interest-rate approval evidence;
- customer-consent status.

### Compliance report generator

Available UMRA profiles:

- umra_digital_credit_supervision
- umra_books_and_records
- umra_credit_information_exchange
- umra_npl_recovery
- umra_transaction_receipts
- umra_term_variations
- umra_guarantor_controls
- consumer_protection_complaints

Validated reports are evidence-hashed and downloadable in JSON/CSV. Maker-checker approval remains available before external submission.

## External launch dependencies not solved by code

The following still require real evidence/configuration before a claim of full operational compliance:

1. licensed lender/digital-credit-provider status and UMRA application authorisation;
2. fit-and-proper, governance, AML/CFT, source-of-funds and credit-policy documentation;
3. Data Protection Act registration/certificate and approved privacy/retention arrangements;
4. registered physical office and verified business address;
5. signed/certified CRB reporting relationship and exact provider schema/endpoint;
6. verified production legal entity/trading-name disclosure;
7. exact UMRA periodic-return templates/submission process if prescribed separately;
8. recovery-agent registry, customer notification and collections operating procedures before third-party recovery is activated;
9. legal/compliance confirmation of the exact section-14 NPL/default-interest calculation if UMRA issues additional interpretation.

## Control principle

Where a regulatory input is missing, OpFin should **refer, block or leave the action pending**. It must not invent approval, consent, provider success, affordability, CRB submission or regulatory evidence merely to keep a workflow moving.
