# OpFin API quick reference

Updated: 18 September 2026

This is a task-oriented index. Start with [the integrator guide](INTEGRATOR_GUIDE.md) for authentication, request handling, errors and safe retries. Laravel's registered routes establish the exact addresses; controller checks, permissions and capability gates still apply. A registered operation is not proof of production availability.

## Search the API

From repository root:

```bash
python3 scripts/search-api.py "credit"
python3 scripts/search-api.py "umra"
python3 scripts/search-api.py "receipt"
```

Or:

```bash
cd apps/api
php artisan route:list --path=api/credit
php artisan route:list --path=api/admin/umra
php artisan route:list --json
```

The [documentation workflow](../../../../docs/DOCUMENTATION_MAINTENANCE.md) compares the endpoint tables with Laravel registration and generates a full discovery index. Its coverage report identifies operations needing more narrative documentation; it does not invent request/response schemas.

## Service and capability checks

| Task | Method / endpoint |
| --- | --- |
| Check that the process responds | `GET /api/health/live` |
| Inspect dependency and operational readiness | `GET /api/health/ready` |
| Read the authenticated capability response | `GET /api/capabilities` |

Inspect the readiness body, not only HTTP status. Warming workers or blocked integrations must not be presented as ready services.

## Account and identity

| Task | Method / endpoint |
| --- | --- |
| Send OTP | `POST /api/generate-otp` |
| Verify OTP | `POST /api/verify-otp` |
| Register | `POST /api/register` |
| Login | `POST /api/login` |
| Reset PIN | `POST /api/reset-password` |
| Profile | `GET /api/profile` |
| KYC status | `GET /api/kyc/status` |
| Submit KYC | `POST /api/kyc/cases` |
| Consents | `GET/POST /api/consents` |

## Credit profile and borrowing

| Task | Method / endpoint |
| --- | --- |
| Credit profile | `GET /api/credit/profile` |
| Refresh profile | `POST /api/credit/profile/refresh` |
| Eligible options | `GET /api/credit/options` |
| Submit application | `POST /api/credit/applications` |
| List applications | `GET /api/credit/applications` |
| Offer detail | `GET /api/credit/offers/{offer}` |
| Accept offer | `POST /api/credit/offers/{offer}/accept` |
| Repay | `POST /api/loans/{loan}/repay` |

Offer acceptance requires exact disclosure acceptance and explicit credit-information reporting consent.

## Phones, wallets and receipts

| Task | Method / endpoint |
| --- | --- |
| Phone numbers | `GET /api/phone-numbers` |
| Add second phone | `POST /api/phone-numbers/secondary` |
| Wallets | `GET/POST /api/wallets` |
| Default wallet | `PATCH /api/wallets/{wallet}/default` |
| Receipt history | `GET /api/receipts` |
| Receipt detail | `GET /api/receipts/{receipt}` |

## Guarantors

| Task | Method / endpoint |
| --- | --- |
| List application guarantors | `GET /api/credit/applications/{application}/guarantors` |
| Add guarantor | `POST /api/credit/applications/{application}/guarantors` |
| Independent confirm/reject | `POST /api/guarantors/confirm` |

A maximum of two guarantor contacts is enforced.

## Support and accessibility

| Task | Method / endpoint |
| --- | --- |
| Support cases | `GET/POST /api/support-cases` |
| Accessibility preferences | `PATCH /api/accessibility-preferences` |
| Account deletion | `DELETE /api/account` |

Complaints carry the configured regulatory resolution deadline.

## Admin UMRA controls

| Task | Method / endpoint |
| --- | --- |
| Credit reporting register | `GET /api/admin/umra/credit-reporting` |
| Submit eligible credit reports | `POST /api/admin/umra/credit-reporting/submit` |
| Evaluate NPL | `POST /api/admin/umra/loans/{loan}/evaluate-npl` |
| Accrue default interest | `POST /api/admin/umra/loans/{loan}/default-interest` |
| Toggle NPL cap enforcement | `PATCH /api/admin/umra/loans/{loan}/npl-enforcement` |
| Term-change register | `GET /api/admin/umra/term-changes` |
| Request term change | `POST /api/admin/umra/product-terms/{term}/changes` |
| Approve term change | `POST /api/admin/umra/term-changes/{change}/approve` |
| Apply term change | `POST /api/admin/umra/term-changes/{change}/apply` |

## Governance and regulator evidence

| Task | Method / endpoint |
| --- | --- |
| Governance dashboard | `GET /api/admin/governance/dashboard` |
| Regulatory reports | `GET /api/admin/governance/regulatory-reports` |
| Report detail | `GET /api/admin/governance/regulatory-reports/{report}` |
| Generate report | `POST /api/admin/governance/regulatory-reports` |
| Approve report | `POST /api/admin/governance/regulatory-reports/{report}/approve` |

The governance routes register platform-admin, operations and support roles for the read group; the write group registers platform-admin and operations roles. Controller-level governance checks still apply. See [the route definitions](../../routes/governance.php), which are mounted under `/api` by [application bootstrap](../../bootstrap/app.php).

Current UMRA report profiles include digital credit supervision, credit-information exchange, books/records, NPL/default-interest, receipts, term/guarantor controls and consumer complaints. Generating or approving an evidence pack does not establish external regulatory filing.

## Provider callbacks

| Task | Method / endpoint |
| --- | --- |
| CPay callback | `POST /api/webhooks/cpay` |
| WhatsApp verification | `GET /api/webhooks/whatsapp` |
| WhatsApp webhook | `POST /api/webhooks/whatsapp` |
| USSD callback | `POST /api/ussd` |

Read [current endpoints](current-endpoints.md) for request/response notes and [the frontend/backend contract](frontend-backend-contract.md) for client rules. Callback authentication and replay controls must be reviewed in the handler; absence of customer-token middleware does not mean an unsigned request is permitted.
