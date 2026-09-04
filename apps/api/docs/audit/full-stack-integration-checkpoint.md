# Full-Stack Integration Checkpoint

Date: 2026-05-22

Scope: OpFin-BE and OpFin-FE after the investor-demo frontend/backend integration. This checkpoint does not introduce new product features.

## Executive status

The investor-demo vertical slice is structurally ready for a local demo once the runtime toolchains are available. The backend exposes the required Sanctum-protected demo endpoints, the frontend can switch from fixture mode to real backend mode with environment variables, and the demo flow has explicit mock labels for decisioning and mobile money.

The main blockers are operational rather than product-flow gaps in this environment: PHP, Composer, Pint, and npm are not available on the shell PATH, so backend tests, migrations, linting, frontend tests, type checks, linting, and builds could not be executed during this checkpoint.

## Backend API readiness

### Confirmed endpoints

Public and authentication endpoints:

| Method | Endpoint | Status | Notes |
| --- | --- | --- | --- |
| GET | `/api/health` | Present | Health check endpoint. |
| POST | `/api/register` | Present | Legacy registration endpoint. |
| POST | `/api/login` | Present | Returns bearer token and user payload used by the frontend. |
| POST | `/api/logout` | Present | Requires Sanctum authentication. |
| GET | `/api/profile` | Present | Requires Sanctum authentication and records sensitive profile audit event. |

Investor-demo customer endpoints:

| Method | Endpoint | Status | Notes |
| --- | --- | --- | --- |
| GET | `/api/demo/dashboard` | Present | Returns profile, KYC status, consent, latest application, decision, offer, and loan schedule relation. |
| POST | `/api/demo/consent` | Present | Grants mock credit-processing consent and records audit log. |
| DELETE | `/api/demo/consent` | Present | Revokes mock credit-processing consent and records audit log. |
| POST | `/api/demo/loan-applications` | Present | Validates payload, enforces KYC and consent gates, creates application, mock decision, and offer. |
| GET | `/api/demo/loan-applications/{application}/decision` | Present | Customer may view own decision; admin roles may view any. |
| GET | `/api/demo/loan-applications/{application}/offer` | Present | Customer may view own offer; admin roles may view any. |
| POST | `/api/demo/loan-offers/{offer}/accept` | Present | Accepts pending offer, creates loan account, repayment schedule, ledger entries, and mock mobile money disbursement in a transaction. |

Investor-demo admin endpoint:

| Method | Endpoint | Status | Notes |
| --- | --- | --- | --- |
| GET | `/api/demo/admin/investor-snapshot` | Present | Requires Sanctum and platform admin, operations, or support role check in the controller. |

Supporting endpoints consumed by the frontend:

| Method | Endpoint | Status | Notes |
| --- | --- | --- | --- |
| GET | `/api/products` | Present | Requires Sanctum; used by loan application form. |
| GET | `/api/institutions` | Present | Requires Sanctum; used by loan application form. |
| GET | `/api/product-terms/{product}` | Present | Requires Sanctum; used by loan application form. |
| GET | `/api/loan-balance/{user}` | Present | Legacy dashboard support. |
| POST | `/api/loan-applications/{id}/status` | Present | Legacy admin update endpoint, audit wrapped. |

### Authentication and access control

Sanctum authentication is wired through `auth:sanctum` on protected API routes. The frontend stores the returned bearer token in an HTTP-only cookie and passes it to server-side API calls.

Role-based access is implemented in the backend user model and middleware, and the demo admin snapshot performs a controller-level role check for `platform_admin`, `operations`, and `support`. Customer access to admin snapshot is covered by tests. Customers can view only their own decision and offer records.

### Response consistency

Newer API responses use the standard envelope:

```json
{
  "success": true,
  "message": "Human-readable message.",
  "data": {}
}
```

Errors from the demo controller use:

```json
{
  "success": false,
  "message": "Human-readable message.",
  "errors": {}
}
```

