# Canonical Programme Implementation Status

Updated: 21 September 2026

This file is an implementation evidence index. A capability is only marked complete when code and automated acceptance evidence exist.

## Implemented code foundations

- Financial Spaces, memberships, invitations and Space capabilities.
- Personal-Space migration/backfill for existing financial-wellbeing records.
- Space APIs for creation, membership, invitation and capability configuration.
- Financial-life APIs for assets, obligations/receivables, net worth and safe-to-spend.
- Plan/entitlement schema separated from roles/permissions.
- Partner and Partner Product catalogue schema/API.
- Commercial agreements and immutable/idempotent Revenue Events.
- Revenue reconciliation fields for CPay reference and reconciliation reference.
- Organisation onboarding cases for Business, SACCO, Investment/Fund and regulated Partner Spaces.
- Employer activation as a Business capability, preserving the Personal Space privacy boundary.
- Automated cross-Space isolation, invitation, employer and financial-life tests.

## Inclusive-finance foundations implemented 21 September 2026

- Voluntary inclusive-finance profile with explicit programme-measurement consent and automatic clearing of measurement attributes on withdrawal.
- Measurement-only inclusion fields technically separated from credit-decision inputs.
- Financial-capability guidance and intervention/outcome event evidence.
- Financial-reputation pathway built from verified identity and actual repayment/reporting behaviour without creating a second credit score.
- Alternative-data signal registry with provenance, verification, consent and risk-eligibility gates.
- Configurable inclusive-finance programmes with customer enrolment, voluntary idempotent exit and deterministic enrolment-to-exit impact windows linked to existing Financial Spaces/partners.
- Alternative credit-support evidence for salary undertakings, guarantees, savings pledges, receivables, warehouse receipts and related instruments.
- Fair-treatment assessment evidence and customer-facing explanation of decision boundaries.
- Aggregate impact reporting using system-of-record outcomes and five-person minimum cohort suppression.
- Flutter Financial Resilience experience exposing capability, reputation, editable voluntary programme measurement, programme eligibility/enrolment and credit-support evidence.
- High-contrast accessibility mode added alongside existing large-text, simple-language, reduced-motion and screen-reader support.
- Operations programme configuration UI and privacy-suppressed impact dashboard.
- Audit logging for sensitive inclusive-finance mutations without duplicating raw sensitive values into audit metadata.
- Automated feature tests for measurement consent, protected-field exclusion, provider-signal consent, programme eligibility, Financial Space isolation, programme reporting privacy, enrolment idempotency and alternative-collateral verification.

## Existing capabilities retained and integrated by contract

The current product already contains production KYC/consent, credit, offers, repayments, savings, protection, investment suitability/orders, employer programmes, community-finance/SACCO foundations, ledger/reconciliation, CPay adapter/webhook replay protection, USSD, WhatsApp, financial-wellbeing and Web surfaces. These are reused; this programme does not replace working financial truth with duplicate implementations.

## Mobile-completeness contract

The Flutter App remains the required complete channel for Individuals and Savings Groups. Existing mobile financial hubs and connected-financial-life surfaces must consume the canonical Space APIs as they are progressively switched from user-only ownership. No essential Individual or Savings Group action may be made Web-only or subscription-only.

## Institutional channel contract

Business/Employer, SACCO and regulated partners use Space-aware Workspaces for deeper operations. Institutional onboarding is progressive: profile → KYB → regulatory evidence → products → integration → certification. Enabling a capability never bypasses the applicable verification, partner or regulatory gate.

## Commerce integrity

Recommendation/financial-health calculations are upstream of commercial terms. Revenue events record the commercial consequence after a customer/product action; commission or revenue share is not a recommendation input.

## Acceptance gates

A release is not certified until:
1. database migrations complete on a production-like database;
2. API and client tests pass;
3. cross-Space access is denied by default;
4. financial operations are idempotent and reconciled;
5. CPay callback/retry/failure behaviour passes;
6. Individual and Savings Group App journeys pass mobile-completeness UAT;
7. accessibility/low-literacy and interrupted-connectivity journeys pass;
8. Business/Employer/SACCO/Partner Workspaces pass role/permission UAT;
9. revenue attribution and reconciliation balance;
10. documentation drift checks pass.

External partner credentials, licences/approvals, live commercial agreements and production-provider certification are operational dependencies, not code that can be invented in the repository.

## Impact & Inclusive Finance extension — 22 September 2026

Implemented on the Impact & Inclusive Finance feature branch:

- configurable, versioned impact indicator registry;
- programme theory-of-change model;
- programme indicator assignments, targets and reporting frequency;
- baseline/follow-up/exit/post-programme observation stages;
- customer financial-health and resilience snapshots with transparent status reasons;
- optional livelihood, enterprise and dignified-work snapshots;
- voluntary programme-only economic-agency/empowerment snapshots;
- community-finance/VSLA evidence bridge that remains non-risk-eligible;
- privacy-safe programme outcome aggregation;
- stronger suppression that hides small participant counts as well as values;
- dedicated programme-partner role and explicit programme-level grants;
- aggregate-only partner impact portal;
- admin Impact framework workspace;
- customer mobile financial-health check-in;
- API, user, training, operational and UAT documentation.

Non-negotiable implementation boundary:

- impact/programme data is `credit_decision_eligible=false`;
- programme-linked participant measurement requires active consent and active enrolment;
- programme frameworks do not alter credit eligibility, pricing or limits;
- Stolets remains a separate SME operating platform and may only provide data through explicitly consented, governed interfaces;
- external credentials, programme agreements, regulatory approvals and physical-device accessibility certification remain external activation matters, not values to fabricate in source code.

## Programme and commercial completion P0-P2 — 22 September 2026

Implemented code now includes:

- metadata-driven programme instruments/questions;
- staged follow-up scheduling and an hourly maintenance command;
- reviewed localisation with explicit English fallback;
- customer App and Web check-in experiences;
- verified WhatsApp and feature-phone USSD check-ins;
- staff-assisted capture with actor separation;
- five reusable programme starting templates;
- programme-delivery operations workspace;
- dedicated programme-partner invitation, OTP activation, listing and revocation;
- aggregate CSV/XLSX/ZIP partner/MEL exports;
- canonical customer acquisition attribution;
- governed commercial cost events;
- commercial funnel, portfolio and unit-economics dashboard;
- programme-to-commercial graduation evidence;
- system-enriched financial-health snapshots from existing financial-life truth;
- governed provider-adapter registry and allow-listed evidence ingestion for gnuGrid/CRB, MNO, employer, VSLA, Stolets and future providers.

All new programme responses, enrichment records and provider ingestions retain explicit non-credit boundaries. Provider evidence does not become underwriting merely because it has verified provenance.

Stolets remains a separate SME operating product. A Stolets adapter is an explicit minimum-necessary data interface, not an OpFin merchant-operations module.
