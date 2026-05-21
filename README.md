# OpFin-FE

OpFin-FE now contains two frontend surfaces:

- `opfin-frontend/`: existing Flutter mobile application.
- repo root `src/`: Next.js + TypeScript web/customer/admin portal scaffold.

The Next.js app is mock-first and uses only API contracts currently documented from the Laravel backend. Screens whose backend contracts are incomplete are present as protected placeholders rather than invented data models.

## Next.js Setup

```bash
npm install
cp .env.example .env.local
npm run dev
```

## Environment

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=true
```

Set `NEXT_PUBLIC_USE_MOCK_API=false` to call the Laravel backend for documented endpoints. Screens without backend contracts remain clearly sandbox-labelled.

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
2. Use `/login` for backend-backed phone/password authentication, or keep `NEXT_PUBLIC_USE_MOCK_API=true` and use the sandbox shortcuts.
3. Visit `/dashboard` and `/kyc` to verify profile-backed customer data.
4. Visit `/consent` to create or revoke sandbox consent. The backend does not yet expose a dedicated consent API.
5. Submit `/loans/apply`; the form posts documented product, term, institution, amount, and reason fields.
6. Review `/loans/decision`, `/loans/offer`, `/loans/schedule`, and `/loans/account`.
7. Use `/admin-login` and `/admin/credit-review` for the operations review slice. The status update uses the documented admin endpoint, while the queue itself remains sandbox-labelled until an admin list API exists.

Known contract gaps are kept visible in the UI: consent records, affordability decisions, loan offers, audit listing, employer benefits, savings, insurance, and investments are not treated as live integrations.

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

Sandbox-labelled demo areas:

- Consent create/revoke state
- Formal affordability and decision payloads
- Loan offers
- Repayment schedules, because the backend does not currently document a schedule route
- Admin application list and audit trail list
