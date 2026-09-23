# OpFin API quick reference

Status: Controlled external developer reference  
Updated: 23 September 2026  
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

## Essentials

Essentials provides purpose-bound finance through approved third-party lenders.

Customer flow: `GET /api/essentials/catalogue` → `POST /api/essentials/accounts` → account verification → `POST /api/essentials/eligibility` → `POST /api/essentials/quotes` → disclosed acceptance at `POST /api/essentials/quotes/{quote}/accept` → repayment at `POST /api/essentials/advances/{advance}/repay`.

Customer platform permissions use `GET/POST/DELETE /api/essentials/partner-authorisations...`. Embedded providers use `/api/partner/essentials/...`; operations use `/api/admin/essentials/...`.

Invariants: OpFin is not the primary lender; lender limits do not stack above the customer's overall responsible-credit headroom; financed funds go to the verified provider/beneficiary rather than the customer; all gnuGrid services route through Cito; configured settlement/repayment routes use CPay.
