# Current capability contracts: treasury, Essentials and location

Status: Source-based developer and integration reference  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

## Evidence and scope

This supplements [current endpoints](current-endpoints.md) and the [client contract](frontend-backend-contract.md). Exact registration comes from [API routes](../../routes/api.php); controller validation and service policies establish behaviour. Use `/api` once. Registration does not grant authority or activate a provider.

The treasury opening-baseline and role-catalogue corrections at candidate `a56289bcfbf05759d6afd844d89f7e8a39ae34db` passed the existing API build procedure: **265 tests, 1,872 assertions**, dependency audit and asset build. Verification was isolated and deliberately stopped before runtime deployment. Deployment acceptance must still identify the merged and running revision.

The [24 September evidence](../../../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) is historical. Its six failing tests are resolved in this tested candidate, not retroactively erased. Essentials financial-control findings remain separate and unresolved by this treasury repair.

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

Currency must match the account. **`balance_as_of` is the fixed opening-balance date established at creation, not the latest refresh date.** The account model now preserves it when legacy balance-refresh callers also supply today's date. Recalculation remains observable through `updated_at`. A reviewed historical rebaselining procedure must not rely on ordinary mass assignment to alter the baseline.

The tested historical sequence sets a 1 September opening balance, records 5 and 10 September movements, and preserves the opening date. A transaction before 1 September remains rejected. Existing wrongly stored dates are not guessed or silently rewritten by this repair; any such record needs original source evidence.

### Import, review and issue

The CSV request uses `statement_file`, JSON-encoded `mapping`, `minor_unit_exponent`, and supplied opening/closing balances. Mapping covers date, value date, description, reference, debit, credit and balance as supplied. The service normalises rows and reuses a matching source-file hash within the Space/account. Different files can still overlap economically and require review.

Responses expose matches, exceptions, suggestions and `review_todos`. Decisions include matching, creating a justified missing book entry, marking an external-only or duplicate row, and accepting a book-only item or balance variance with evidence. A suggestion is not posting authority.

`ready_for_confirmation` and `confirmation_status=ready` mean required review tasks have been resolved. Accepted differences and reasons remain visible. Issuance freezes the statement snapshot; do not add currencies without an explicit supported conversion policy.

See the [treasury specification](../../../../docs/product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md), [service](../../app/Services/FinancialSpaceStatementService.php) and [regression tests](../../tests/Feature/FinancialSpaceStatementsTest.php). Treasury does not by itself complete member capital, unitisation/NAV, distributions or investment performance.

## 2. Essentials customer API

Essentials arranges named third-party lender financing for a verified biller or rental beneficiary, not unrestricted wallet cash-out. These are source contracts for controlled integration. Current financial-control findings prevent treating them as live-service certification.

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
| POST | `/api/essentials/quotes/{quote}/authorise-partner` | Authorise separate completion hand-off |
| POST | `/api/essentials/quotes/{quote}/accept` | Accept the exact disclosed quote |
| GET | `/api/essentials/advances` | Read advances |
| POST | `/api/essentials/advances/{advance}/repay` | Initiate repayment |

### Request fields

| Operation | Required | Optional and constrained |
| --- | --- | --- |
| Platform authorisation | `partner_account_id`, non-empty `scopes` | `financial_space_id`, `valid_days` 1–90, default 30; scopes `eligibility`, `account_write`, `quote_create`, `status_read` |
| Service account | `biller_id`, `account_reference` up to 255 characters | `financial_space_id`, `nickname` up to 120, metadata array |
| Eligibility | No field universally required by the controller | `financial_space_id`, `channel` up to 40, default `android` |
| Quote | `essentials_account_id`, positive integer `amount_minor` | `channel`, `source_partner_account_id`, `source_platform` up to 80 |
| Acceptance | 64-character `disclosure_hash`, accepted `accept_disclosures` | Authenticated customer and quote identify the target; client-calculated pricing is not accepted here |
| Repayment | Positive integer `amount_minor`, body `idempotency_key` up to 160 characters | `wallet_id` nullable in the current controller; omission is not an assurance of safe wallet policy |

See the [controller](../../app/Http/Controllers/Api/EssentialsController.php). A nullable Space or client-supplied channel does not prove secure partner scoping or store-policy enforcement. Those require negative acceptance tests.

### Response, error and replay handling

Created accounts, authorisations, quotes, accepted advances and submitted repayments use 201. **Essentials repayment 201 is not collection finality**, and differs from ordinary-loan 202 initiation. Quote creation returns `data.quote` and `data.disclosure_hash`; acceptance returns `data.advance`; repayment returns `data.repayment`. Inspect state and references.

Handled invalid arguments use 422 and certain runtime conflicts use 409. Framework/proxy errors need not share the custom envelope; HTML/CSV exports are not JSON. Essentials requires the body key; do not assume the ordinary-loan header-or-body contract applies. Reuse a key only for the same logical instruction and reconcile ambiguity before another economic request. Sequential replay does not prove concurrent safety.

## 3. Partners and operations

Partner routes register authentication, throttling and `partner_api`, `platform_admin` or `operations` roles. Customer permission and exact target records still matter:

- `POST /api/partner/essentials/customers/{customer}/eligibility`
- `POST /api/partner/essentials/customers/{customer}/accounts`
- `POST /api/partner/essentials/customers/{customer}/quotes`
- `GET /api/partner/essentials/customers/{customer}/status`
- `POST /api/partner/essentials/quotes/{quote}/complete`

Operations under `/api/admin/essentials` include portfolio/work queue, biller create/update, account verification, lender configuration, advance reconciliation and repayment reconciliation. Programme-partner aggregate access is a separate role and authority.

Pending lender funding/reversal must reserve exposure until resolved. Accounting, deletion, exact-Space grants, mandate usability and concurrent repayment findings are not closed by provider configuration or the treasury repair. Never publish completion tokens, provider credentials or raw identity evidence.

## 4. Location Context

Registered routes include `GET /api/location/status`, `GET/POST /api/location-contexts`, `DELETE /api/location-contexts/{context}`, `POST /api/location/places/autocomplete`, `GET /api/location/static-map/{context}`, `POST /api/location/route` and `GET /api/location/nearby-services`.

Acceptance requires optional purpose-specific location, manual fallback, no background tracking, authorisation before provider resolution and no location-driven credit-score/limit/pricing changes. Keys remain server-side. Registration does not prove provider configuration or device acceptance.

## 5. Maintenance

Update changed routes, validation, fields, roles, states, errors and retries with the endpoint index and relevant task/UAT case. Original requirements remain separate from defects. This guide is not a complete OpenAPI specification, generated SDK or financial-release certificate.
