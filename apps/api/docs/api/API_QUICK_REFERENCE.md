# OpFin API quick reference

Status: Task-oriented source reference  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

An endpoint is an address and HTTP method for one API operation. Authentication identifies the caller; authorisation also checks the action and target record. A successful response is not automatically completed money movement.

Use [current endpoints](current-endpoints.md) for navigation, [Domain endpoints](domain-endpoints.md) for the preserved detailed contracts, [Current capability contracts](CURRENT_CAPABILITY_CONTRACTS.md) for treasury/Essentials/location details, and [the client contract](frontend-backend-contract.md) for presentation and recovery. Exact registration comes from `php artisan route:list --json` in `apps/api`.

The six API failures recorded on 24 September were subsequently repaired. Read [dated historical evidence](../../../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) together with [later lender-release evidence](../../../../docs/operations/LENDING_DELIVERY_2026-09-25.md). Neither those repairs nor API discovery closes all remaining financial-control findings.

## Developer and AI discovery

Open `/developers` on a deployment containing the Developer Centre. Start with its novice guide, then contracts/errors, financial reliability, AI integration, sandbox testing and maintenance.

| Task | Operation |
| --- | --- |
| Discover links and source version | `GET /api/developer/manifest` |
| Search public documentation | `GET /api/developer/public` |
| Search/read learning guides | `GET /api/developer/guides`; `GET /api/developer/guides/{guide}` |
| Search authenticated role-visible operations | `GET /api/developer/catalogue` |
| Inspect one visible operation | `GET /api/developer/operations/{operation}` |
| Export reviewed contracts visible to the caller | `GET /api/developer/openapi` |
| Discover read-only AI documentation tools | `GET /api/developer/agent-tools` |

Catalogue filters are `q`, `page`, `limit`, `group` and `method`. Read [Developer interface](DEVELOPER_INTERFACE.md) for fields, response envelopes, authentication and examples. `registration_only` entries are coverage gaps, not executing SDK schemas. The OpenAPI route returns a raw specification rather than the ordinary success envelope. The AI bridge retrieves documentation only.

From `apps/api`, `php artisan api:catalogue` exports the complete reviewed offline inventory, including role-exclusive routes. `--check --require-complete` fails while any registered operation lacks a reviewed schema. `--baseline=<approved snapshot>` identifies removals, operation-ID changes and other tracked drift. The offline all-role export does not widen HTTP access.

## Discovery from repository root

```bash
python3 scripts/search-api.py "credit"
python3 scripts/search-api.py "financial-spaces"
python3 scripts/search-api.py "statement"
python3 scripts/search-api.py "essentials"
python3 scripts/search-docs.py "repayment" --api
make api-docs
make api-docs-check
make agent-docs-test
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

Use [the treasury route map](CURRENT_CAPABILITY_CONTRACTS.md) for accounts, transactions, imports, matching/resolution, book-only/variance decisions, confirmation and statement issue/export.

The main entry points are `/api/financial-spaces/{space}/treasury/accounts`, account transactions/statement-imports, `/api/financial-spaces/{space}/statement-imports/{import}` and `/api/financial-spaces/{space}/statements`.

Import is not reconciliation; a suggestion is not confirmation; an OpFin statement is not bank-issued evidence. Currency-separated reporting does not create an unsupported FX grand total. The earlier opening-baseline repair is separate from full member-capital, NAV and distribution acceptance.

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

Location remains optional and purpose-bound, with manual/foreground use and no implied background tracking. Credentials remain server-side. Acceptance includes authorisation before provider resolution, non-credit use and small-cohort privacy.

## Responsible credit

| Task | Operation |
| --- | --- |
| Read/refresh profile | `GET /api/credit/profile`; `POST /api/credit/profile/refresh` |
| Eligible terms | `GET /api/credit/options` |
| Read/submit applications | `GET /api/credit/applications`; `POST /api/credit/applications` |
| Offer and acceptance | `GET /api/credit/offers/{offer}`; `POST /api/credit/offers/{offer}/accept` |
| Ordinary loan repayment | `POST /api/loans/{loan}/repay` |
| Receipts | `GET /api/receipts`; `GET /api/receipts/{receipt}` |

Limits do not guarantee approval. Offers require exact disclosures and applicable separate reporting consent. Ordinary-loan and Essentials repayment have different response/key contracts; neither a 202 nor a 201 proves finality.

## Inclusive finance and programmes

Customer routes include profile GET/PATCH, capability/reputation, programme listing, enrolment POST/DELETE, due check-ins, instrument responses and support instruments under `/api/inclusive-finance`.

Programme configuration, indicators, instruments/questions, translations, follow-ups, invitations/grants, suppressed exports, commercial attribution/costs and provider-adapter ingestion are role-gated. Search `inclusive-finance`, `commercial` or `provider-adapters` through `scripts/search-api.py`.

Programme measurement and financial health are not credit scores. `programme_partner` aggregate reporting is not the Essentials `partner_api` role. Consent, enrolment, purpose and exact programme/Space scope remain necessary.

## Essentials

The lifecycle is catalogue, service account, verification, eligibility, quote/disclosures, explicit acceptance, provider fulfilment and repayment/reconciliation. [Current capability contracts](CURRENT_CAPABILITY_CONTRACTS.md) gives customer, partner and operations routes and fields.

Permissions use GET/POST `/api/essentials/partner-authorisations` and DELETE `/api/essentials/partner-authorisations/{authorisation}`. Partner operations are under `/api/partner/essentials`; operations queues/configuration/reconciliation are under `/api/admin/essentials`.

Repayment requires a body `idempotency_key`; a nullable `wallet_id` in input validation is not a blanket safe-omission guarantee. A 201 contains the created repayment record, not proof of collection.

Essentials requires a named third-party lender, non-stacking headroom, verified purpose-bound settlement and customer authority. Remaining accounting, exact-Space, concurrency, closure and exposure findings are separate from this documentation feature.

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

See [UMRA controls](../../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md) and the domain contracts. Callback authentication/replay differs from customer-token flows; no customer-token requirement does not mean unsigned traffic is acceptable.

## Lender and distribution administration

Use `/api/admin/lending-platform` for lender profiles/products, affiliated deployment, scoped Google/Apple/Huawei policy and delegated operations access. The [endpoint list](current-endpoints.md#lending-platform-configuration-25-september-2026) and [lender contract](../../../../docs/architecture/LENDER_ORCHESTRATION.md) describe permissions and activation boundaries.

## Contract maintenance

Keep fields, validation, ownership, errors and retry/finality evidence in sync. Do not claim all framework/proxy errors or HTML/CSV exports use one envelope. Keep secrets and real customer data out of examples. Route discovery and publication checks are not complete financial or production certification.
