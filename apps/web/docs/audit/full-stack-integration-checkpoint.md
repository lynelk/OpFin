# Full-Stack Integration Checkpoint

Date: 2026-05-22

Scope: OpFin-FE after integration with OpFin-BE investor-demo endpoints.

## Frontend status

The Next.js frontend is wired for the investor-demo backend contract. It uses `NEXT_PUBLIC_OPFIN_API_URL` for the API base URL. Local fixture mode is opt-in only with `NEXT_PUBLIC_USE_MOCK_API=true`, and production builds reject that setting.

## Integrated flows

| Flow | Frontend implementation | Backend dependency | Status |
| --- | --- | --- | --- |
| Login | Server action stores bearer token, role, and name cookies | `POST /api/login` | Integrated. |
| Protected routes | Next middleware guards customer/admin/employer/wealth routes | Auth cookies | Implemented. |
| Role-aware navigation | Navigation groups and role filters | Backend user role | Implemented. |
| Dashboard | Server component API calls | `/api/demo/dashboard`, `/api/profile`, `/api/loan-balance/{user}` | Integrated. |
| KYC status | Reads demo dashboard KYC object | `/api/demo/dashboard` | Integrated; provider is not live. |
| Consent | Grant/revoke server actions | `POST/DELETE /api/demo/consent` | Integrated. |
| Loan application | Product reference data plus demo submit action | `/api/products`, `/api/institutions`, `/api/product-terms/{product}`, `/api/demo/loan-applications` | Integrated. |
| Decision | Loads specific application decision or latest dashboard decision | `/api/demo/loan-applications/{application}/decision` | Integrated. |
| Offer | Loads offer and accepts through server action | `/api/demo/loan-applications/{application}/offer`, `/api/demo/loan-offers/{offer}/accept` | Integrated. |
| Account and schedule | Reads latest accepted demo loan from dashboard | `/api/demo/dashboard` | Integrated. |
| Admin credit review | Reads investor snapshot | `/api/demo/admin/investor-snapshot` | Integrated. |
| Admin audit trail | Reads investor snapshot audit trail | `/api/demo/admin/investor-snapshot` | Integrated. |

## Error states

The API client maps HTTP status codes to `validation`, `unauthorized`, `forbidden`, `server`, and `network` error kinds. Server actions redirect back to the relevant screen with an error kind and message.

Known limitations:

- Validation errors are displayed as message-level notices, not per-field inline errors.
- Middleware currently treats either `opfin_access_token` or `opfin_role` as a session signal; server actions still require the token for real API calls.
- Referred/manual review decisions and failed mock payment UI states are not implemented.

## What remains mocked

- All API calls when `NEXT_PUBLIC_USE_MOCK_API=true`.
- Savings, insurance, investments, and employer portal content.
- KYC provider verification, CRB, live mobile money, and production compliance reporting.
- Admin login shortcut routes are demo-only helpers.

## Verification attempted

| Command | Result |
| --- | --- |
| `git diff --check` | Passed with a warning on pre-existing dirty `opfin-frontend/lib/otp_screen.dart`. |
| `npm --version` | Blocked: `npm` not recognized. |
| `npm run test` | Blocked: `npm` not recognized. |
| `npm run typecheck` | Blocked: `npm` not recognized. |
| `npm run lint` | Blocked: `npm` not recognized. |
| `npm run build` | Blocked: `npm` not recognized. |

## Must fix before investor demo

1. Make npm available and run `npm install`.
2. Run `npm run typecheck`, `npm run lint`, `npm run test`, and `npm run build`.
3. Start the Laravel API with seeded demo data.
4. Set `NEXT_PUBLIC_OPFIN_API_URL` to the Laravel `/api` base URL.
5. Set `NEXT_PUBLIC_USE_MOCK_API=false`.
6. Rehearse customer and admin flows in the browser.

## Recommended next prompt

> Prepare the frontend for investor-demo rehearsal. Run the Next.js app against the seeded Laravel API with `NEXT_PUBLIC_USE_MOCK_API=false`, verify every customer and admin screen in browser, fix only demo-blocking issues, and update the demo status docs with the verified browser results.
