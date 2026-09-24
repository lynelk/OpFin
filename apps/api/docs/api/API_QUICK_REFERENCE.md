# OpFin API quick reference

Status: Task-oriented source reference  
Reviewed: 24 September 2026  
Language: English (United Kingdom)

An endpoint is an address and HTTP method for one API operation. Authentication identifies the caller; authorisation also checks the action and target record. A successful response is not automatically completed money movement.

Use [current endpoints](current-endpoints.md) for existing narrative contracts, [Current capability contracts](CURRENT_CAPABILITY_CONTRACTS.md) for treasury/Essentials/location details, and [the client contract](frontend-backend-contract.md) for presentation and recovery. Exact registration comes from `php artisan route:list --json` in `apps/api`; none of these documents is a complete OpenAPI specification.

**Current acceptance:** the reviewed API build has six failures and Essentials has unresolved financial-control findings. Read [delivery evidence](../../../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) before treating a route as approved for live financial use.

## Discovery from repository root

```bash
python3 scripts/search-api.py "credit"
python3 scripts/search-api.py "financial-spaces"
python3 scripts/search-api.py "statement"
python3 scripts/search-api.py "essentials"
python3 scripts/search-docs.py "repayment" --api
```

## Identity and account

| Task | Operation |
| --- | --- |
| Send and verify phone code | `POST /api/generate-otp`; `POST /api/verify-otp` |
| Register and sign in | `POST /api/register`; `POST /api/login` |
| Reset PIN | `POST /api/reset-password` |
| Profile and deletion | `GET /api/profile`; `DELETE /api/account` |
| Identity status and submission | `GET /api/kyc/status`; `POST /api/kyc/cases` |
| Read/grant consent | `GET /api/consents`; `POST /api/consents` |

The App journey uses names and a six-digit PIN after phone verification. Web retains migrated-password compatibility. Provider evidence remains attributable and missing biometric checks must not become completed KYC. gnuGrid access follows the current Cito-only integration rule; generic fallback is not permission to bypass it.

## Financial Spaces

| Task | Operation |
| --- | --- |
| List/create Spaces | `GET /api/financial-spaces`; `POST /api/financial-spaces` |
| Accept invitation | `POST /api/financial-spaces/invitations/accept` |
| Members and invitations | `GET /api/financial-spaces/{space}/members`; `POST /api/financial-spaces/{space}/invitations` |
| Financial position | `GET /api/financial-spaces/{space}/financial-life` |
| Assets | `GET /api/financial-spaces/{space}/assets`; `POST /api/financial-spaces/{space}/assets` |
| Obligations/receivables | `GET /api/financial-spaces/{space}/obligations`; `POST /api/financial-spaces/{space}/obligations` |
| Workspace | `GET /api/financial-spaces/{space}/workspace` |
| Organisation onboarding | `PUT /api/financial-spaces/{space}/organisation-onboarding` |
| Employer capability | `POST /api/financial-spaces/{space}/employer/enable` |

Membership, role, entitlement and product eligibility are separate. A group relationship does not reveal a member's Personal Space.

## Club treasury and statements

Use [the complete treasury route map](CURRENT_CAPABILITY_CONTRACTS.md) for account/transaction CRUD scope, imports, row matching/resolution, book-only/variance decisions, confirmation and statement issue/export.

The main entry points are `/api/financial-spaces/{space}/treasury/accounts`, account transactions/statement-imports, `/api/financial-spaces/{space}/statement-imports/{import}` and `/api/financial-spaces/{space}/statements`.

Import is not reconciliation; a suggestion is not a user's confirmation; an issued OpFin statement is not bank-issued evidence. Currency-separated reporting does not create an unsupported FX grand total. Historical opening-baseline failures remain acceptance work, and treasury does not implement all member-capital/NAV/distribution requirements.

## Location Context

| Task | Operation |
| --- | --- |
| Capability state | `GET /api/location/status` |
| Read/save/remove context | `GET /api/location-contexts`; `POST /api/location-contexts`; `DELETE /api/location-contexts/{context}` |
| Places search | `POST /api/location/places/autocomplete` |
| Authenticated map | `GET /api/location/static-map/{context}` |
| Explicit route | `POST /api/location/route` |
| Nearby services | `GET /api/location/nearby-services` |
| Aggregate operations view | `GET /api/admin/location-insights` |
| Partner service network | `GET /api/partner/location-network` |

