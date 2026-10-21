# OpFin API integrator guide: first request to advanced and AI workflows

**Classification:** Controlled external technical reference  
**Reviewed:** 21 October 2026  
**Authority:** Registered source routes, controller contracts, reviewed catalogue definitions and domain authorisation  
**Language:** English (United Kingdom)

## Choose your path

| Your experience | Start here | What to verify before integration |
| --- | --- | --- |
| New to APIs | Sections 1–3 and the public learning guides | HTTP method, origin, response envelope and which routes need a token |
| App or backend developer | Sections 4–7 and a domain contract | Actor role, Financial Space, consent, input validation, retries, webhooks, idempotency and failure states |
| Senior/enterprise integrator | Sections 6–9 and the release controls | Tenant isolation, financial invariants, reviewed schema coverage, audit evidence and provider reconciliation |
| AI/agent developer | Sections 8–9 | Role-filtered discovery only; no implied permission to execute financial operations |

Every operation remains server-authoritative. A search result, OpenAPI entry or model-generated proposal does not grant authentication, permission, funding, legal authority or settlement finality.

## 1. The HTTP basics

A **route** combines an HTTP method (GET, POST, PUT or DELETE) and a path. A **request** carries any allowed parameters/body and, for protected routes, the calling user's authentication. A **response** has a status code, headers and a body. A **Financial Space** is the explicitly authorised operating context, not an alternative login identity.

The API origin is the host before `/api`. For example, set `OPFIN_API_ORIGIN` to the origin of your own authorised sandbox instance, with no trailing `/api`. Do not paste live tokens into terminals, issues, screenshots or generated documentation. All examples are read-only unless they explicitly say otherwise.

## 2. First public request (no financial data)

```bash
# Configure only the approved environment and origin.
export OPFIN_API_ORIGIN='https://your-approved-api-origin.invalid'

curl --fail-with-body --silent --show-error \
  --header 'Accept: application/json' \
  "$OPFIN_API_ORIGIN/api/developer/manifest"
```

The source manifest identifies the public catalogue, checked-in guides, authenticated catalogue, reviewed OpenAPI and agent-tool discovery, and explicitly sets `financial_execution=false`. An origin ending in `/api` would duplicate the prefix. The example hostname is deliberately not a real endpoint.

Explore the public guide search:

```bash
curl --fail-with-body --silent --show-error \
  --header 'Accept: application/json' \
  "$OPFIN_API_ORIGIN/api/developer/guides?q=authentication"
```

## 3. Read a response properly

The common `ApiResponse::success` JSON wrapper contains `success`, `message` and `data`. The common error wrapper contains `success=false`, `message` and `errors`. **Not every route uses this wrapper.** Reviewed OpenAPI is a direct JSON document; CSV/HTML exports and framework errors may use another content type and structure.

Always check the **HTTP status**, `Content-Type`, expected schema and any **domain-specific business status**. A 200/201/202 response does not prove money changed hands or an institution authenticated a statement.

Typical outcomes: 401 unauthenticated, 403 forbidden, 404 unavailable/inaccessible target, 409 conflicting or uncertain original financial request, 422 invalid input, 429 throttled, and 503 unavailable/disabled capability. These are guidance, not a universal per-route contract. Use each operation's declared response statuses.

## 4. Authenticated discovery

After obtaining an authorised token through the application's normal login/consent flow, use it only in an approved environment:

```bash
# Set OPFIN_TOKEN securely in your runtime, not in source control.
curl --fail-with-body --silent --show-error \
  --header 'Accept: application/json' \
  --header "Authorization: Bearer $OPFIN_TOKEN" \
  "$OPFIN_API_ORIGIN/api/developer/catalogue?q=statement"
```

The authenticated catalogue derives the role from the token's actual user. Supported filters include `q`, `page`, `limit`, `group` and `method`; search is capped and paginated. For one visible operation, use `GET /api/developer/operations/{operation}`. `GET /api/developer/openapi` includes **reviewed** contracts only, not unreviewed registration-only routes. `GET /api/developer/agent-tools` advertises documentation-only search/description tools.

The API sends `Cache-Control: private, no-store`, request IDs and a contract fingerprint on developer responses. The fingerprint identifies source/contract change, not actual production parity or certification.

## 5. Financial Space and role permissions

Never guess a Space ID, user ID or financial product from a visible URL. The backend verifies membership, role, purpose, consent, product entitlement and sometimes additional institution/case authority. A platform administrator is not automatically an authorised lender, customer or institutional portfolio participant.

The code defines authenticated club discovery at `GET /api/accounting/club-schema` and `GET /api/accounting/my-club-books`. The latter supports retained former-member **own-history** access; it is not a club-wide data export.

For feature-gated Financial Intelligence, see [the precise contract](FINANCIAL_INTELLIGENCE_CONTRACT.md) and its guards before calling `GET /api/financial-spaces/{space}/intelligence/context`. Registered routes do not mean the feature is activated or that a source statement has been authenticated.

