# Current capability contracts: treasury, Essentials and location

Status: Source-based developer and integration reference  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

## Evidence and scope

This supplements [current endpoints](current-endpoints.md) and the [client contract](frontend-backend-contract.md). Exact registration comes from [API routes](../../routes/api.php); controller validation and service policies establish behaviour. Use `/api` once. Registration does not grant authority or activate a provider.

The treasury opening-baseline and role-catalogue corrections at candidate `a56289bcfbf05759d6afd844d89f7e8a39ae34db` passed the existing API build: 265 tests, 1,872 assertions, dependency audit and assets. The [24 September evidence](../../../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) remains historical; its six failed tests are not the current regression position.

The later authority/closure candidate `e336daf4962cb735d1ff0b2360690b1eaffab83e` passed 358 tests and 2,362 assertions on SQLite and PostgreSQL 18. See [authority/closure contracts](ESSENTIALS_AUTHORITY_AND_CLOSURE.md) and [verification evidence](../../../../docs/operations/ESSENTIALS_AUTHORITY_VERIFICATION_2026-09-25.md). That result does not replace independent financial approval, actual deployment or acceptance of unrelated accounting/provider lifecycles.

## 1. Treasury and statements

Treasury records are the Space's cashbook and reconciliation evidence. Import does not move money, create a verified bank balance, or overwrite the financial ledger. An OpFin statement is not bank-issued.

In this table `{base}` is `/api/financial-spaces/{space}`.

| Method | Route after `{base}` | Purpose |
| --- | --- | --- |
| GET, POST | `/treasury/accounts` | List or create accounts |
| GET, POST | `/treasury/accounts/{account}/transactions` | Read or record cashbook movements |
| GET, POST | `/treasury/accounts/{account}/statement-imports` | List imports or upload a mapped statement |
| GET | `/statement-imports/{import}` | Read parsed rows, suggestions and review tasks |
| POST | `/statement-imports/{import}/reconcile` | Match records and refresh review state |
| POST | `/statement-rows/{row}/match` | Match an authorised book transaction |
| POST | `/statement-rows/{row}/resolve` | Apply a permitted, evidenced row decision |
| POST | `/statement-imports/{import}/book-transactions/{transaction}/accept` | Record a reviewed book-only exception |
| POST | `/statement-imports/{import}/balance-variance` | Record a variance decision |
| POST | `/statement-imports/{import}/confirm` | Confirm after review requirements are satisfied |
| GET | `/statements` | List issued statements |
| POST | `/treasury/accounts/{account}/statements` | Issue an account statement |
| POST | `/statements/consolidated` | Issue a currency-separated consolidated statement |
| GET | `/statements/{statement}` | Read the frozen statement |
| GET | `/statements/{statement}/html` | Read printable HTML |
| GET | `/statements/{statement}/csv` | Export CSV |

Account creation and cashbook writes require permitted administration; relevant reads require membership and exact account/Space authority. Test denied access as well as successful requests.

### Account, movement and baseline rules

Account data includes `account_name`, `account_type`, optional `institution_name`, `account_reference`, `currency`, `opening_balance_minor`, `balance_as_of` and metadata. The account reference is masked before persistence. Movement data includes direction, positive integer amount, description, transaction/value dates, type, transaction reference and source attribution. Controller validation defines required fields and allowed values.

Currency must match the account. `balance_as_of` is the fixed opening-balance date, not the latest refresh date. The account model preserves it when legacy balance-refresh callers also supply today's date. Recalculation remains observable through `updated_at`. A reviewed historical rebaselining procedure must not rely on ordinary mass assignment to change the baseline.

The tested sequence opens on 1 September, records 5 and 10 September movements and preserves the opening date. A transaction before 1 September remains rejected. Existing wrongly stored dates are not guessed or silently rewritten; corrections need original evidence.

### Import, review and issue

The CSV request uses `statement_file`, JSON-encoded `mapping`, `minor_unit_exponent`, and supplied opening/closing balances. Mapping covers date, value date, description, reference, debit, credit and balance as supplied. The service normalises rows and reuses a matching source-file hash within the Space/account. Different files can still overlap economically and require review.

Responses expose matches, exceptions, suggestions and `review_todos`. Decisions include matching, creating an evidenced missing book entry, marking an external-only or duplicate row, and accepting a book-only item or balance variance. A suggestion is not posting authority.

`ready_for_confirmation` and `confirmation_status=ready` mean the required review tasks have been resolved. Accepted differences and reasons remain visible. Issuance freezes the snapshot; do not add currencies without a supported conversion policy.

See the [treasury specification](../../../../docs/product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md), [service](../../app/Services/FinancialSpaceStatementService.php) and [tests](../../tests/Feature/FinancialSpaceStatementsTest.php). Treasury alone does not complete member capital, unitisation/NAV, distributions or investment performance.

## 2. Essentials customer API

