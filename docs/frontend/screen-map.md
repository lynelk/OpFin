# OpFin Frontend Screen Map

## Repository Status

The repository already had a Flutter mobile application in `opfin-frontend/`, but it did not contain a proper Next.js + TypeScript web frontend for the customer, admin, employer, and investor-demo surfaces requested for OpFin.

A new Next.js App Router scaffold has been added at the repository root. The existing Flutter app remains untouched.

## Current Web Routes

Authentication:

- `/login`: mock customer login selector.
- `/admin-login`: mock admin/operations login selector.
- `/api/mock-login`: local-only route that sets a mock role cookie.

Customer:

- `/dashboard`: customer dashboard with KYC status, balance, and applications summary.
- `/kyc`: KYC/NIN status screen using the profile contract.
- `/consent`: consent management placeholder because backend consent records are not implemented.
- `/loans/apply`: loan application form shell using known loan product/application fields.
- `/loans/decision`: loan decision result placeholder because no decision API exists yet.
- `/loans/offer`: loan offer placeholder because the backend offer module is missing.
- `/loans/schedule`: repayment schedule screen using known schedule fields.
- `/loans/account`: loan account screen using known application and loan fields.

Admin and operations:

- `/admin/dashboard`: admin dashboard placeholder.
- `/admin/credit-review`: credit application review queue using known loan application fields.
- `/admin/audit-trail`: audit trail placeholder using audit event concepts from the backend audit log.

Employer and future modules:

- `/employer`: employer portal placeholder.
- `/savings`: savings placeholder.
- `/insurance`: insurance placeholder.
- `/investments`: investment placeholder.

## Route Protection

`middleware.ts` protects:

- customer routes
- loan routes
- admin routes
- employer route
- future module routes

Access is currently mock-cookie based:

- customer routes: any mock role
- admin routes: `platform_admin`, `operations`, `support`
- employer route: `platform_admin`, `employer_admin`

Real authentication should replace the mock cookie once the backend session/token contract is finalized for the web frontend.

## Navigation

Navigation is role-aware through `src/lib/navigation.ts` and `src/lib/auth/session.ts`.

Groups:

- Customer
- Future modules
- Operations
- Employer

## Screen Contract Policy

Screens use mock data only where the backend API documentation already exposes comparable fields. Missing backend modules remain placeholders and do not invent request/response shapes.

Known backend-backed areas:

- profile
- products
- product terms
- loan applications
- loan balance
- repayment schedules based on backend model fields

Placeholder areas:

- consent management
- affordability and decision results
- loan offers
- employer portal
- savings
- insurance
- investments
- admin dashboard metrics
- audit trail API listing

