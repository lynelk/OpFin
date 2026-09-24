# Current capability contracts: treasury, Essentials and location

Status: Source-based developer and integration reference  
Reviewed: 24 September 2026  
Language: English (United Kingdom)  
Source baseline: `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`

## How to use this reference

This supplements [current endpoints](current-endpoints.md) and the [client contract](frontend-backend-contract.md). It explains recently delivered source capabilities without claiming production acceptance. Exact registration comes from [API routes](../../routes/api.php); controller validation and service policies establish behaviour.

Use `/api` once in the base URL. A route appearing here does not grant permission or activate a provider. Authentication, record ownership, Financial Space membership, roles, consent, feature gates and provider readiness remain separate checks.

The [delivery evidence record](../../../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) identifies current API build failures and unresolved Essentials control findings. Integrators must not use this document as approval to move real money.

## 1. Treasury and statements

Treasury records represent a Space's recorded cashbook and reconciliation evidence. Importing a statement does not move money, create a verified bank balance or overwrite the financial ledger. A generated OpFin statement is not a bank-issued statement.

In the route table below, `{base}` means `/api/financial-spaces/{space}`. Replace the identifiers with authorised values returned by the API; do not append the literal placeholder.

| Method | Route after `{base}` | Purpose |
| --- | --- | --- |
| GET, POST | `/treasury/accounts` | List active accounts or create a treasury account |
| GET, POST | `/treasury/accounts/{account}/transactions` | Read or record cashbook movements |
| GET, POST | `/treasury/accounts/{account}/statement-imports` | List imports or upload a mapped statement |
| GET | `/statement-imports/{import}` | Inspect parsed rows, suggestions and review tasks |
| POST | `/statement-imports/{import}/reconcile` | Match records and refresh review state |
| POST | `/statement-rows/{row}/match` | Match an authorised cashbook transaction |
| POST | `/statement-rows/{row}/resolve` | Resolve a row using a permitted documented decision |
| POST | `/statement-imports/{import}/book-transactions/{transaction}/accept` | Record a reviewed book-only exception |
| POST | `/statement-imports/{import}/balance-variance` | Record an explicit variance decision |
| POST | `/statement-imports/{import}/confirm` | Confirm only after the review requirements are satisfied |
| GET | `/statements` | List issued statements |
| POST | `/treasury/accounts/{account}/statements` | Issue an account statement |
| POST | `/statements/consolidated` | Issue a consolidated, currency-separated statement |
| GET | `/statements/{statement}` | Read a frozen statement |
| GET | `/statements/{statement}/html` | Read its printable HTML representation |
| GET | `/statements/{statement}/csv` | Download its CSV representation |

The inspected service requires administration for account creation and cashbook writes, and membership for the relevant reads; it also verifies the account belongs to the requested Space. Test both successful and denied access rather than relying on a route prefix.

### Account and movement data

The current account service consumes `account_name`, `account_type`, optional `institution_name`, `account_reference`, `currency`, `opening_balance_minor`, `balance_as_of` and metadata. It persists a masked account reference. The cashbook consumes direction, positive integer amount, description, transaction/value dates, transaction type, reference and source attribution. Controller validation remains the authority for required fields and allowed values.

A movement's currency must match its account. The opening-baseline guard rejects a transaction or import before the account baseline. Current regression failures show that historical-date workflows are not accepted; do not change source dates or invent a new baseline to bypass an error.

### Import and review semantics

The inspected test submits `statement_file`, a JSON-encoded column `mapping`, `minor_unit_exponent`, and supplied opening/closing balances. Mapping includes transaction date, value date, description, reference, debit, credit and balance where present. The service reads CSV, normalises rows and deduplicates the same file hash within its Space/account.

The review response exposes row matches, exceptions, suggestions and `review_todos`. Registered decisions include matching a transaction, creating a justified book entry, marking an external-only/duplicate row, accepting a book-only item and accepting a variance with evidence. A suggestion is not automatic authority to post or confirm.

`ready_for_confirmation` and `confirmation_status=ready` mean the required review tasks have been resolved; they do not make previously accepted differences disappear. Preserve reasons and evidence. Statement issuance freezes the reporting snapshot, and different currencies must not be added into an unexplained total.

See the [treasury product specification](../../../../docs/product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md), [statement service](../../app/Services/FinancialSpaceStatementService.php) and [statement regression tests](../../tests/Feature/FinancialSpaceStatementsTest.php). Member capital, unitisation/NAV, distributions and investment performance are separate extensions, not consequences of a CSV import.

## 2. Essentials customer API