Essentials arranges financing for a verified biller or rental beneficiary, not unrestricted wallet cash-out. These are source contracts for controlled integration. Remaining financial-control findings prevent treating the whole lifecycle as live-service certification.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/essentials` | Customer summary |
| GET | `/api/essentials/catalogue` | Catalogue |
| GET, POST | `/api/essentials/partner-authorisations` | List or grant platform permissions |
| DELETE | `/api/essentials/partner-authorisations/{authorisation}` | Revoke permission |
| POST | `/api/essentials/accounts` | Save a service account |
| POST | `/api/essentials/accounts/{account}/verify` | Verify account |
| POST | `/api/essentials/eligibility` | Refresh lender eligibility |
| GET, POST | `/api/essentials/quotes` | Read or create quotes |
| POST | `/api/essentials/quotes/{quote}/authorise-partner` | Authorise completion hand-off |
| POST | `/api/essentials/quotes/{quote}/accept` | Accept the exact disclosed quote |
| GET | `/api/essentials/advances` | Read advances |
| POST | `/api/essentials/advances/{advance}/repay` | Initiate repayment |

### Request fields

| Operation | Required | Optional and constrained |
| --- | --- | --- |
| Platform authorisation | `partner_account_id`, non-empty `scopes` | `financial_space_id`, `valid_days` 1–90, default 30; scopes `eligibility`, `account_write`, `quote_create`, `status_read` |
| Service account | `biller_id`, `account_reference` up to 255 characters | `financial_space_id`, `nickname` up to 120, metadata array |
| Eligibility | No field universally required by the customer controller | `financial_space_id`, `channel` up to 40, default `android` |
| Quote | `essentials_account_id`, positive integer `amount_minor` | `channel`, `source_partner_account_id`, `source_platform` up to 80 |
| Acceptance | 64-character `disclosure_hash`, accepted `accept_disclosures` | Authenticated customer and quote identify the target; client-calculated pricing is not accepted here |
| Repayment | Positive integer `amount_minor`, body `idempotency_key` up to 160 characters | `wallet_id` nullable in controller input; omission is not a blanket safe-wallet guarantee |

See the [controller](../../app/Http/Controllers/Api/EssentialsController.php). Partner scoping and actual store-channel enforcement are separate controls; an optional client field does not establish either.

### Response, error and replay handling

Created accounts, authorisations, quotes, accepted advances and submitted repayments use 201. Essentials repayment 201 is not collection finality and differs from ordinary-loan 202 initiation. Quote creation returns `data.quote` and `data.disclosure_hash`; acceptance returns `data.advance`; repayment returns `data.repayment`. Inspect state and references.

Handled invalid arguments use 422 and certain runtime conflicts use 409. Framework/proxy errors need not share the custom envelope; HTML/CSV exports are not JSON. Essentials requires the body key; do not assume an ordinary-loan header-or-body contract applies. Reuse a key only for the same logical instruction and reconcile ambiguity before another economic request.

The shared customer mutex returns a safe 409 for a competing covered operation. It does not automatically retry a callback, change the native reservation-commit boundary or make a still-pending provider collection safe to resubmit with a new key. Existing forbidden and not-found responses retain their distinct meanings.

## 3. Partners and operations

Partner routes register authentication, throttling and `partner_api`, `platform_admin` or `operations` roles. Customer permission and exact target records still matter:

- `POST /api/partner/essentials/customers/{customer}/eligibility`
- `POST /api/partner/essentials/customers/{customer}/accounts`
- `POST /api/partner/essentials/customers/{customer}/quotes`
- `GET /api/partner/essentials/customers/{customer}/status`
- `POST /api/partner/essentials/quotes/{quote}/complete`

An omitted partner Space selects the existing Personal Space. Active membership and the applicable exact-Space grant are required. It never means all customer Spaces. The grant-first denial path avoids disclosing whether a missing grant or inactive membership caused denial.

The revised eligibility response is deliberately smaller than the native customer summary:

| Field | Meaning |
| --- | --- |
| `data.financial_space_id` | The resolved and authorised Space |
| `data.lines[]` | Only that Space's line identifiers, lender/product identifiers, approved/available amounts, currency, status and expiry |
| `data.overall.financial_space_id` | Same resolved Space |
| `data.overall.overall_available_limit_minor` | Existing overall ceiling bounded by the largest eligible target-Space line, never the sum of lines |
| `data.overall.currency` | The single line currency, or null when there is no safe single-currency amount |
| `data.overall.limits_are_not_additive` | True |

The response omits accounts, advances, raw decision snapshots, provider evidence and profile collections. Mixed currencies return no invented total. Eligibility permission does not imply permission to read other financial history. Status responses retain only their resolved Space's permitted advances.

Operations under `/api/admin/essentials` include portfolio/work queue, biller create/update, account verification, lender configuration, advance reconciliation and repayment reconciliation. Programme-partner reporting remains a separate role and authority.

PR #118 implements this authority/closure slice and its test controls. It does not by itself complete immutable accounting, canonical provider instructions, pending collection reservations, mandate lifecycle or pending funding/reversal exposure. Read [the detailed supplement](ESSENTIALS_AUTHORITY_AND_CLOSURE.md) and [verification record](../../../../docs/operations/ESSENTIALS_AUTHORITY_VERIFICATION_2026-09-25.md) before claiming release acceptance. Never publish completion tokens, credentials or raw identity evidence.

## 4. Location Context

Registered routes include `GET /api/location/status`, `GET/POST /api/location-contexts`, `DELETE /api/location-contexts/{context}`, `POST /api/location/places/autocomplete`, `GET /api/location/static-map/{context}`, `POST /api/location/route` and `GET /api/location/nearby-services`.

Acceptance requires optional purpose-specific location, manual fallback, no background tracking, authorisation before provider resolution and no location-driven credit-score/limit/pricing changes. Keys remain server-side. Registration does not establish provider or device acceptance.

## 5. Maintenance

Update changed routes, validation, fields, roles, states, errors and retries with the endpoint index and relevant task/UAT case. Original requirements remain separate from defects. This guide is not a complete OpenAPI specification, generated SDK or financial-release certificate.
