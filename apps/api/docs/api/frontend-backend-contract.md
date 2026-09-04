# Frontend/Backend Contract

Date: 2026-05-22

This document records the API contract currently consumed by OpFin-FE for the investor-demo vertical slice.

## Base URL and authentication

Frontend environment:

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
```

Authentication:

- Login returns `access_token`, `token_type`, and `user`.
- Authenticated requests send `Authorization: Bearer <token>`.
- Frontend stores the token in the `opfin_access_token` HTTP-only cookie through server actions.

## Response envelope

Successful demo responses:

```json
{
  "success": true,
  "message": "Investor demo dashboard loaded.",
  "data": {}
}
```

Demo validation or access errors:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "amount": ["The amount field is required."]
  }
}
```

## Required endpoints

### Login

`POST /api/login`

Request:

```json
{
  "phone": "256700000001",
  "password": "password"
}
```

Response data:

```json
{
  "access_token": "token",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Demo Customer",
    "phone": "256700000001",
    "role": "customer",
    "national_id": "CM000000001",
    "date_of_birth": "1990-01-01",
    "nin_status": "verified"
  }
}
```

### Profile

`GET /api/profile`

Response data:

```json
{
  "user": {
    "id": 1,
    "name": "Demo Customer",
    "phone": "256700000001",
    "role": "customer",
    "national_id": "CM000000001",
    "date_of_birth": "1990-01-01",
    "nin_status": "verified"
  },
  "permissions": []
}
```

### Demo dashboard

`GET /api/demo/dashboard`

Response data:

```json
{
  "mock_integrations": ["affordability", "decisioning", "mobile_money_disbursement"],
  "profile": {},
  "kyc": {
    "status": "verified",
    "national_id": "CM000000001",
    "date_of_birth": "1990-01-01",
    "mock_integration": false
  },
  "consent": null,
  "latest_application": null
}
```

### Consent

`POST /api/demo/consent`

Response data:

```json
{
  "mock_integration": true,
  "status": "granted",
  "consent": {
    "purpose": "credit_processing",
    "status": "granted"
  }
}
```

`DELETE /api/demo/consent`

Response data uses the same shape with `status: revoked`.

### Loan application

`POST /api/demo/loan-applications`

Request:

```json
{
  "loan_product_id": 1,
  "loan_product_term_id": 1,
  "institution_id": 1,
  "amount": 250000,
  "reason": "School fees"
}
```

Response data:

```json
{
  "application": {
    "id": 1,
    "amount": 250000,
    "status": "approved"
  },
  "decision": {
    "id": 1,
    "status": "approved",
    "requested_amount_minor": 250000,
    "approved_amount_minor": 250000,
    "monthly_income_minor": 1200000,
    "estimated_monthly_obligation_minor": 92500,
    "reason_codes": ["KYC_VERIFIED", "CONSENT_GRANTED"],
    "decision_summary": "Approved by mock affordability rules for investor demo only."
  },
  "offer": {
    "id": 1,
    "status": "pending_acceptance",
    "principal_amount_minor": 250000,
    "total_repayment_minor": 277500,
    "duration_days": 30,
    "interest_rate": "11.00",
    "interest_type": "flat",
    "repayment_frequency": "monthly"
  }
}
```

### Decision and offer lookup

`GET /api/demo/loan-applications/{application}/decision`

Response data:

```json
{
  "mock_integration": true,
  "decision": {}
}
```

`GET /api/demo/loan-applications/{application}/offer`

Response data:

```json
{
  "mock_integration": true,
  "offer": {}
}
```

### Offer acceptance

`POST /api/demo/loan-offers/{offer}/accept`

Response data:

```json
{
  "offer": {
    "id": 1,
    "status": "accepted"
  },
  "loan": {
    "id": 1,
    "status": "Disbursed",
    "schedules": []
  },
  "mobile_money": {
    "id": 1,
    "provider": "mock",
    "status": "completed",
    "direction": "disbursement",
    "amount_minor": 250000,
    "reconciliation_status": "matched"
  },
  "ledger_entries": [],
  "repayment_schedule": []
}
```

### Admin investor snapshot

`GET /api/demo/admin/investor-snapshot`

Allowed roles: `platform_admin`, `operations`, `support`.

Response data:

```json
{
  "customers": [],
  "applications": [],
  "decisions": [],
  "offers": [],
  "loans": [],
  "ledger_entries": [],
  "repayment_schedules": [],
  "mobile_money": [],
  "audit_trail": []
}
```

## Supporting reference-data endpoints

These endpoints are used by the loan application form:

- `GET /api/products`
- `GET /api/institutions`
- `GET /api/product-terms/{product}`

## Known contract gaps

- Legacy endpoints may not use the standard envelope consistently.
- No dedicated API endpoint currently exposes failed mock payment simulation for the frontend.
- Referred/manual review decision state is not part of the current demo decisioning contract.
- Production KYC, CRB, mobile money, insurance, savings, investments, and compliance reporting contracts are not yet live.
