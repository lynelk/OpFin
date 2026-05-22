# Frontend/Backend Contract

Date: 2026-05-22

This is the frontend-facing contract currently used by the OpFin investor-demo screens.

## Environment

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
```

If `NEXT_PUBLIC_USE_MOCK_API` is not exactly `false`, the frontend uses local fixtures from `src/lib/mock-data.ts`.

## Auth

The login server action calls `POST /api/login` with:

```json
{
  "phone": "256700000001",
  "password": "password"
}
```

The response must include:

```json
{
  "data": {
    "access_token": "token",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Demo Customer",
      "phone": "256700000001",
      "role": "customer"
    }
  }
}
```

Authenticated calls use `Authorization: Bearer <token>`.

## Response shape

The frontend expects:

```json
{
  "success": true,
  "message": "Message",
  "data": {}
}
```

For errors, it reads `message`, `errors`, and HTTP status.

## Required backend endpoints

| Method | Endpoint | Frontend use |
| --- | --- | --- |
| POST | `/api/login` | Login action. |
| GET | `/api/profile` | Dashboard profile context. |
| GET | `/api/demo/dashboard` | Dashboard, KYC, consent, latest loan, account, schedule. |
| POST | `/api/demo/consent` | Grant credit-processing consent. |
| DELETE | `/api/demo/consent` | Revoke credit-processing consent. |
| GET | `/api/products` | Loan application options. |
| GET | `/api/institutions` | Loan application options. |
| GET | `/api/product-terms/{product}` | Loan term options. |
| POST | `/api/demo/loan-applications` | Submit investor-demo credit application. |
| GET | `/api/demo/loan-applications/{application}/decision` | Decision screen. |
| GET | `/api/demo/loan-applications/{application}/offer` | Offer screen. |
| POST | `/api/demo/loan-offers/{offer}/accept` | Accept offer and create loan/schedule/disbursement records. |
| GET | `/api/demo/admin/investor-snapshot` | Admin credit review and audit trail. |

## Known gaps

- No frontend contract for referred/manual review decisions yet.
- No frontend contract for intentionally failed mock payment simulation yet.
- Some legacy frontend API helpers remain for older endpoints, but the investor-demo path uses `/api/demo/*`.
