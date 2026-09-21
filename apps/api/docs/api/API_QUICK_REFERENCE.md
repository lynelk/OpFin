# OpFin API quick reference

Updated: 21 September 2026

This is a task-oriented index. Exact registered routes remain authoritative in Laravel.

## Search the API

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
| Governance overview | `GET /api/admin/governance/overview` |
| Regulatory reports | `GET /api/admin/governance/regulatory-reports` |
| Report detail | `GET /api/admin/governance/regulatory-reports/{report}` |
| Generate report | `POST /api/admin/governance/regulatory-reports/generate` |
| Approve report | governed maker-checker endpoint in governance routes |

Current UMRA report profiles include digital credit supervision, credit-information exchange, books/records, NPL/default-interest, receipts, term/guarantor controls and consumer complaints.

## Provider callbacks

| Task | Method / endpoint |
| --- | --- |
| CPay callback | `POST /api/webhooks/cpay` |
| WhatsApp verification | `GET /api/webhooks/whatsapp` |
| WhatsApp webhook | `POST /api/webhooks/whatsapp` |
| USSD callback | `POST /api/ussd` |

Read `current-endpoints.md` for request/response notes and `frontend-backend-contract.md` for client rules.


## Financial Spaces

| Task | Method / endpoint |
| --- | --- |
| My authorised Spaces | `GET /api/financial-spaces` |
| Create Space | `POST /api/financial-spaces` |
| Accept invitation | `POST /api/financial-spaces/invitations/accept` |
| Members | `GET /api/financial-spaces/{space}/members` |
| Invite member | `POST /api/financial-spaces/{space}/invitations` |
| Financial position | `GET /api/financial-spaces/{space}/financial-life` |
| Assets | `GET/POST /api/financial-spaces/{space}/assets` |
| Debt / receivables | `GET/POST /api/financial-spaces/{space}/obligations` |
| Institutional workspace | `GET /api/financial-spaces/{space}/workspace` |
| Organisation onboarding | `PUT /api/financial-spaces/{space}/organisation-onboarding` |
| Enable Employer capability | `POST /api/financial-spaces/{space}/employer/enable` |

## Marketplace and commercial platform

| Task | Method / endpoint |
| --- | --- |
| Partner products | `GET /api/marketplace/products` |
| OpFin plans | `GET /api/plans` |
| Subscribe a Space | `POST /api/financial-spaces/{space}/subscription` |
| Record revenue event | `POST /api/admin/revenue-events` |
| Reconcile revenue / CPay | `POST /api/admin/revenue-events/{event}/reconcile` |

**Design contract:** one person may belong to many Spaces; Employer is a Business capability; permission, entitlement and eligibility are separate; Personal Space data is not exposed to employers/groups merely because a relationship exists.

## Inclusive finance and programme delivery

| Task | Method / endpoint |
| --- | --- |
| Inclusion/profile consent | GET/PATCH /api/inclusive-finance/profile |
| Capability guidance | GET /api/inclusive-finance/capability |
| Capability evidence | POST /api/inclusive-finance/capability/events |
| Financial reputation pathway | GET /api/inclusive-finance/reputation |
| Alternative-data signals | GET/POST /api/inclusive-finance/signals |
| Open programmes | GET /api/inclusive-finance/programmes |
| Programme enrolment | POST /api/inclusive-finance/programmes/{programme}/enrol |
| Alternative collateral/support | GET/POST /api/inclusive-finance/support-instruments |
| Fair-treatment explanation | GET /api/inclusive-finance/fair-treatment |
| Admin programme register | GET /api/admin/inclusive-finance/programmes |\n| Admin impact summary | GET /api/admin/inclusive-finance/impact |
| Admin programme configuration | POST/PATCH /api/admin/inclusive-finance/programmes[/{programme}] |
| Admin provider-signal governance | POST /api/admin/inclusive-finance/signals; PATCH /api/admin/inclusive-finance/signals/{signal}/verify |
| Admin support verification | PATCH /api/admin/inclusive-finance/support-instruments/{instrument}/verify |
| Admin fair-treatment assessment | POST /api/admin/inclusive-finance/fair-treatment/{application}/assess |

Voluntary inclusion fields are for service adaptation/programme measurement and do not feed credit risk. Programme participation, provider-signal verification and alternative collateral evidence do not automatically change a credit decision. See `docs/product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md`.
