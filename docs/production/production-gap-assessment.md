# Production Gap Assessment

Date: 2026-05-22

Scope: OpFin-FE as a replacement frontend for the current live OpFin solution.

## Bottom line

The current Next.js frontend is not production-ready as a replacement system. It is a useful investor-demo and integration scaffold, but major customer, admin, support, accessibility, responsive, validation, and operational flows remain incomplete or mock-backed.

## Implemented screens

- Login.
- Admin login.
- Customer dashboard.
- KYC status.
- Consent management.
- Loan application.
- Loan decision result.
- Loan offer.
- Loan account.
- Repayment schedule.
- Admin dashboard placeholder.
- Admin credit review.
- Admin audit trail.
- Employer portal placeholder.
- Savings placeholder.
- Insurance placeholder.
- Investments placeholder.

## Fully functional by code inspection

- Environment-based API client using `NEXT_PUBLIC_OPFIN_API_URL`.
- Mock fallback controlled by `NEXT_PUBLIC_USE_MOCK_API`.
- Login server action that stores HTTP-only token/role/name cookies.
- Protected route middleware for portal routes.
- Role-aware navigation groups.
- Customer investor-demo screens wired to backend `/api/demo/*` endpoints when mock mode is disabled.
- Admin credit review and audit trail wired to the backend investor snapshot endpoint.
- Generic loading, empty, and error notices.

## Partially implemented

- Customer flows: demo credit journey exists; production repayment, support, profile-management, statements, KYC recovery, and payment exception flows are incomplete.
- Admin flows: demo snapshot and placeholders exist; production operations console is missing.
- Error states: generic status handling exists; field-level validation and recovery flows are incomplete.
- Protected routes: middleware exists but accepts role-only cookies as a session signal.
- API integration: real backend mode exists, but mock mode remains default unless explicitly disabled.
- Form validation: mostly HTML required fields and server redirect messages.
- Accessibility: basic labels exist; no full audit was run.
- Mobile responsiveness: CSS exists; no browser/device verification was run.

## Mocked, sandbox-only, or placeholders

- All data when `NEXT_PUBLIC_USE_MOCK_API` is not exactly `false`.
- `/api/mock-login`.
- Sandbox customer/admin shortcut links.
- Investor-demo decisioning labels and data.
- Investor-demo consent labels and data.
- Mock mobile money disbursement display.
- Admin dashboard placeholder.
- Employer portal placeholder.
- Savings placeholder.
- Insurance placeholder.
- Investments placeholder.
- Any production CRB, KYC provider, live mobile money, support, and compliance workflows.

## Missing for production replacement

- Customer registration/account recovery if replacement scope requires it.
- Production KYC submission/review/retry flow.
- Production consent policy-version flow.
- Production loan application state tracking.
- Manual review/referral screens.
- Offer disclosure and acceptance evidence UX.
- Real repayment collection and payment-status screens.
- Customer statement and transaction history.
- Support console.
- Operational reconciliation views.
- Compliance reporting views.
- Production dashboard metrics.
- Fine-grained field validation.
- Accessibility and mobile audits.
- Production environment guard that disables mock routes and fixtures.

## Verification attempted

| Command | Result |
| --- | --- |
| `git diff --check` | Passed with warning on pre-existing dirty `opfin-frontend/lib/otp_screen.dart`. |
| `npm --version` | Blocked: `npm` not recognized. |
| `npm run typecheck` | Blocked: `npm` not recognized. |
| `npm run lint` | Blocked: `npm` not recognized. |
| `npm run test` | Blocked: `npm` not recognized. |
| `npm run build` | Blocked: `npm` not recognized. |

## Production decision

Do not use this frontend as the production replacement yet. Use it as a foundation for the replacement build after backend production contracts are finalized and the frontend toolchain can run repeatable checks.
