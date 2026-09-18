# Frontend API dependencies

Updated: 18 September 2026

This file is retained as a compact implementation checklist. The canonical web contract is `api-contract-dependencies.md` and the canonical API docs live under `apps/api/docs/api`.

## Base URL

The web app uses `NEXT_PUBLIC_OPFIN_API_URL`. Flutter uses its build-time API base configuration. Do not hard-code a production endpoint into business logic.

## Current dependency groups

- Authentication: OTP/register/login/PIN reset/profile/logout.
- Identity: KYC case/status and explicit consent.
- Credit: credit profile/options/applications/decisions/offers.
- Payments: verified wallets, repayment, provider status/reconciliation.
- Receipts: customer receipt history/detail.
- Support: complaint/support cases and SLA state.
- Governance: regulatory reports, books/records, UMRA controls.
- Assisted channels: WhatsApp/USSD share server-authoritative borrower state.

## Client implementation rules

- Centralise auth/base URL/error parsing.
- Preserve backend validation messages safely.
- Never authorise from locally cached roles alone.
- Never create client-side balances, limits, pricing or settlement truth.
- Keep sensitive data out of logs/preferences.
- Use idempotency for financial initiation.
- Keep provider-gated capabilities visibly unavailable until connected.

Use `python3 scripts/search-api.py "<term>"` for exact routes.
