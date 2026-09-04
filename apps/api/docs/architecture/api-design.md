# API Design

## API Principles

- APIs are contracts. Changes must be backward-compatible unless versioned.
- Validate every request before domain logic.
- Authorize every protected action after authentication and before mutation.
- Derive actor identity from Sanctum, not from request payload.
- Return consistent JSON response shapes.
- Avoid leaking internal exception details.

## Authentication

Use Laravel Sanctum for mobile/API access.

Expected patterns:

- Public endpoints: login, registration, OTP initiation/verification, password reset, provider callbacks.
- Protected endpoints: user profile, financial products, applications, loans, repayments, KYC, consent, reports.
- Admin endpoints: protected by Sanctum or web session plus policy/role checks.

Token rules:

- Issue tokens only after successful authentication or verified OTP flow.
- Revoke current token on logout.
- Revoke all tokens after password reset or suspected account compromise.
- Never pass tokens in query strings.

## Versioning

Introduce `/api/v1` before expanding beyond the current mobile app surface. Keep current routes stable while moving new work into versioned route groups.

Versioned route groups should define:

- Middleware.
- Rate limits.
- Response format.
- Deprecation policy.

## Request Validation

Use form request classes for new endpoints when validation is more than trivial.

Validate:

- Amounts as integer minor units.
- Currency.
- Phone number format.
- Dates and date order.
- Product and term IDs.
- Status enums.
- Provider payload shape.
- Consent purpose and version.

Never accept:

- Role changes from unprivileged clients.
- User/institution ownership from mobile clients without policy checks.
- Provider status as final truth without verification.

## Response Format

Recommended success shape:

```json
{
  "success": true,
  "message": "Human-readable summary",
  "data": {}
}
```

Recommended error shape:

```json
{
  "success": false,
  "message": "Human-readable summary",
  "errors": {}
}
```

For list endpoints:

- Use Laravel pagination.
- Include `data`, `links`, and `meta`.
- Avoid returning unbounded collections for mobile flows.

## Idempotency

Required for:

- Loan application submission.
- Repayment initiation.
- Disbursement initiation.
- Provider callbacks.
- Employer payroll deductions.
- Investment or insurance order placement.

Use idempotency keys where client retry can create duplicate financial actions. Store key, actor, route/action, request hash, response summary, and expiry.

## Rate Limiting

Add route-specific limits for:

- Login.
- OTP generation.
- OTP verification.
- Password reset.
- KYC/CRB checks.
- Loan application submission.
- Repayment initiation.
- Provider callbacks.
- Compliance exports.

## Provider Callback APIs

Provider callbacks must:

- Authenticate provider payloads using provider-native signatures, shared secrets, or status verification calls.
- Store callback events before processing.
- Be idempotent.
- Reject stale or replayed payloads.
- Avoid returning internal details.

