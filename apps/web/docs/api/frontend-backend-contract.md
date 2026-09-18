# Web frontend/backend contract

Updated: 18 September 2026

This document records the current production-facing contract used by the Next.js web application. The canonical API reference remains `apps/api/docs/api/current-endpoints.md`.

## Environment

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

Production must keep mock/demo flags disabled.

## Authentication

Current customer authentication uses phone plus six-digit PIN. New registration is phone → OTP → names → PIN.

Login payload:

```json
{
  "phone": "2567XXXXXXXX",
  "pin": "482951"
}
```

Legacy `password` remains a backend migration-compatible input for old accounts, not the current web/mobile UX.

Successful authentication returns the standard envelope with `data.access_token` and `data.user`. Server-rendered web calls keep bearer/session material in the established secure server boundary.

## Standard response

```json
{
  "success": true,
  "message": "Human-readable result",
  "data": {}
}
```

Errors use HTTP status plus safe `message` / `errors`.

## Borrower state

Web should use `GET /api/credit/profile` rather than reconstructing score/limit/due state from several legacy endpoints.

The profile provides:

- composite score/band;
- available-to-borrow;
- amount due;
- total outstanding;
- next due date;
- setup state;
- next action.

## Loan application and offer

- `GET /api/credit/options` — eligible terms.
- `POST /api/credit/applications` — limit-aware application.
- `GET /api/credit/offers/{offer}` — immutable pricing/disclosure snapshot.
- `POST /api/credit/offers/{offer}/accept` — exact disclosure acceptance plus explicit credit-information reporting consent.

Clients must not recompute interest/fees. Display backend disclosure values.

## KYC and consent

- `GET /api/kyc/status`
- `POST /api/kyc/cases` multipart: NIN + ID front/back + photo holding ID.
- `GET/POST /api/consents`
- `DELETE /api/consents/{consent}`

Do not expose private KYC paths/provider payloads.

## Wallets, repayments and receipts

- `GET/POST /api/wallets`
- `PATCH /api/wallets/{wallet}/default`
- `POST /api/loans/{loan}/repay`
- `GET /api/receipts`
- `GET /api/receipts/{receipt}`

Provider pending state is not completed payment/disbursement.

## Support and complaints

- `GET/POST /api/support-cases`

Complaint cases include the regulatory due date, first-response/SLA evidence and complaint procedure snapshot.

## Admin/regulatory

Admin web consumes governance/compliance routes for:

- reconciliation and ledger evidence;
- credit review;
- support/complaints;
- credit-information exchange;
- NPL/default-interest controls;
- receipts;
- guarantor/term-change registers;
- regulator evidence packs/books and records.

Use `python3 scripts/search-api.py "umra"` to discover the registered routes.

## Contract rule

When an API response/request used by web changes, update this file, the typed API client and tests in the same PR.
