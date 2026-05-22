# Blockers and Risks

Date: 2026-05-22

## Critical

| Risk | Evidence | Required action |
| --- | --- | --- |
| Frontend checks cannot run | `npm` is unavailable. | Restore Node/npm, install dependencies, run typecheck/lint/tests/build. |
| Production KYC flow incomplete | KYC screen now submits to `/api/kyc/cases` and reads `/api/kyc/status`, but provider-backed capture/retry UX is incomplete. | Build provider-backed KYC submission/review/retry flow after backend contract. |
| Production consent flow incomplete | Consent screen now uses `/api/consents`, but policy publication and full consent history UX are incomplete. | Build policy-versioned production consent UX. |
| Decisioning is demo/mock | Decision screen shows investor-demo results. | Build production decision/referral/manual review UX. |
| Repayment flow missing | No repayment initiation/status workflow in Next app. | Build repayment collection and payment status flows. |
| Support console incomplete | Support case intake/list exists, but lookup, assignment, SLA, notes, and closure workflow are incomplete. | Complete support operations module before replacement. |
| Reconciliation UI incomplete | Reconciliation run intake/list exists, but provider file matching and exception resolution are incomplete. | Complete mobile-money operations UI. |
| Compliance reporting incomplete | Report record intake/list exists, but regulator-specific exports and approvals are incomplete. | Complete report exports after backend contracts. |

## High

| Risk | Evidence | Required action |
| --- | --- | --- |
| Mock mode must remain production-disabled | API client now requires `NEXT_PUBLIC_USE_MOCK_API=true` for fixtures, and Next production builds fail when that flag is enabled. | Keep deployment configuration locked to real API mode. |
| Mock login route exists but is guarded | `/api/mock-login` now returns 404 in production and unless `OPFIN_ENABLE_DEMO_SHORTCUTS=true`; Next production builds fail when demo shortcuts are enabled. | Keep route disabled in production and remove entirely once demo workflows are retired. |
| Route middleware only checks token presence | Protected routes now require `opfin_access_token`, but middleware does not validate the bearer token cryptographically. | Rely on backend authorization for data and consider server-side session validation for sensitive pages. |
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
