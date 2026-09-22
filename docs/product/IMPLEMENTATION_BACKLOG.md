# OpFin Canonical Implementation Backlog

Updated: 21 September 2026

## P0 — Foundation
- FS-001 Create Financial Space schema, Personal Space bootstrap and backfill.
- FS-002 Create generic memberships, roles and invitations.
- FS-003 Add Space context resolution to authenticated APIs.
- FS-004 Add Space-level capability state.
- FS-005 Add plans/entitlements separately from RBAC and eligibility.
- FS-006 Add cross-Space isolation behavioural tests and audit context.
- DOC-001 Keep Blueprint/domain/gap/API docs synchronised in CI.

## P1 — Onboarding convergence
- ONB-001 Register person once; automatically create Personal Space.
- ONB-002 Intent routing: Manage my money / Start or join a group / Set up an organisation / Accept invitation.
- ONB-003 Progressive verification levels and resumable onboarding.
- ONB-004 Invitation-first group/employer/SACCO joining.
- ONB-005 Assisted onboarding with explicit actor/audit/consent separation.

## P1 — Individual mobile-complete slice
- IND-001 Everyday money and transaction capture.
- IND-002 Budget and goal management.
- IND-003 General debt, receivables and payables.
- IND-004 Assets, liabilities and net worth.
- IND-005 Safe-to-spend and financial-health projections.
- IND-006 Savings/investments/protection partner journeys.
- IND-007 Mobile-completeness acceptance suite including poor connectivity and accessibility.

## P1 — Savings Group mobile-complete slice
- GRP-001 Create/join/invite.
- GRP-002 Officials, roles, member lifecycle and governance.
- GRP-003 Contributions, savings, expenses and fees.
- GRP-004 Member loans, repayments and guarantor/approval flows.
- GRP-005 Statements, audit history, meetings/voting where enabled.
- GRP-006 Group goals, investments and protection.
- GRP-007 Mobile-completeness and low-literacy acceptance certification.

## P2 — Web and Workspaces
- WEB-001 Space switcher shared with App semantics.
- WEB-002 Enhanced Individual/Household analysis, forecasting and reports.
- WEB-003 Enhanced Group productivity, bulk operations and advanced reporting.
- WEB-004 Business/Employer Workspace.
- WEB-005 SACCO/Cooperative Workspace.
- WEB-006 Regulated Partner Workspace.

## P2 — Partner and monetisation
- PAR-001 Partner Catalogue and product lifecycle.
- PAR-002 Eligibility/suitability interface before marketplace presentation.
- PAR-003 Standard partner adapter/webhook contract and failure state model.
- REV-001 Plans/subscriptions/entitlements.
- REV-002 Commercial agreements and effective-dated terms.
- REV-003 Immutable revenue events and allocations.
- REV-004 CPay billing/collection/settlement/reconciliation integration.
- REV-005 Revenue disclosure, reconciliation and finance reporting.

## Inclusive-finance programme delivery — implemented foundation
- IF-001 Inclusive customer/profile measurement consent boundary. **Implemented.**
- IF-002 Financial capability guidance and event/outcome evidence. **Implemented.**
- IF-003 Financial reputation pathway separate from the Composite Score. **Implemented.**
- IF-004 Governed alternative-data signal provenance and risk-eligibility gate. **Implemented.**
- IF-005 Fair-treatment assessment evidence and protected-field exclusion. **Implemented.**
- IF-006 Programme configuration, enrolment, voluntary exit and aggregate impact reporting with deterministic participation boundaries. **Implemented.**
- IF-007 Alternative collateral/guarantee evidence API including warehouse receipts. **Implemented.**
- IF-008 Mobile Financial Resilience surface. **Implemented.**
- IF-009 Production partner/provider certification, programme agreements and approved product-policy mapping. **Operational activation gate, not inventable in code.**

## P3 — Institutional journeys
- BUS-001 Business KYB and role onboarding; informal-to-formal progression.
- EMP-001 Employer capability activation on Business Space and privacy boundary.
- SAC-001 SACCO institutional verification, member linkage and product configuration.
- REG-001 Fund Manager/insurer/lender/other regulated partner onboarding and certification.

