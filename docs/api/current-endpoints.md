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

## Production Readiness Endpoints

These endpoints are production-shaped persistence and workflow foundations. They do not make live provider calls unless a configured provider implementation is explicitly added.

### `GET /api/kyc/status`

Returns the authenticated customer's latest KYC case.

### `POST /api/kyc/cases`

Submits a KYC case with national ID, provider/reference metadata, and evidence payload.

### `GET /api/consents`

Returns the authenticated customer's consent records.

### `POST /api/consents`

Grants a versioned consent purpose and revokes any currently granted consent for the same purpose.

### `DELETE /api/consents/{consent}`

Revokes a customer-owned consent record.

### `PATCH /api/admin/kyc/cases/{case}`

Reviews a KYC case as verified or rejected. Requires platform admin, operations, or support role.

### `POST /api/admin/crb-reports`

Records a CRB provider report result and provider reference. Requires platform admin, operations, or support role.

### `POST /api/admin/loan-applications/{application}/decision`

Records a production credit decision using KYC, consent, and CRB gates. Applications without current verified KYC, active consent, or current CRB report are referred rather than approved.

### `POST /api/admin/reconciliation-runs`

Creates a reconciliation run from unreconciled mobile money transactions for a provider/business date.

### `GET /api/admin/reconciliation-runs`

Returns recent reconciliation runs for operations review.

### `POST /api/admin/support-cases`

Creates a support case linked to a customer.

### `GET /api/admin/support-cases`

Returns recent support cases for operations/support review.

### `POST /api/admin/compliance-reports`

Creates a compliance report record with period counts for KYC cases, consents, credit decisions, and mobile money transactions.

### `GET /api/admin/compliance-reports`

Returns recent compliance report records.

### Production ledger service

The production ledger service is not exposed directly as a public API. `App\Services\ProductionLedgerService` posts immutable `ledger_transactions` and `ledger_entries` in integer minor units only and rejects unbalanced postings.

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

## Investor Demo Endpoints

These endpoints are protected by Sanctum and are intentionally labelled as investor-demo/mock flows. They do not call live mobile money, CRB, KYC, savings, insurance, or investment providers.

### `GET /api/demo/dashboard`

Returns the authenticated customer's demo profile, KYC status, consent state, mock integration labels, and latest application with decision, offer, loan, and schedule relationships.

### `POST /api/demo/consent`

Creates or re-grants demo-scoped `credit_processing` consent. Audit logged as `demo.consent.granted`.

### `DELETE /api/demo/consent`

Revokes demo-scoped credit-processing consent. Future demo credit applications are blocked until consent is granted again. Audit logged as `demo.consent.revoked`.

### `POST /api/demo/loan-applications`

Creates a demo loan application, requires verified KYC and granted demo consent, runs mock affordability/decisioning, returns reason codes, and creates a pending loan offer when approved.

Submitted fields:

- `loan_product_id`
- `loan_product_term_id`
- `institution_id`
- `amount`
- `reason`

### `GET /api/demo/loan-applications/{application}/decision`

Returns the reason-coded mock decision for an application visible to the authenticated customer or admin/support role.

### `GET /api/demo/loan-applications/{application}/offer`

Returns the mock loan offer for an approved demo application visible to the authenticated customer or admin/support role.

### `POST /api/demo/loan-offers/{offer}/accept`

Accepts a pending demo offer, creates the loan account, generates repayment schedules via the existing loan model event, creates ledger entries through `LoanService`, and records a sandbox mobile money disbursement through the mock adapter. The offer row is locked during acceptance to block duplicate acceptance.

Audit events include:

- `demo.loan_offer.accepted`
- `demo.loan_account.created`
- `demo.repayment_schedule.generated`
- `demo.ledger_entries.created`
- `demo.disbursement.recorded`

### `GET /api/demo/admin/investor-snapshot`

Requires `platform_admin`, `operations`, or `support`. Returns customers, applications, decisions, offers, loans, ledger entries, repayment schedules, mobile money transactions, and audit trail entries for the investor demo review.

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