Essentials arranges financing from a named third-party lender for a verified biller or rental beneficiary. It is not an unrestricted wallet payout. The current review identifies internal financial-control gaps, so the following is a source contract for controlled integration work, not a live-service certification.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/essentials` | Read the customer's summary |
| GET | `/api/essentials/catalogue` | Read the catalogue |
| GET, POST | `/api/essentials/partner-authorisations` | List or create platform permissions |
| DELETE | `/api/essentials/partner-authorisations/{authorisation}` | Revoke a permission |
| POST | `/api/essentials/accounts` | Save a service account |
| POST | `/api/essentials/accounts/{account}/verify` | Request account verification |
| POST | `/api/essentials/eligibility` | Refresh participating lender eligibility |
| GET, POST | `/api/essentials/quotes` | Read recent quotes or create a quote |
| POST | `/api/essentials/quotes/{quote}/authorise-partner` | Authorise the separate quote-completion hand-off |
| POST | `/api/essentials/quotes/{quote}/accept` | Accept the exact disclosed quote |
| GET | `/api/essentials/advances` | Read customer advances |
| POST | `/api/essentials/advances/{advance}/repay` | Initiate repayment |

### Inspected request fields

The [Essentials controller](../../app/Http/Controllers/Api/EssentialsController.php) validates the following. Additional service-level constraints still apply.

| Operation | Required fields | Optional fields and important constraints |
| --- | --- | --- |
| Create platform authorisation | `partner_account_id`; non-empty `scopes` array | `financial_space_id`; `valid_days` between 1 and 90, default 30. Allowed scopes: `eligibility`, `account_write`, `quote_create`, `status_read` |
| Save service account | `biller_id`; `account_reference` string, maximum 255 characters | `financial_space_id`; `nickname` up to 120 characters; metadata array |
| Refresh eligibility | No field is universally required by this controller | `financial_space_id`; `channel` up to 40 characters, default `android` |
| Create quote | `essentials_account_id`; integer `amount_minor` greater than zero | `channel`; `source_partner_account_id`; `source_platform` up to 80 characters |
| Accept quote | `disclosure_hash` string of exactly 64 characters; accepted `accept_disclosures` value | The authenticated customer and quote identifier determine the target; no client-calculated price is accepted here |
| Repay | Integer `amount_minor` greater than zero; `idempotency_key` string up to 160 characters | `wallet_id` is nullable in the current controller; verify the approved ownership/fallback policy before relying on omission |

`financial_space_id` being nullable is an observed input contract, not assurance that omitted context is safe for partner grants. The unresolved review requires exact-Space authorisation. Similarly, a client-supplied channel is not evidence that store-policy enforcement cannot be bypassed.

### Responses, failures and retries

The inspected controller returns 201 for created accounts, authorisations, quotes, accepted advances and submitted repayments. In particular, an Essentials repayment's **201 does not mean collection is final**; it differs from the existing ordinary-loan 202 initiation contract.

Quote creation returns `data.quote` and `data.disclosure_hash`. Acceptance returns `data.advance`. Repayment returns `data.repayment`. Read the underlying state and references instead of interpreting a success envelope as economic completion.

Handled invalid-argument errors use 422. Some handled runtime conflicts use 409. Validation, authentication, framework and proxy errors must not be assumed to have an identical custom envelope. HTML and CSV statement exports are not JSON envelopes.

The Essentials repayment controller requires `idempotency_key` in the body. Do not assume that the ordinary loan endpoint's header-or-body behaviour applies. Reuse one key for one logical instruction, retain the request reference, and resolve ambiguous provider state before initiating another economic request. Current concurrency findings mean sequential replay evidence alone is insufficient.

## 3. Partner and operations APIs

Partner Essentials routes register `auth:sanctum`, API throttling and `partner_api`, `platform_admin` or `operations` roles. Customer permission and target-record checks remain necessary:

- `POST /api/partner/essentials/customers/{customer}/eligibility`
- `POST /api/partner/essentials/customers/{customer}/accounts`
- `POST /api/partner/essentials/customers/{customer}/quotes`
- `GET /api/partner/essentials/customers/{customer}/status`
- `POST /api/partner/essentials/quotes/{quote}/complete`

Operations routes register platform-admin/operations access. They include the portfolio and work queue, biller creation/update, account verification, lender configuration, advance reconciliation and repayment reconciliation under `/api/admin/essentials`.

A programme partner is a different role with programme-scoped aggregate reporting. Do not substitute a programme-partner grant for a lending-platform authorisation. Secrets, completion tokens and provider credentials must not appear in documentation, ordinary logs or source control.

Pending lender funding and reversal states must reserve the relevant exposure until reconciled. Current findings cover that calculation, deletion obligations, capital-mandate eligibility, concurrent repayments and immutable accounting. Do not describe provider configuration alone as the remaining activation requirement.

## 4. Location Context

Current registered routes include `GET /api/location/status`, `GET/POST /api/location-contexts`, `DELETE /api/location-contexts/{context}`, `POST /api/location/places/autocomplete`, `GET /api/location/static-map/{context}`, `POST /api/location/route` and `GET /api/location/nearby-services`.

The current UAT contract requires optional, purpose-specific location, manual fallback, no background tracking, authorisation before external provider resolution and no location-driven credit-score/limit/pricing changes. Provider keys remain server-side. Route registration is not a claim that the Google provider is configured or that every device permission flow has passed.

## 5. Changes to this reference

Any affected route, controller validation, response field, role/Space rule, provider state, error or retry policy must update this reference, the current endpoint index and relevant manual task/UAT case in the same change. Preserve the original concept requirement separately from observed defects. This is not a complete OpenAPI specification, generated SDK contract or production certification.
