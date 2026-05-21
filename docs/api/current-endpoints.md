# Current API Endpoint Summary

## Public Endpoints

### `GET /api/health`

Returns service health in the standard response envelope.

### `POST /api/register`

Creates a customer user and returns a Sanctum token. Registration currently uses a legacy response shape and should be normalized.

### `POST /api/login`

Authenticates by phone and password and returns a Sanctum bearer token.

### `POST /api/logout`

Requires Sanctum authentication. Revokes the current access token.

### `POST /api/generate-otp`

Generates a short-lived OTP and queues SMS delivery.

### `POST /api/verify-otp`

Verifies a phone/OTP pair.

### `POST /api/reset-password`

Requires a valid, unexpired OTP before resetting a user password. Existing tokens are revoked after a successful reset.

## Authenticated Customer Endpoints

### `GET /api/profile`

Returns authenticated user details and permissions. Successful access is audit logged as `profile.viewed`.

### `POST /api/validate-nin`

Validates a NIN through the configured CRB provider and stores validation fields on the user. This is not yet a complete KYC lifecycle.

### `POST /api/credit-scores`

Fetches or reuses a recent credit score. This endpoint currently sends `client_consented: Yes` to the provider but does not verify a first-party consent record.

### `GET /api/products`

Lists loan products visible to the authenticated user.

### `GET /api/product-terms/{product}`

Lists loan terms for a loan product.

### `POST /api/loan-applications`

Creates a pending credit application after checking verified NIN status, uncleared loans, and existing pending applications.

Current gaps:

- No dedicated consent gate.
- No formal affordability check.
- Amount validation accepts numeric values but the database still needs integer minor-unit enforcement.

### `GET /api/loan-applications/{user}`

Lists loan applications for the authenticated user. Cross-user access is blocked.

### `GET /api/loan-balance/{user}`

Returns the authenticated user's loan balance. Cross-user access is blocked.

### `POST /api/loans/{loan}/repay`

Starts a repayment transaction for the authenticated user's loan. Duplicate pending repayment transactions are blocked.

## Internal Mobile Money Adapter

The provider-agnostic mobile money layer currently exposes service contracts, not public API routes. It supports disbursement requests, repayment collection requests, status lookup, webhook processing, reversal requests, failed-transaction handling, normalized provider responses, idempotency keys, webhook signature validation, reconciliation status, and audit logs.

The mock provider is intended for local development and tests. MTN Mobile Money and Airtel Money adapters are placeholders that do not make live calls.

Adapter requests must provide:

- `idempotency_key`
- positive integer `amount_minor`
- `currency`, defaulting to `UGX`
- `phone`

Payment events from this layer must not mutate loan balances directly; ledger impact should continue to pass through the financial processing service.

## Operations/Admin Endpoints

### `GET /api/admin/foundation-check`

Requires `platform_admin` or `operations` role through middleware.

### `POST /api/loan-applications/{id}/status`

Requires platform admin, operations, or legacy `is_admin`. Updates application status.

### `PATCH /api/transactions/{id}/approve`

Requires platform admin, operations, or legacy `is_admin`. Marks a pending transaction successful and passes it into loan processing.

## Payment Callback Endpoints

### `POST /api/handleCallback`

Generic payment callback. Requires `X-Opfin-Callback-Secret` header or `signature` payload value matching `PAYMENT_CALLBACK_SECRET`.

### `POST /api/airtel-callback`

Airtel callback. Requires callback secret.

### `POST /api/mtn-callback`

MTN callback. Requires callback secret.

## Standard Response Target

New endpoints should use:

```json
{
  "success": true,
  "message": "Operation completed.",
  "data": {}
}
```

Legacy endpoints still need normalization before public API stabilization.
