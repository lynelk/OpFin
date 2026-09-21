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
- Configurable inclusive-finance programmes and customer enrolment linked to existing Financial Spaces/partners.
- Alternative credit-support evidence for salary undertakings, guarantees, savings pledges, receivables, warehouse receipts and related instruments.
- Fair-treatment assessment evidence and customer-facing explanation of decision boundaries.
- Aggregate impact reporting using system-of-record outcomes and five-person minimum cohort suppression.
- Flutter Financial Resilience experience exposing capability, reputation, programme measurement, programme enrolment and credit-support evidence.
- Automated feature tests for measurement consent, protected-field exclusion, provider-signal consent, programme reporting privacy and alternative-collateral verification.

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
