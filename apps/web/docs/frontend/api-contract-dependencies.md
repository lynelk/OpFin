# Frontend API Contract Dependencies

## Current API Client

The Next.js scaffold includes a centralized API client at:

- `src/lib/api/client.ts`

Environment variables:

- `NEXT_PUBLIC_OPFIN_API_URL`
- `NEXT_PUBLIC_USE_MOCK_API`

When `NEXT_PUBLIC_USE_MOCK_API` is not `false`, the app uses local mock data. When it is `false`, documented backend endpoints are called with the Sanctum bearer token stored by the login server action.

## Known Backend Contracts Used

The frontend only relies on fields visible in the current backend/frontend audit documentation.

### `POST /login`

Used by:

- `/login`
- `/admin-login`

Expected fields under `data`:

- `access_token`
- `token_type`
- `user.id`
- `user.name`
- `user.phone`
- `user.role`

### `GET /profile`

Used by:

- `/dashboard`
- `/kyc`

Expected fields:

- `user.id`
- `user.name`
- `user.phone`
- `user.role`
- `user.national_id`
- `user.date_of_birth`
- `user.nin_status`
- `permissions`

### `GET /products`

Used by:

- `/loans/apply`

Expected fields:

- `id`
- `name`
- `status`
- optional `institution.id`
- optional `institution.name`

### `GET /institutions`

Used by:

- `/loans/apply`

Expected fields:

- `id`
- `name`

### `GET /product-terms/{productId}`

Used by:

- `/loans/apply`

Expected fields:

- `id`
- `loan_product_id`
- `interest_rate`
- `interest_type`
- `interest_cycle`
- `repayment_frequency`
- `duration`
- `status`

### `GET /loan-applications/{userId}`

Used by:

- `/dashboard`
- `/loans/account`
- `/loans/decision`

Expected fields:

- `id`
- `amount`
- `status`
- `reason`
- optional `loan_product`
- optional `loan_product_term`
- optional `loan.id`
- optional `loan.status`
- optional `loan.outstanding_balance`
- optional `loan.repayment_start_date`

### `GET /loan-balance/{userId}`

Used by:

- `/dashboard`

Expected fields:

- `outstandingAmount`

### `POST /loan-applications`

Used by:

- `/loans/apply`

Submitted fields:

- `loan_product_id`
- `loan_product_term_id`
- `institution_id`
- `amount`
- `reason`

Expected fields:

- created loan application payload under `data`

### `POST /loan-applications/{id}/status`

Used by:

- `/admin/credit-review`

Submitted fields:

- `status`

Expected fields:

- updated loan application payload under `data`

## Mock-Only Until Backend Contracts Exist

These screens are intentionally placeholder-backed:

- consent management
- loan decision result
- loan offer
- admin dashboard metrics
- audit trail listing
- employer portal
- savings
- insurance
- investments

Required backend contracts before real integration:

- consent records and revocation
- affordability checks
- formal loan decision result
- loan offer
- repayment schedule API endpoint
- loan account detail endpoint
- audit trail listing endpoint
- employer benefit endpoints
- savings, insurance, and investment endpoints

## Authentication Dependencies

Current web scaffold supports backend login through `/login` and `/admin-login`, storing the returned bearer token in an HTTP-only cookie for server-side API calls. `/api/mock-login` remains available for sandbox investor-demo walkthroughs and sets the same local cookie shape with a generated sandbox session ID. The switch-role action clears local cookies.

Required production contract:

- logout endpoint
- `401` and `403` behavior
- CSRF/session or token storage strategy for web

## Integration Rule

Do not connect a screen to a backend endpoint until its response shape is documented. Keep placeholders explicit when backend contracts are incomplete.