Legacy endpoints may still return older shapes. The investor-demo frontend uses the newer envelope for the demo flow.

## Frontend integration readiness

The Next.js frontend uses `NEXT_PUBLIC_OPFIN_API_URL` for the backend base URL. It remains in fixture mode unless `NEXT_PUBLIC_USE_MOCK_API=false` is set.

Confirmed integration points:

| Screen or flow | Backend API | Status |
| --- | --- | --- |
| Login | `POST /api/login` | Integrated through server action. |
| Session handling | HTTP-only token, role, and name cookies | Implemented. |
| Protected routes | Next middleware | Implemented for customer, admin, employer, savings, insurance, and investments routes. |
| Role-aware navigation | Role/group mapping | Implemented. |
| Dashboard | `/api/demo/dashboard`, `/api/profile`, `/api/loan-balance/{user}` | Uses real APIs when mock mode is disabled. |
| KYC status | `/api/demo/dashboard` | Reads backend user KYC fields; no live provider integration. |
| Consent management | `/api/demo/consent` | Integrated for grant and revoke. |
| Loan application | `/api/products`, `/api/institutions`, `/api/product-terms/{product}`, `/api/demo/loan-applications` | Integrated. |
| Decision result | `/api/demo/loan-applications/{application}/decision` or latest dashboard record | Integrated. |
| Loan offer | `/api/demo/loan-applications/{application}/offer`, `/api/demo/loan-offers/{offer}/accept` | Integrated. |
| Loan account | `/api/demo/dashboard` | Integrated through latest demo loan relation. |
| Repayment schedule | `/api/demo/dashboard` | Integrated through latest demo loan schedule relation. |
| Admin credit review | `/api/demo/admin/investor-snapshot` | Integrated. |
| Admin audit trail | `/api/demo/admin/investor-snapshot` | Integrated. |

## Demo flow validation

Code review confirms the intended path exists:

1. Customer logs in through `/api/login`.
2. Frontend stores bearer token and user role cookies.
3. Customer dashboard loads demo dashboard/profile data.
4. Customer checks KYC status from backend user fields.
5. Customer grants credit-processing consent.
6. Customer submits a loan application.
7. Backend verifies KYC and consent.
8. Backend creates a mock decision with reason codes.
9. Approved decisions create a mock offer.
10. Customer accepts the offer.
11. Backend creates the loan account.
12. Backend generates repayment schedule rows.
13. Backend records ledger entries.
14. Backend records a sandbox/mock mobile money disbursement.
15. Admin views customers, applications, decisions, offers, loans, schedules, ledger entries, mobile money, and audit events through the investor snapshot endpoint.

Runtime execution of the full flow could not be completed in this shell because PHP/Composer/npm are unavailable.

## Error state coverage

| Error state | Backend status | Frontend status | Notes |
| --- | --- | --- | --- |
| Unauthenticated request | Covered by Sanctum 401 behavior | Classified as `unauthorized` | Needs runtime verification. |
| Forbidden request | Demo controller returns 403 for customer/admin violations | Classified as `forbidden` and displayed through redirects/notices | Customer admin denial has backend test coverage. |
| Validation error | Demo application returns 422 with field errors | Classified as `validation` | UI currently displays message-level feedback, not field-by-field rendering. |
| Missing KYC | Demo service returns 403 | Displayed as forbidden/error notice | Requires seeded demo user KYC for happy path. |
| Missing consent | Demo service returns 403 | Displayed on loan application page | Backend test coverage exists. |
| Declined decision | Demo decisioning supports declined outcomes | Decision UI can render non-approved status | Add explicit end-to-end demo script step before investor rehearsal. |
| Referred/manual review decision | Not implemented | Not implemented | Documented gap; do not present as supported. |
| Failed mock payment | Mobile money adapter has failure-state concepts | No investor-demo UI trigger | Not part of current demo path. |
| Duplicate payment/webhook event | Mobile-money idempotency tests exist from prior integration work | No direct UI trigger | Duplicate offer acceptance is blocked in demo flow. |
| Server error | Laravel exception behavior applies | API client classifies as `server` or `network` | Needs runtime/browser verification. |

