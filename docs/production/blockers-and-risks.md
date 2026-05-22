# Blockers and Risks

Date: 2026-05-22

## Critical

| Risk | Evidence | Required action |
| --- | --- | --- |
| Frontend checks cannot run | `npm` is unavailable. | Restore Node/npm, install dependencies, run typecheck/lint/tests/build. |
| Production KYC flow missing | KYC screen reads backend status only. | Build provider-backed KYC submission/review/retry flow after backend contract. |
| Production consent flow missing | Consent screen uses demo consent contract. | Build policy-versioned production consent UX. |
| Decisioning is demo/mock | Decision screen shows investor-demo results. | Build production decision/referral/manual review UX. |
| Repayment flow missing | No repayment initiation/status workflow in Next app. | Build repayment collection and payment status flows. |
| Support console missing | No customer-support case or lookup workflow. | Build support operations module before replacement. |
| Reconciliation UI missing | No payment exception/reconciliation console. | Build mobile-money operations UI. |
| Compliance reporting missing | No reporting screens. | Build reports after backend contracts. |

## High

| Risk | Evidence | Required action |
| --- | --- | --- |
| Mock mode remains easy to enable | API client defaults to fixtures unless `NEXT_PUBLIC_USE_MOCK_API=false`. | Add production guard to fail builds/deploys when mock mode or mock route is enabled. |
| Mock login route exists | `/api/mock-login` sets sandbox cookies. | Disable/remove from production builds. |
| Route middleware trusts role cookie | Protected routes pass with token or role cookie. | Require access token and validate session shape. |
| Admin screens are demo/placeholder | Admin dashboard placeholder and snapshot-driven review. | Build real operations screens. |
| Validation UX is limited | Forms rely on HTML required and redirect messages. | Add typed validation and field-level errors. |

## Medium

| Risk | Evidence | Required action |
| --- | --- | --- |
| Accessibility unverified | Basic labels exist but no audit run. | Run keyboard/screen-reader/contrast audit. |
| Mobile responsiveness unverified | CSS exists but no browser test run. | Verify mobile viewport flows. |
| Empty/loading states are generic | StateNotice components exist. | Add workflow-specific guidance. |

## Low

| Risk | Evidence | Required action |
| --- | --- | --- |
| Future product placeholders | Savings, insurance, investments, employer portal. | Exclude from cutover unless required by live system. |

## Production guardrails

- Do not deploy with `NEXT_PUBLIC_USE_MOCK_API` enabled.
- Do not deploy `/api/mock-login`.
- Do not deploy demo shortcuts on login/admin-login.
- Do not point production users to `/api/demo/*` workflows.
- Do not claim build readiness until npm checks pass.