## 6. Recovering an uncertain operation

**Read-only operations** may generally be retried within their documented rate limits. **Money-changing or accounting operations** must be governed by their specific stable idempotency key and original request/attempt semantics. Do not retry an ambiguous payment with a new key or automatically switch payment providers after an unknown result.

Club client request recovery uses:

| Method | Registered route | Meaning |
| --- | --- | --- |
| GET | `/api/accounting/saved-requests` | Current user's unresolved saved-request metadata |
| POST | `/api/financial-spaces/{space}/accounting/books/{book}/client-requests/prepare/{purpose}` | Persist an instruction or statement request before domain submission |
| POST | Same prefix + `/inspect` | Re-read a bound encrypted request under current permissions |
| POST | Same prefix + `/submit` | Resume the original domain operation or retrieve its recorded result |
| POST | Same prefix + `/acknowledge` | Release the recovery slot after reading the result |
| POST | Same prefix + `/cancel` | Cancel only if no domain submission occurred |

`prepare` requires an original domain envelope including its `idempotency_key`. Follow-up actions require the server-issued `reference` and `content_hash`. The backend checks current authorisation and unchanged meaning. Acknowledging the saved result is **not** independent accounting approval or a provider payment. Read [full club recovery contract](CLUB_CLIENT_RECOVERY.md) and [club accounting guide](CLUB_ACCOUNTING.md).

Essentials repayments expose `GET /api/essentials/repayments/{repayment}/collection-status` and `POST /api/essentials/repayments/{repayment}/cancel-unsubmitted`. Cancellation is only allowed before external submission. The underlying collection cannot be assumed failed because an HTTP response was lost. Read [durable collection contract](ESSENTIALS_DURABLE_COLLECTIONS.md).

## 7. Domain references and boundaries

| Domain | Read this before integrating |
| --- | --- |
| Club accounting, member ownership and immutable statements | [Club accounting](CLUB_ACCOUNTING.md), [client recovery](CLUB_CLIENT_RECOVERY.md) |
| Essentials bill/rent finance and repayment | [Essentials authority](ESSENTIALS_AUTHORITY_AND_CLOSURE.md), [durable collections](ESSENTIALS_DURABLE_COLLECTIONS.md) |
| Financing requests from partner applications | [Partner financial intents](PARTNER_FINANCIAL_INTENTS.md) |
| Payroll-deduction cases and customer deletion | [Account/payroll/deletion contracts](ACCOUNT_AND_DATA_DELETION.md), [payroll deduction](PAYROLL_DEDUCTION.md) |
| Portfolio analytics and statement review/appeal | [Financial Intelligence](FINANCIAL_INTELLIGENCE_CONTRACT.md) |
| Managed assets/product finance | [Asset registry](ASSET_REGISTRY_CONTRACT.md), [product factory](PRODUCT_FACTORY_CONTRACT.md) |
| Developer and AI discovery | [Developer interface](DEVELOPER_INTERFACE.md), [API/AI architecture](../../../../docs/developer/API_AND_AGENT_PLATFORM.md) |
| Complete registered paths and source-linked contract inventory | [Current endpoint index](current-endpoints.md), [quick reference](API_QUICK_REFERENCE.md) |

Do not treat a partner referral, parsed statement, configured capital mandate, synthetically balanced ledger, or provider's initial HTTP acknowledgement as a completed regulated product/transaction.

## 8. Using OpFin safely with AI

A model can **search guides, describe a visible operation, explain requirements and propose a next action**. Current discovery tools are deliberately read-only and never perform financial writes. A model must not derive authority from a prompt, a retrieved page, a guessed operation name or a locally cached user profile.

A future write-capable agent would need, independently: authenticated principal, least-privilege delegation, the exact Financial Space, active consent and purpose, pre-execution validation, human approval where needed, immutable intent and idempotency, attributable execution, reconciliation and audited result. No generic arbitrary HTTP/SQL/financial execution endpoint is provided by the read-only Developer Centre.

The contract fingerprint helps identify drift. Re-search the current catalogue and revalidate client assumptions after a change; do not infer that a route exists merely because a model remembers it.

## 9. Local development and CI synchronisation

From a full checkout with required dependencies:

```bash
make api-docs
make api-docs-check
make docs-check
make publication-check
make agent-docs-test
cd apps/api
php artisan route:list --json
php artisan api:catalogue --check --require-complete
```

`--require-complete` is expected to **fail** while registered operations lack reviewed schemas. An open coverage gap is not corrected by generating a permissive schema from method/path names. Review request/response shapes, role/Space ownership, errors, examples, consent, state transitions, accounting and negative tests before marking an operation documented.

See [current application/release evidence](../../../../docs/operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md). Full database, Web, Flutter, Android/iOS, device/accessibility and institutional/provider acceptance cannot be replaced by documentation checks.
