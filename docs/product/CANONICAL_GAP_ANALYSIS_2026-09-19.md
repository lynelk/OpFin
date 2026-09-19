# Canonical Product Gap Analysis — main

Baseline reviewed: `main` at 537669b3e82063ed166f88178a7ad08466fca291  
Date: 19 September 2026

## Already implemented / strong foundations

- Phone/OTP authentication, PIN/identity journey and production KYC controllers.
- Versioned consent and credit-processing controls.
- Lending application, offer, repayment schedule, payment and reconciliation foundations.
- Production ledger/journal structures and financial-integrity tests.
- CPay webhook/adapter boundary and replay-safety tests.
- Financial wellbeing tables for accounts, budgets, entries and calendar events.
- Savings goals/products/movements and protection products/policies/claims.
- Financial wellbeing, household-finance, investments, employer, microbusiness and community-finance web surfaces.
- USSD and WhatsApp controllers.
- Capability registry/configuration foundation.
- Community-finance programmes, memberships, ledger, facilities, guarantors, scorecards and partner-plan foundations.
- Accessibility and low-literacy acceptance principles in the launch journey.
- CI, security workflow, API docs, UAT and production-readiness documentation.

## Reusable but needs refactoring/convergence

- `financial_accounts`, `financial_budgets`, `financial_entries` and calendar events are owned directly by `user_id`; they need Financial Space ownership while retaining user provenance.
- Community Finance membership is programme-specific; membership/role semantics need to converge on generic Financial Space membership without discarding cooperative-specific records.
- `institutions` is reusable for legal entities but must not double as every user-facing Space.
- Current capability registry is country/config based; add Space-level capability state and separate entitlements.
- Employer functionality exists, but Employer should become a Business capability rather than a separate identity type.
- Existing investments/protection surfaces should consume the Partner Catalogue rather than hard-coded/provider-specific presentation.
- Existing web surfaces should become role/Space-aware Workspaces and use the same Space switcher/context.
- Launch navigation is borrower-centred; preserve simplicity while expanding to complete Individual/Group journeys.

## Missing

- Canonical `financial_spaces` aggregate.
- Generic memberships, invitations and role assignments across Space types.
- Explicit separation of permission, entitlement and eligibility.
- Subscription plans/entitlements tied to Spaces.
- Standard Partner Catalogue and commercial agreement abstraction.
- Immutable Revenue Event model for subscriptions, commissions, revenue share, transactions, SaaS/API and servicing income.
- App-level Space switcher and complete Savings Group journey.
- Full assets/liabilities/net-worth domain linked to Space ownership.
- General debt/receivables/payables management beyond OpFin-issued loans.
- Safe-to-spend projection across commitments/goals.
- Mobile-completeness certification suite for Individual and Savings Group.
- Institutional onboarding orchestration for Business/Employer, SACCO and regulated partners.
- Explicit partner-failure/recovery acceptance suite across all partner product classes.

## Obsolete or conflicting assumptions

- Treating a user as exactly one account/persona type.
- Treating Employer as a separate legal entity from Business.
- Treating Investor and Fund Manager as equivalent onboarding types.
- Direct `user_id` ownership as the long-term boundary for all financial records.
- Any future design that makes Web mandatory for normal Individual or Savings Group operation.
- Any monetisation rule where commission/revenue share changes financial-health advice.
- Building another payment/billing truth inside OpFin when CPay/Cito is the approved execution/reconciliation boundary.

## Migration posture

Use expand → backfill → dual-read/dual-write where necessary → verify parity → enforce → retire. Do not destructively rename/drop legacy ownership columns in the first migration.
