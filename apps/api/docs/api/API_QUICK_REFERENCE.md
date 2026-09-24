# OpFin API quick reference

Status: Controlled external developer reference  
Updated: 24 September 2026  
Language: English (United Kingdom)

This is a task-oriented entry point. For the complete registered surface, use `api/current-endpoints.md` plus `php artisan route:list --json`.

## Discovery

```bash
python3 scripts/search-api.py "credit"
python3 scripts/search-api.py "programme"
python3 scripts/search-api.py "financial-spaces"
python3 scripts/search-api.py "umra"
```

## Identity and account

| Task | Method / endpoint |
| --- | --- |
| Send OTP | `POST /api/generate-otp` |
| Verify OTP | `POST /api/verify-otp` |
| Register | `POST /api/register` |
| Login | `POST /api/login` |
| Reset PIN | `POST /api/reset-password` |
| Profile | `GET /api/profile` |
| Delete account | `DELETE /api/account` |
| KYC status | `GET /api/kyc/status` |
| Submit KYC | `POST /api/kyc/cases` |
| Consents | `GET/POST /api/consents` |

Cito is the preferred NIN/phone-ownership route where configured. Biometric/document evidence uses the configured evidence-capable provider until an equivalent certified contract exists. Ambiguous primary-provider failure remains pending/error until reconciled; direct fallback is explicit.

## Financial Spaces

| Task | Method / endpoint |
| --- | --- |
| List authorised Spaces | `GET /api/financial-spaces` |
| Create Space | `POST /api/financial-spaces` |
| Accept invitation | `POST /api/financial-spaces/invitations/accept` |
| Members | `GET /api/financial-spaces/{space}/members` |
| Invite member | `POST /api/financial-spaces/{space}/invitations` |
| Financial position | `GET /api/financial-spaces/{space}/financial-life` |
| Assets | `GET/POST /api/financial-spaces/{space}/assets` |
| Obligations/receivables | `GET/POST /api/financial-spaces/{space}/obligations` |
| Institutional workspace | `GET /api/financial-spaces/{space}/workspace` |
| Organisation onboarding | `PUT /api/financial-spaces/{space}/organisation-onboarding` |
| Enable employer capability | `POST /api/financial-spaces/{space}/employer/enable` |

Membership, role, entitlement and financial-product eligibility are separate gates.

## Investment Club / group treasury

| Task | Method / endpoint |
| --- | --- |
| Treasury accounts | `GET/POST /api/financial-spaces/{space}/treasury/accounts` |
| Cashbook transactions | `GET/POST /api/financial-spaces/{space}/treasury/accounts/{account}/transactions` |
| Statement imports | `GET/POST /api/financial-spaces/{space}/treasury/accounts/{account}/statement-imports` |
| Inspect imported statement | `GET /api/financial-spaces/{space}/statement-imports/{import}` |
| Smart reconciliation | `POST /api/financial-spaces/{space}/statement-imports/{import}/reconcile` |
| Resolve statement-row to-do | `POST /api/financial-spaces/{space}/statement-rows/{row}/resolve` |
| Accept book-only item | `POST /api/financial-spaces/{space}/statement-imports/{import}/book-transactions/{transaction}/accept` |
| Accept balance variance | `POST /api/financial-spaces/{space}/statement-imports/{import}/balance-variance` |
| Confirm reconciliation | `POST /api/financial-spaces/{space}/statement-imports/{import}/confirm` |
| Issued statements | `GET /api/financial-spaces/{space}/statements` |
| Issue account statement | `POST /api/financial-spaces/{space}/treasury/accounts/{account}/statements` |
| Issue consolidated all-activity statement | `POST /api/financial-spaces/{space}/statements/consolidated` |
| Statement detail | `GET /api/financial-spaces/{space}/statements/{statement}` |
| Print-ready HTML | `GET /api/financial-spaces/{space}/statements/{statement}/html` |
| Statement CSV | `GET /api/financial-spaces/{space}/statements/{statement}/csv` |

Statement imports are external evidence; the OpFin treasury cashbook is internal book truth. High-confidence items auto-match; uncertain items become a short user to-do list and reconciliation is not confirmed until those decisions are cleared. Issued OpFin statements are immutable snapshots and are clearly identified as OpFin Financial Space statements rather than bank-issued documents. Consolidated statements keep totals separated by currency unless an explicit FX policy exists.