Location remains optional and purpose-bound, with manual/foreground use and no implied background tracking. Provider credentials remain server-side. Acceptance includes authorisation before provider resolution, non-credit use and small-cohort privacy.

## Responsible credit

| Task | Operation |
| --- | --- |
| Read/refresh profile | `GET /api/credit/profile`; `POST /api/credit/profile/refresh` |
| Eligible terms | `GET /api/credit/options` |
| Read/submit applications | `GET /api/credit/applications`; `POST /api/credit/applications` |
| Offer and acceptance | `GET /api/credit/offers/{offer}`; `POST /api/credit/offers/{offer}/accept` |
| Ordinary loan repayment | `POST /api/loans/{loan}/repay` |
| Receipts | `GET /api/receipts`; `GET /api/receipts/{receipt}` |

Limits do not guarantee approval. Offers require exact disclosures and applicable separate reporting consent. Ordinary-loan repayment initiation and Essentials repayment have different response/key contracts; neither a 202 nor a 201 proves financial finality.

## Inclusive finance and programmes

Customer routes include profile GET/PATCH, capability/reputation, programme listing, programme enrolment POST/DELETE, due check-ins, instrument responses and support instruments under `/api/inclusive-finance`.

Programme configuration, indicators, instruments/questions, translations, follow-ups, partner invitations/grants, suppressed exports, commercial attribution/costs and provider-adapter ingestion are role-gated operations. Use:

```bash
python3 scripts/search-api.py "inclusive-finance"
python3 scripts/search-api.py "commercial"
python3 scripts/search-api.py "provider-adapters"
```

Programme measurement and financial health are not credit scores. `programme_partner` aggregate reporting is not the Essentials `partner_api` role. Consent, enrolment, purpose and exact programme/Space scope remain necessary.

## Essentials

The customer lifecycle is catalogue → service account → verification → eligibility → quote/disclosures → explicit acceptance → provider fulfilment → repayment/reconciliation. [Current capability contracts](CURRENT_CAPABILITY_CONTRACTS.md) gives exact customer, partner and operations routes and controller fields.

Customer permissions use GET/POST `/api/essentials/partner-authorisations` and DELETE `/api/essentials/partner-authorisations/{authorisation}`. Partner operations are under `/api/partner/essentials`; operator queues/configuration/reconciliation are under `/api/admin/essentials`.

The customer repayment controller requires a body `idempotency_key`; `wallet_id` is nullable in current input validation and must not be treated as a blanket safe-omission guarantee. A 201 contains the created repayment record, not proof of collected money.

Essentials requires a named third-party lender, non-stacking headroom, verified purpose-bound settlement and customer authority. Internal accounting, exact-Space grants, concurrent collection, deletion, capital-mandate and pending-exposure findings remain open. Provider configuration alone does not close them.

## Governance and callbacks

| Task | Operation |
| --- | --- |
| Governance dashboard | `GET /api/admin/governance/dashboard` |
| Report register/generation | `GET /api/admin/governance/regulatory-reports`; `POST /api/admin/governance/regulatory-reports` |
| Report detail/approval | `GET /api/admin/governance/regulatory-reports/{report}`; `POST /api/admin/governance/regulatory-reports/{report}/approve` |
| Integrity run | `POST /api/admin/governance/integrity-runs` |
| CPay callback | `POST /api/webhooks/cpay` |
| WhatsApp challenge/message | `GET /api/webhooks/whatsapp`; `POST /api/webhooks/whatsapp` |
| USSD callback | `POST /api/ussd` |

See [UMRA controls](../../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md) and current endpoints for specialised compliance operations. Callback authentication/replay rules differ from customer-token flows; absence of a customer token requirement does not make unsigned traffic acceptable.

## Contract maintenance

Keep purpose, required/optional fields, validation, ownership, status/error handling and retry/finality evidence in sync. Do not claim every framework/proxy error or HTML/CSV export has the ordinary JSON envelope. Keep secrets and real customer data out of examples. Route registration and publication checks are not complete semantic or production certification.