## Test and build results

Commands attempted on 2026-05-22:

| Repo | Command | Result |
| --- | --- | --- |
| OpFin-BE | `git diff --check` | Passed. |
| OpFin-BE | `php --version` | Blocked: `php` not recognized. |
| OpFin-BE | `composer --version` | Blocked: `composer` not recognized. |
| OpFin-BE | `php artisan test` | Blocked: `php` not recognized. |
| OpFin-BE | `.\vendor\bin\pint --test` | Blocked: Pint executable not present/recognized. |
| OpFin-FE | `git diff --check` | Passed; warning on pre-existing dirty Flutter file `opfin-frontend/lib/otp_screen.dart`. |
| OpFin-FE | `npm --version` | Blocked: `npm` not recognized. |
| OpFin-FE | `npm run test` | Blocked: `npm` not recognized. |
| OpFin-FE | `npm run typecheck` | Blocked: `npm` not recognized. |
| OpFin-FE | `npm run lint` | Blocked: `npm` not recognized. |
| OpFin-FE | `npm run build` | Blocked: `npm` not recognized. |

## What works

- Backend demo endpoints required by the frontend exist.
- Sanctum bearer-token flow is implemented for API calls.
- Role checks exist for admin demo access and frontend protected admin routes.
- KYC and consent gates are enforced before demo credit processing.
- Approved demo applications can produce offers, loan accounts, repayment schedules, ledger entries, mock mobile money records, and audit logs.
- Frontend can consume real backend APIs when mock mode is disabled.
- Customer and admin demo screens are wired to the documented backend contract.
- Mock integrations are labelled in API responses and UI notices.

## What is broken or blocked

- Local verification is blocked by missing PHP, Composer, Pint, and npm executables.
- The frontend middleware treats either access-token or role cookie as a session indicator; API calls still require the token, so a role-only cookie can pass route middleware but fail data loading.
- Legacy backend endpoints and tables still use mixed response shapes and some decimal/string money columns outside the demo tables.
- The admin snapshot role check is controller-level rather than route-middleware-level.

## What remains mocked

- Affordability and credit decisioning.
- Mobile money disbursement.
- KYC provider verification.
- CRB, insurance, investments, employer benefits, and savings integrations.
- Frontend fixtures whenever `NEXT_PUBLIC_USE_MOCK_API` is not explicitly set to `false`.
- Investor-demo admin snapshot is a demo aggregation endpoint, not production reporting.

## Must fix before investor demo

1. Install or expose PHP 8.2+, Composer, and npm in the execution environment.
2. Run `composer install`, `php artisan migrate:fresh --seed`, `php artisan test`, and `.\vendor\bin\pint --test`.
3. Run `npm install`, `npm run typecheck`, `npm run lint`, `npm run test`, and `npm run build`.
4. Set frontend environment to `NEXT_PUBLIC_USE_MOCK_API=false` and `NEXT_PUBLIC_OPFIN_API_URL=http://localhost:<backend-port>/api` for the integrated demo.
5. Rehearse the seeded customer/admin demo credentials end to end in a browser.
6. Decide whether to tighten frontend middleware so a protected route requires `opfin_access_token`, not role-only cookies.

## Recommended next prompt

Use this next:

> Prepare OpFin for an investor-demo rehearsal. Install/verify local PHP, Composer, and npm toolchains, run backend migrations and seeders, start the Laravel API and Next.js frontend, set `NEXT_PUBLIC_USE_MOCK_API=false`, execute the full demo script in browser, fix only demo-blocking bugs, and update the demo status docs with verified screenshots or screenshot notes.