## Location Context

| Task | Method / endpoint |
| --- | --- |
| Capability state | `GET /api/location/status` |
| List subject locations | `GET /api/location-contexts?subject_type=...&subject_id=...` |
| Save purpose-bound location | `POST /api/location-contexts` |
| Remove optional location | `DELETE /api/location-contexts/{context}` |
| Search Google places | `POST /api/location/places/autocomplete` |
| Authenticated static map | `GET /api/location/static-map/{context}` |
| Explicit route calculation | `POST /api/location/route` |
| Nearby partner services | `GET /api/location/nearby-services` |
| Operations aggregate geography | `GET /api/admin/location-insights` |
| Partner-owned service network | `GET /api/partner/location-network` |

Location is foreground/manual and purpose-bound. Personal service discovery is approximate; background tracking is not configured. All Location Context records are non-credit by default. Google credentials remain server-side and Google-dependent functions fail closed when the adapter is disabled.

## Responsible credit

| Task | Method / endpoint |
| --- | --- |
| Credit profile | `GET /api/credit/profile` |
| Refresh profile | `POST /api/credit/profile/refresh` |
| Eligible options | `GET /api/credit/options` |
| Applications | `GET/POST /api/credit/applications` |
| Offer detail | `GET /api/credit/offers/{offer}` |
| Accept offer | `POST /api/credit/offers/{offer}/accept` |
| Repay | `POST /api/loans/{loan}/repay` |
| Receipts | `GET /api/receipts` |
| Receipt detail | `GET /api/receipts/{receipt}` |

A displayed limit is not guaranteed approval. Offer acceptance is bound to disclosures and required consent. Pending payout/collection is not financial finality.

## Inclusive finance and programmes

Key customer routes include:

- `GET/PATCH /api/inclusive-finance/profile`
- `GET /api/inclusive-finance/capability`
- `GET /api/inclusive-finance/reputation`
- `GET /api/inclusive-finance/programmes`
- `POST /api/inclusive-finance/programmes/{programme}/enrol`
- `DELETE /api/inclusive-finance/programmes/{programme}/enrol`
- `GET /api/inclusive-finance/programme-check-ins`
- `POST /api/inclusive-finance/programme-check-ins/{instrument}/responses`
- `GET/POST /api/inclusive-finance/support-instruments`

Programme measurement, financial-health outcomes and protected attributes remain non-credit by default.

## Programme and provider operations

Programme configuration, indicators, instruments/questions, translations, follow-up operations, partner invitations/access, privacy-suppressed exports, commercial attribution/costs and governed provider-adapter ingestion are role-gated operator surfaces.

Use:

```bash
python3 scripts/search-api.py "inclusive-finance"
python3 scripts/search-api.py "commercial"
python3 scripts/search-api.py "provider-adapters"
```

This avoids maintaining a second exhaustive route catalogue here.

## Governance and regulatory evidence

| Task | Method / endpoint |
| --- | --- |
| Governance dashboard | `GET /api/admin/governance/dashboard` |
| Regulatory report register | `GET /api/admin/governance/regulatory-reports` |
| Report detail | `GET /api/admin/governance/regulatory-reports/{report}` |
| Generate report | `POST /api/admin/governance/regulatory-reports` |
| Approve report | `POST /api/admin/governance/regulatory-reports/{report}/approve` |
| Run integrity checks | `POST /api/admin/governance/integrity-runs` |

UMRA-specific controls remain under `/api/admin/umra/...` and are documented in `current-endpoints.md` and `docs/UMRA_DIGITAL_LENDING_CONTROLS.md`.

## Provider callbacks

| Task | Method / endpoint |
| --- | --- |
| CPay callback | `POST /api/webhooks/cpay` |
| WhatsApp verification | `GET /api/webhooks/whatsapp` |
| WhatsApp callback | `POST /api/webhooks/whatsapp` |
| USSD callback | `POST /api/ussd` |

## Client rule

Use `api/frontend-backend-contract.md` for client behaviour, error/finality handling and presentation rules. Do not infer client behaviour from route names alone.
