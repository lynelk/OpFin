# Frontend API Dependencies

## Base URL

Current frontend base URL:

```dart
const String apiUrl = 'https://app.opfin.co/api';
```

The app also has a commented local LAN URL. This should be replaced with formal environment configuration before production or investor demos.

## Observed Endpoints

Authentication and account:

- `POST /login`
- `POST /logout`
- `POST /generate-otp`
- `POST /verify-otp`
- `POST /register`
- `POST /reset-password`

Profile and identity:

- `POST /validate-nin`

Credit:

- `GET /products`
- `GET /product-terms/{productId}`
- `GET /loan-applications/{userId}`
- `GET /loan-balance/{userId}`
- `POST /loan-applications`
- `POST /loans/{loanId}/repay`

## Backend Alignment Needs

The frontend should align with the Laravel backend standard API response envelope:

```json
{
  "success": true,
  "message": "Operation completed.",
  "data": {}
}
```

Before the next development phase, confirm exact backend contracts for:

- Login response fields.
- Profile response fields.
- Role and permission representation.
- Validation error shape.
- OTP expiration and retry behavior.
- Loan product schema.
- Loan term schema.
- Loan application status values.
- Repayment request and response fields.
- NIN validation states.

## API Client Requirements

Create a centralized API client before adding new modules:

- Own `baseUrl`, timeouts, headers, and JSON decoding.
- Attach `Authorization: Bearer <token>` automatically.
- Handle `401` by clearing session and redirecting to login.
- Normalize backend validation errors into user-displayable messages.
- Map network failures to retryable/non-retryable states.
- Avoid logging request/response bodies that contain PII or financial data.
- Support environment switching for local, staging, demo, and production.
- Support idempotency keys for sensitive financial requests when backend support exists.

## Authentication Dependencies

The frontend depends on Sanctum-style bearer tokens from the backend. Required backend support:

- Token creation on login.
- Token revocation on logout.
- Authenticated profile endpoint.
- Role and permission data in profile or a dedicated endpoint.
- Consistent `401` and `403` responses.

Frontend requirements:

- Store access token in secure storage only.
- Use profile endpoint as the source of truth after session restore.
- Do not rely on locally cached role or credit fields for authorization.
- Do not store National ID, date of birth, phone, or other sensitive data in plain preferences.

## Missing Domain APIs

The current frontend does not yet have API dependencies for the full OpFin domain:

- KYC lifecycle beyond basic NIN validation.
- Consent creation, listing, withdrawal, and audit.
- CRB consent and report status.
- Mobile money linking, collection authorization, payment status, and webhooks.
- Savings accounts/goals, deposits, withdrawals, and statements.
- Investment products, holdings, transactions, suitability, and statements.
- Insurance products, policies, premiums, and claims.
- Employer onboarding, employee eligibility, payroll-linked benefits, and approvals.
- Compliance reporting and export workflows.
- Notifications and support tickets.

## Demo API Requirements

Investor demo flows should use sandbox-only APIs or static fixtures:

- Demo products and terms.
- Demo KYC/consent status.
- Demo credit decision.
- Demo repayment status.
- Demo employer benefit eligibility.
- Demo compliance/audit events.

No demo screen should call live mobile money, CRB, employer payroll, investment, or insurance integrations.

