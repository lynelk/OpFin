# Web API contract dependencies

Updated: 18 September 2026

The central API client is `apps/web/src/lib/api/client.ts`. Specialised helpers exist for governance and other domains, but none may become an independent source of financial truth.

## Core dependencies

| Web task | API source |
| --- | --- |
| Login/session | `POST /api/login`, `GET /api/profile` |
| Borrower dashboard | `GET /api/credit/profile` |
| KYC | `GET /api/kyc/status`, `POST /api/kyc/cases` |
| Consent | `/api/consents` |
| Loan application | `GET /api/credit/options`, `POST /api/credit/applications` |
| Offer | `GET/POST /api/credit/offers/*` |
| Repayment | `POST /api/loans/{loan}/repay` |
| Wallets | `/api/wallets` |
| Receipts | `/api/receipts` |
| Support/complaints | `/api/support-cases` |
| Admin compliance | governance + `/api/admin/umra/*` routes |

## Data rules

- Use `data.user.national_id_masked`; raw NIN is not a normal profile field.
- Use the server credit profile for score, limit, due and outstanding.
- Use exact offer disclosures; do not calculate a client-side “equivalent” offer.
- Pass explicit credit-reporting consent on offer acceptance.
- Treat 202/pending money movement as pending.
- Keep idempotency keys stable for intentional retry.
- Never log or persist OTPs, PINs, raw KYC images or provider secrets.

## Production mocks

Mocks/fixtures may support explicitly labelled tests/demos only. Production requires:

```text
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

## Discovering dependencies

```bash
python3 scripts/search-api.py "credit"
python3 scripts/search-api.py "support"
python3 scripts/search-docs.py "offer acceptance" --api
```

The canonical human-readable route list is `apps/api/docs/api/current-endpoints.md`.
