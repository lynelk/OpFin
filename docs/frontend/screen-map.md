# OpFin Frontend Screen Map

## Repository Status

The repository already had a Flutter mobile application in `opfin-frontend/`, but it did not contain a proper Next.js + TypeScript web frontend for the customer, admin, employer, and investor-demo surfaces requested for OpFin.

A new Next.js App Router scaffold has been added at the repository root. The existing Flutter app remains untouched.

## Current Web Routes

Authentication:

- `/login`: backend phone/password login form with sandbox customer shortcut.
- `/admin-login`: backend phone/password admin login form with sandbox admin/operations shortcuts.
- `/api/mock-login`: local-only route that sets a mock role cookie.

Customer:

- `/dashboard`: customer dashboard with KYC status, balance, and applications summary.
- `/kyc`: KYC/NIN status screen using the profile contract.
- `/consent`: sandbox consent create/revoke state because backend consent records are not implemented.
- `/loans/apply`: loan application form wired to products, institutions, product terms, and application submission.
- `/loans/decision`: decision display derived from documented application status because no formal decision API exists yet.
- `/loans/offer`: sandbox-labelled offer placeholder because the backend offer module is missing.
- `/loans/schedule`: sandbox-labelled repayment schedule because no schedule API route is documented.
- `/loans/account`: loan account screen using known application and loan fields.

Admin and operations:

- `/admin/dashboard`: admin dashboard placeholder.
- `/admin/credit-review`: sandbox queue using known loan application fields, with status update wired to the documented admin endpoint.
- `/admin/audit-trail`: sandbox-labelled audit trail placeholder using audit event concepts from the backend audit log.

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

Backend login stores `opfin_access_token`, `opfin_role`, and `opfin_name` cookies for server-rendered API calls and role-aware navigation. Sandbox login sets the same cookie shape for local demo flow, and the switch-role action clears session cookies before returning to `/login`.

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

- login
- profile
- products
- institutions
- product terms
- loan applications
- loan application submission
- loan application status update
- loan balance

Placeholder areas:

- consent management
- affordability and decision results
- loan offers
- repayment schedules
- employer portal
- savings
- insurance
- investments
- admin dashboard metrics
- audit trail API listing
