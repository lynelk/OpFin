# OpFin-FE

OpFin-FE now contains two frontend surfaces:

- `opfin-frontend/`: existing Flutter mobile application.
- repo root `src/`: Next.js + TypeScript web/customer/admin portal scaffold.

The Next.js app supports a complete investor-demo vertical slice against the Laravel `/api/demo/*` endpoints. When `NEXT_PUBLIC_USE_MOCK_API=true`, the same screens run with labelled sandbox fixtures.

## Next.js Setup

```bash
npm install
cp .env.example .env.local
npm run dev
```

## Environment

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
```

Set `NEXT_PUBLIC_USE_MOCK_API=false` to call the Laravel backend for documented endpoints. Screens without backend contracts remain clearly sandbox-labelled.

## Railway Production Boundary

The canonical Railway production frontend is the `opfin-web` service built from the repository root. Production must set `NODE_ENV=production` and `NEXT_PUBLIC_USE_MOCK_API=false`. Only browser-safe values may use the `NEXT_PUBLIC_` prefix; provider credentials and other secrets belong exclusively in backend services. The web service is stateless and should not mount persistent storage.

## Build, Lint, Test

```bash
npm run typecheck
npm run lint
npm run test
npm run build
npm run check
```

## Included Web Routes

- `/login`
- `/admin-login`
- `/dashboard`
- `/kyc`
- `/consent`
- `/loans/apply`
- `/loans/decision`
- `/loans/offer`
- `/loans/schedule`
- `/loans/account`
- `/admin/dashboard`
- `/admin/credit-review`
- `/admin/audit-trail`
- `/employer`
- `/savings`
- `/insurance`
- `/investments`

## Route Protection

`middleware.ts` protects customer, admin, employer, savings, insurance, and investment routes using backend login cookies or generated sandbox cookies. The app stores `opfin_access_token`, `opfin_role`, and `opfin_name` as HTTP-only cookies and clears them through the switch-role action. Tokens are never hardcoded; sandbox shortcuts generate local demo session IDs.

## Investor Demo Flow

1. Start the Laravel backend and expose it at `NEXT_PUBLIC_OPFIN_API_URL`.
2. Use `/login` for backend-backed phone/password authentication. Use `NEXT_PUBLIC_USE_MOCK_API=true` only for isolated local fixture review; production builds reject it.
3. Visit `/dashboard` and `/kyc` to verify profile-backed customer data.
4. Visit `/consent` to grant or revoke investor-demo credit-processing consent.
5. Submit `/loans/apply`; the backend runs mock affordability and decisioning and returns reason codes.
6. Review `/loans/decision`, then `/loans/offer`, and accept the approved offer.
7. Review `/loans/account` and `/loans/schedule` after the backend creates the loan account, ledger entries, repayment schedule, and sandbox mobile money disbursement record.
8. Use `/admin-login`, `/admin/credit-review`, and `/admin/audit-trail` to review the admin investor-demo snapshot.

Mock integration areas are visibly labelled: affordability, decisioning, demo consent, and sandbox mobile money disbursement. No live mobile money, CRB, KYC, insurance, savings, investment, or employer integrations are connected.

Backend-connected demo endpoints:

- `POST /login`
- `GET /profile`
- `GET /products`
- `GET /institutions`
- `GET /product-terms/{product}`
- `POST /loan-applications`
- `GET /loan-applications/{user}`
- `GET /loan-balance/{user}`
- `POST /loan-applications/{id}/status`
- `GET /demo/dashboard`
- `POST /demo/consent`
- `DELETE /demo/consent`
- `POST /demo/loan-applications`
- `GET /demo/loan-applications/{application}/decision`
- `GET /demo/loan-applications/{application}/offer`
- `POST /demo/loan-offers/{offer}/accept`
- `GET /demo/admin/investor-snapshot`

Sandbox-labelled demo areas:

- Mock affordability and decision payloads
- Demo consent lifecycle
- Sandbox mobile money disbursement
- Insurance, savings, investment, employer, and production KYC integrations

See `docs/demo/investor-demo-script.md`, `docs/demo/screenshots-checklist.md`, and `docs/demo/demo-limitations.md`.
