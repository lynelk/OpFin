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

Set `NEXT_PUBLIC_USE_MOCK_API=false` only after the backend contracts are available and compatible.

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

`middleware.ts` protects customer, admin, employer, savings, insurance, and investment routes using a mock `opfin_role` cookie. The mock login endpoints are for local development only and should be replaced with real backend-backed session handling before production.
