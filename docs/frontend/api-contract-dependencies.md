# Frontend API Contract Dependencies

## Current API Client

The Next.js scaffold includes a centralized API client at:

- `src/lib/api/client.ts`

Environment variables:

- `NEXT_PUBLIC_OPFIN_API_URL`
- `NEXT_PUBLIC_USE_MOCK_API`

When `NEXT_PUBLIC_USE_MOCK_API` is not `false`, the app uses local mock data.

## Known Backend Contracts Used

The frontend only relies on fields visible in the current backend/frontend audit documentation.

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

### `GET /product-terms/{productId}`

Prepared in the API client.

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
- `/admin/credit-review`

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
- loan decision result
- loan offer
- repayment schedule API endpoint
- loan account detail endpoint
- audit trail listing endpoint
- employer benefit endpoints
- savings, insurance, and investment endpoints

## Authentication Dependencies

Current web scaffold uses mock cookies through `/api/mock-login`.

Required production contract:

- login endpoint suitable for browser session handling
- logout endpoint
- authenticated profile endpoint
- role/permission response
- `401` and `403` behavior
- CSRF/session or token storage strategy for web

## Integration Rule

Do not connect a screen to a backend endpoint until its response shape is documented. Keep placeholders explicit when backend contracts are incomplete.