## Final acceptance
- E2E-001 App/Web/API parity and Space isolation.
- E2E-002 Financial integrity/idempotency/reconciliation.
- E2E-003 Accessibility, low-literacy, localisation-ready and interrupted-network journeys.
- E2E-004 Partner outage/retry/recovery and duplicate-callback safety.
- E2E-005 Revenue attribution, settlement and reconciliation.
- E2E-006 Documentation/API drift and production operational acceptance.

## Definition of done
A slice is not done until API, client experience, permissions, audit, accessibility, failure states, automated tests, documentation and production acceptance evidence are complete.

## Impact & Inclusive Finance activation backlog

The core Impact & Inclusive Finance implementation is now coded. Remaining work is activation/evidence rather than inventing more core product surface.

### External/programme activation

- agree programme-specific theories of change, KPI definitions, baselines and targets with each actual implementation partner;
- execute the relevant programme, data-processing and legal agreements;
- provision dedicated programme-partner identities only for authorised users;
- activate real provider integrations only with genuine credentials/contracts;
- configure verified external community-finance or enterprise signals only through governed provider integrations.

### Validation and field evidence

- run the full API/web/client release gates;
- apply production database migration only through the normal controlled release process;
- complete physical-device accessibility/UAT before certification-level claims;
- validate actual programme questionnaires with intended participants, including low-literacy and assisted-channel use;
- confirm localisation requirements per live programme before exposing translated outcome instruments.

### Deliberately not in scope

- full VSLA accounting;
- NGO grant/project management;
- POS/inventory/purchasing;
- donor-specific application forks;
- automatic causal-impact claims;
- automatic use of programme demographics or impact observations in underwriting.

## P0-P2 programme/commercial completion — implemented 22 September 2026

### P0 — implemented

- **P0-01 Dynamic programme check-in engine — Implemented.** Versioned instruments, typed questions, validation, indicator mapping, consent classification, scheduling and shared response storage.
- **P0-02 Multi-channel programme journeys — Implemented.** App, Web, WhatsApp, USSD and assisted capture use one server-authoritative instrument contract.
- **P0-03 Localisation framework — Implemented.** English plus configurable reviewed translations for Swahili, Luganda, Runyankole-Rukiga, French, Arabic and Acholi; untranslated content explicitly falls back to English.
- **P0-04 Commercial unit-economics dashboard — Implemented.** Acquisition attribution, recorded cost truth, funnel, repeat usage, NPL/overdue outcomes, recorded revenue and contribution.

### P1 — implemented

- **P1-01 Programme-to-commercial graduation analytics — Implemented.** Transparent evidence-based graduation state, analytically separate from underwriting.
- **P1-02 Partner/MEL exports — Implemented.** Aggregate CSV, XLSX and ZIP report packs with existing privacy suppression and causal-attribution notices.
- **P1-03 Programme templates — Implemented.** Youth/women resilience, VSLA formal bridge, refugee/PWD inclusion, employer financial wellness and MSME/livelihood templates create editable draft configurations.
- **P1-04 Partner-user administration — Implemented.** Dedicated programme-partner invitations, OTP-backed phone activation, programme grants, listing and revocation.
- **P1-05 Programme operations dashboard — Implemented.** Active enrolments, due/overdue/completed follow-ups, baseline gaps, consent exceptions, assisted capture and scheduled reconciliation.

### P2 — implemented

- **P2-01 Automated financial-health enrichment — Implemented.** Uses existing OpFin financial-life records with provenance; missing external data stays missing; resulting snapshots remain non-credit-eligible.
- **P2-02 Provider-specific adapter framework — Implemented.** Governed gnuGrid/CRB, MNO, employer, VSLA, Stolets and other adapter types with explicit signal allow-lists, consent/legal/configuration gates, idempotent provider references and `risk_eligible=false` ingestion.

### External activation remains separate

Code completion does not fabricate:

- provider credentials;
- partner programme agreements;
- legal/data-processing approvals;
- regulatory approvals;
- reviewed translations that have not actually been supplied;
- physical-device accessibility evidence.

Those are activation/evidence gates rather than missing P0-P2 application code.
