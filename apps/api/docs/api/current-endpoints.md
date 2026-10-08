# Current API endpoints

Status: Current endpoint navigation and contract index  
Reviewed: 27 September 2026  
Reviewed: 3 October 2026  
Language: English (United Kingdom)

The complete domain reference from main `3924a26913f85067a3ac900c78fa80125ca589fc` is preserved without content loss in [Domain endpoints](domain-endpoints.md), including the lender-orchestration additions. This index joins that detailed reference with the [Developer Centre contract](DEVELOPER_INTERFACE.md). Registration, reviewed schema, authorisation, provider activation and financial acceptance are separate states.

## Developer and AI discovery

The browser entry point is `/developers` on the API origin in a deployment containing this feature. The following operations are documentation-only and never execute a discovered financial operation.

| Method | Path | Access and purpose |
| --- | --- | --- |
| GET | `/api/developer/manifest` | Public discovery links and source fingerprints |
| GET | `/api/developer/public` | Explicitly published public operation search |
| GET | `/api/developer/guides` | Public learning-guide search using `q` |
| GET | `/api/developer/guides/{guide}` | Read a guide from an explicit identifier allow-list |
| GET | `/api/developer/catalogue` | Authenticated, role-filtered operation search |
| GET | `/api/developer/operations/{operation}` | Read one visible operation contract or its coverage gap |
| GET | `/api/developer/openapi` | Authenticated, role-filtered raw OpenAPI for reviewed contracts |
| GET | `/api/developer/agent-tools` | Authenticated metadata for four read-only documentation tools |

Search accepts `q` up to 160 characters, `page` from 1 to 1000, `limit` from 1 to 50, `group` and `method`. Permission derives from the actual authenticated user. A caller-supplied role or Space does not widen documentation access or authorise a native operation.

Ordinary discovery responses use `success`, `message` and `data`; `/openapi` returns the raw specification. Existing HTML/CSV exports and framework/proxy errors have their own contracts. The [local MCP bridge](../../../../tools/opfin-mcp/README.md) exposes search and reading only, not financial writes or an arbitrary HTTP executor.

## 1. Health

Read the [domain reference](domain-endpoints.md#1-health). Liveness is not financial readiness or provider activation.

`GET /api/health/ready` reports the `operations.scheduler` and `operations.worker` heartbeats separately:
- The scheduler writes its own heartbeat every five minutes (`opfin-scheduler-heartbeat`).
- The worker proves it is consuming the queue by running `RecordWorkerHeartbeat`, which the scheduler dispatches every five minutes (`opfin-worker-heartbeat-dispatch`).
- A heartbeat is `ready` when it is under 12 minutes old, `stale` when older, and `warming` when it has never been seen.

The API, worker and scheduler must share one cache store and prefix. `CACHE_PREFIX` defaults to `opfin_` in code, so a service without a `.env` file still agrees.

## 2. Account authentication

Read [authentication, registration and closure](domain-endpoints.md#2-account-authentication). New App registration remains phone, OTP, names and six-digit PIN; migrated Web-password compatibility is separate.

### Staff sign-in and password recovery (8 October 2026)

- `POST /api/login` also accepts `email` + `password` for staff accounts (any role other than `customer`). Customers keep `phone` + `pin`. An unknown email gets the same answer as a wrong password, and five failures lock the email for 10 minutes. The response includes `password_change_required`.
- `POST /api/account/password` (authenticated, staff only): `current_password`, `password`, `password_confirmation`. The new password needs 12+ characters with mixed case, a number and a symbol. It clears `password_change_required`, revokes the account's other tokens and is audited as `auth.password_changed`.
- `POST /api/generate-otp` accepts `channel=email` with `email` for staff. The code is emailed; the answer is the same whether or not the email belongs to a staff account. `POST /api/reset-password` accepts `email` + `otp` + `password` + `password_confirmation`.
- While `password_change_required` is set, every other authenticated API route returns HTTP 403 with `code: password_change_required`, except `GET /api/profile` and `POST /api/logout`.

## 3. Phone numbers and wallets

Read [phone/wallet contracts](domain-endpoints.md#3-phone-numbers-and-wallets). A new wallet does not multiply profile-level credit exposure.

## 4. Identity and consent

Read [identity and consent](domain-endpoints.md#4-identity-and-consent) and [governed NIN evidence](../../../../docs/architecture/IDENTITY_EVIDENCE_REUSE.md). Source, consent, expiry and environment remain mandatory; NIN evidence does not complete every KYC check.

## 5. Credit profile

Read [credit-profile contracts](domain-endpoints.md#5-credit-profile). Missing external data remains unavailable, not fabricated.

## 6. Credit applications and offers

Read [applications and offers](domain-endpoints.md#6-credit-applications-and-offers). Lender, terms, disclosure, authority and eligibility are server-controlled.

## 7. Repayment

Read [ordinary repayment](domain-endpoints.md#7-repayment) and [Essentials-specific differences](CURRENT_CAPABILITY_CONTRACTS.md). Acceptance is not collection finality; key location and response status differ by operation.

## 8. Support and accessibility

Read [support/accessibility](domain-endpoints.md#8-support-and-accessibility). Software preferences do not establish real-device acceptance.

## 9. WhatsApp and USSD

Read [assisted-channel contracts](domain-endpoints.md#9-whatsapp-and-ussd). Callback authentication and high-impact confirmation are not replaced by API discovery.

## 10. Admin/operations

Read [administration](domain-endpoints.md#10-adminoperations). Catalogue visibility does not replace target-record authorisation.

### Legacy surface hardening (3 October 2026)

| Change | Path | Contract |
| --- | --- | --- |
| Removed | `POST /api/credit-scores`, `POST /api/validate-nin` | Retired legacy credit-bureau and NIN calls that did not bind the subject to the caller or record governed consent. Use the [credit profile](domain-endpoints.md#5-credit-profile) and [KYC](domain-endpoints.md#4-identity-and-consent) contracts. Both paths now return 404. |
| Changed | Credit-profile bureau (`crb`) component | Reuses a stored bureau score only when it came through a governed route (`CITO_MANAGED`, `DIRECT_PROVIDER`). Results from the retired endpoint are no longer used. |
| Changed | `POST /api/login` | Returns 403 for accounts in the `staff_pending_review` holding role. |
| Changed | `POST /api/generate-otp` and other code-bearing SMS | The stored SMS record keeps a redacted copy (`******`). The deliverable text only travels in the encrypted queue job. |
| Changed | Legacy back-office on the API origin (`/home`, `/users`, `/institutions`, `/loan-products`, `/loan-applications`, `/loans`, `/transactions`, `/accounts`, `/float-management`, `/sms-messages`) | Requires `platform_admin` or `operations`. Creating or editing user records and institution administrators requires `platform_admin`. Changes are written to the audit trail. |
| Added | `POST /float-management/{floatTopup}/approve` | Float top-ups are recorded as `Pending`. A different staff member approves them, and only approval changes the Disbursement balance. |
| Removed | `/chats*` | The staff AI chat assistant is retired. Existing chat records are retained unchanged. |
| Changed | `GET`/`DELETE /account/delete` on the API origin | Forwards (303) to the Web app's `/account/delete`, which uses the regulated `DELETE /api/account` workflow. Returns 404 when `OPFIN_WEB_URL` is not configured. |

`php artisan opfin:legacy-roles` reports legacy role names, how the role migration maps them, and the institution administrators waiting for a reviewed staff role.

## 11. UMRA digital-lending controls

Read [domain controls](domain-endpoints.md#11-umra-digital-lending-controls) and [the control mapping](../../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md). Generated reports are not evidence of regulatory submission.

## 16A. Personal financial wellbeing

Read [wellbeing](domain-endpoints.md#16a-personal-financial-wellbeing). Retain provenance, assumptions and missing-data distinctions.

## 16B. Personal savings and protection

Read [savings/protection](domain-endpoints.md#16b-personal-savings-and-protection). Collection, custody, release, payout and issuance are separate states.

## 16C. Location Context and lightweight Google Maps

Read [Location Context](domain-endpoints.md#16c-location-context-and-lightweight-google-maps) and [current capability contracts](CURRENT_CAPABILITY_CONTRACTS.md). Optional location does not authorise background tracking or location-driven credit decisions.

## 16D. Financial Space treasury, statement import and reconciliation

Read [treasury contracts](domain-endpoints.md#16d-financial-space-treasury-statement-import-and-reconciliation). Fixed opening baselines, reviewed imports and frozen statements do not alone complete member-capital and investment accounting.

## 16E. Club accounting, saved requests and retained history

Read [book and instruction contracts](CLUB_ACCOUNTING.md) and [client recovery and native export](CLUB_CLIENT_RECOVERY.md). These are implementation candidates, not a claim of production acceptance.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/accounting/club-schema` | Guided operation fields; approval is not payment execution |
| GET | `/api/accounting/my-club-books` | Signed-in member's retained own club positions |
| GET | `/api/accounting/saved-requests` | Current user's paginated recoverable request metadata |
| POST | `/api/financial-spaces/{space}/accounting/books/{book}/client-requests/prepare/{purpose}` | Persist the original instruction or statement envelope |
| POST | `/api/financial-spaces/{space}/accounting/books/{book}/client-requests/inspect` | Inspect one exact owned request |
| POST | `/api/financial-spaces/{space}/accounting/books/{book}/client-requests/submit` | Resume its domain operation or read the original result |
| POST | `/api/financial-spaces/{space}/accounting/books/{book}/client-requests/acknowledge` | Acknowledge a result, never approve or pay |
| POST | `/api/financial-spaces/{space}/accounting/books/{book}/client-requests/cancel` | Cancel only a genuinely unsubmitted request |

Instruction recovery rechecks maker authority; statement history retains own-member access after leaving a club. Server-side encryption, exact-key replay and one unresolved slot per user/book/purpose prevent a reload from silently creating another economic instruction. Native exports receive document bytes, not credentials.

## 16F. Universal Asset Registry (Asset Passports)

Read the [asset registry contract](ASSET_REGISTRY_CONTRACT.md). Space managers register devices, vehicles and productive assets under `/api/financial-spaces/{space}/asset-passports`, and can record theft reports and disposals there. Platform admin and operations verify identity (never the person who registered the asset), clear reviews, register and release liens, record recovery and check an identifier's registry state under `/api/admin/asset-passports` and `/api/admin/asset-encumbrances/{encumbrance}/release`.

Identifiers are stored only as a keyed HMAC and a masked form. A conflicting identifier sends the passport to review without revealing the other Space. The registry returns HTTP 503 until `OPFIN_ASSET_IDENTIFIER_KEY` is configured. It is separate from the balance-sheet assets at `/api/financial-spaces/{space}/assets`.

## 16G. Financing Product Factory

Read the [Product Factory contract](PRODUCT_FACTORY_CONTRACT.md). Platform admin and operations configure versioned financing products under `/api/admin/financing-factory`: templates and their guardrails, Legal Product Passports, and products (draft, update, submit, approve, reject, activate, retire, revise). Approvals need a second person, and products must name the actual lender, funder and principal. Activation needs an approved, current passport and, for Islamic products, an approved Sharia approval from the governance record. The generic V5 product definitions at `/api/admin/product-factory/products` are unchanged.

## 17. Financial Spaces and multi-entity membership

Read [Financial Spaces](domain-endpoints.md#17-financial-spaces-and-multi-entity-membership). Membership in one Space does not disclose another.

## 18. Complete financial-life APIs

Read [financial-life contracts](domain-endpoints.md#18-complete-financial-life-apis). This existing section title names a product area, not certification that every API schema is complete.

## 19. Partner Catalogue, plans and subscriptions

Read [partner catalogue and subscriptions](domain-endpoints.md#19-partner-catalogue-plans-and-subscriptions). Entitlement, permission and product eligibility are distinct.

## 20. Revenue, service economics and financial reconciliation

Read [revenue/economics](domain-endpoints.md#20-revenue-service-economics-and-financial-reconciliation). Capital, principal and premium are not platform revenue.

## 21. Inclusive finance, programme delivery and alternative credit support

Read [programme contracts](domain-endpoints.md#21-inclusive-finance-programme-delivery-and-alternative-credit-support) and the remaining detailed programme/partner sections in the [complete domain reference](domain-endpoints.md). Original tables and examples remain intact.

## Inclusive Impact & Outcomes

Read [impact/outcomes](domain-endpoints.md#inclusive-impact--outcomes). Consent, enrolment and suppression remain mandatory; programme measurement is not underwriting.

## Lending platform configuration (25 September 2026)

The accepted `/api/admin/lending-platform` routes and scope are retained in [Domain endpoints](domain-endpoints.md#lending-platform-configuration-25-september-2026), with the [lender-orchestration contract](../../../../docs/architecture/LENDER_ORCHESTRATION.md) and [release evidence](../../../../docs/operations/LENDING_DELIVERY_2026-09-25.md). This discovery feature does not undo that work.

## Contract maintenance

The running catalogue reads registered routes and checked-in guides. `api:catalogue --check` checks definition consistency; `--require-complete` fails for missing reviewed schemas. `--baseline` also detects operation-ID changes and contract-status regressions. The offline export includes all reviewed role-specific contracts; the authenticated HTTP export remains role-filtered.

Source discovery is not automatic semantic completion or proof that production equals remote main. Update fields, examples, permissions, errors, financial recovery and training tasks with the implementation. Historical evidence keeps its original date; unresolved financial and security requirements remain separate.

## Integrated financing foundation

Authenticated customer endpoints:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/financial-intents` | List the authenticated customer's financial intents |
| POST | `/api/financial-intents` | Create a need-led financial intent within an authorised Financial Space |
| POST | `/api/product-matches` | Return only activated products compatible with the intent's financial-principles preference |
| POST | `/api/financing-applications` | Apply for a matched, activated FinancialProduct version |

`principles_preference` accepts `ALL_SUITABLE`, `SHARIA_ONLY` or `CONVENTIONAL_ONLY`. It is a product preference and must not be interpreted or stored as the customer's religion.

Product matching fails closed: a product must be `live`, have an approved/effective Legal Product Passport, and an Islamic product must additionally have an approved, unexpired Sharia approval. These endpoints establish the compatibility layer; existing `/api/credit/**` and Essentials endpoints remain operational during migration.

## Embedded payroll deduction capability (27 September 2026)

Read [Embedded payroll deduction capability](PAYROLL_DEDUCTION.md) for the controlled state model, activation boundary and reconciliation rules.

Customer endpoints add `/api/financing-applications`, `/api/payroll-deduction/provider-capability` and the `/api/payroll-deduction/cases/**` namespace. Authorised operations use `/api/operations/payroll-deduction/cases/**` to record affordability, reservation, Key Facts submission, vote decision, payroll submission, payroll result, amendment and reconciliation evidence.

The PDMS adapter is fail-closed by default. The business workflow may be operated using verified evidence while the official machine interface is unavailable, but no live external endpoint or credential is invented. Payroll submission success is not accounting finality; a successful result still enters `reconciliation_pending` until expected and recovered amounts are matched.

## Financial Intelligence candidate (26 September 2026)

Read the [Financial Intelligence contract](FINANCIAL_INTELLIGENCE_CONTRACT.md), [source-scope register](../../../../docs/product/FINANCIAL_INTELLIGENCE.md) and [acceptance runbook](../../../../docs/operations/FINANCIAL_INTELLIGENCE_RUNBOOK.md). These are disabled-by-default candidate routes, not a completed or deployed product. Existing domain and Developer Centre contracts above remain in force.

The institutional namespace is `/api/financial-spaces/{space}/intelligence`. It includes role-aware context, source registration, staged JSON/CSV imports, independent publication, source-reconciled portfolio analysis, comparison and sensitivity analysis, assigned cases, expiring access grants, frozen reports and explicit report-sharing mandates. Personal owners receive statement permissions only. Imports and statement evidence do not post payments, alter core accounting or become credit decisions.

The candidate also includes jurisdiction-specific issuer-version administration under `/api/intelligence/admin/issuers` and purpose-bound statement evidence under the scoped namespace. Statement PDFs are screened before parsing: active content (scripts, launch/submit actions, embedded files, XFA, including `#xx`-escaped names) and password protection are refused, and size, page, decode and line limits apply. Accepted PDFs are analysed on the queue by a versioned layout reader. The only reader so far is the generic running-balance reader, which is **not validated** for any issuer; issuer-specific readers need authorised redacted samples. Statement responses carry separate `assurance` codes (`institution_eligibility`, `account_authority`, `extraction`, `financial_consistency`, `source_authenticity`, `review`, `document_signals`) and a plain-language `status_explanation`. `opfin:statements:analyse-pending` runs every five minutes to recover interrupted analysis (two attempts at most). `opfin:statements:purge-originals` runs daily and deletes originals and their analysis `OPFIN_STATEMENT_RETENTION_DAYS` (default 90) after analysis permission ends, unless a legal hold applies. Antivirus scanning is not yet in place because it needs an approved scanner service.

Institutional Spaces review flagged statements:
- `GET /statement-reviews` is the queue for administrators and `reviewer` grants.
- `POST /statements/{statement}/reviews` records a reasoned, append-only decision. Reviewers cannot decide their own uploads; escalations need an administrator; appeals go to a different reviewer.
- `POST /statements/{statement}/appeal` lets the uploader appeal a rejection once.
- `supersedes_statement_id` on upload links a resubmission and keeps the earlier version.

Arithmetic consistency is not issuer authentication, and no review decision changes that. The actual application, database, browser and mobile build gates remain outstanding; discoverable routes or written tests do not establish acceptance.

## Payroll, partner referral and account-deletion release contracts (2 October 2026)

These are source/release-candidate contracts, not evidence of financial-provider activation or production deployment.

| Method | Endpoint | Contract |
| --- | --- | --- |
| GET | `/api/account/deletion-readiness` | Current authenticated structured blockers, optional categories and retention disclosure |
| DELETE | `/api/account` | PIN/password re-authentication and `confirmation=DELETE`; `completed` is the only successful closure result; any obligation returns HTTP 409 `blocked_obligations` immediately |
| DELETE | `/api/account/data` | Re-authentication, `confirmation=DELETE_DATA` and `data_categories`; explicit `data_deleted` keeps the account active |
| GET | `/api/financing-applications` | Current customer's financing applications |
| GET/POST | `/api/payroll-deduction/cases` | Read/start an eligible salary-finance case inside an active authorised Financial Space |
| GET | `/api/payroll-deduction/cases/{case}` | Customer-owned case and attempt-specific reconciliation states |
| GET | `/api/payroll-deduction/provider-capability` | Honest inactive/live adapter capability, not fabricated PDMS certification |
| POST | `/api/payroll-deduction/cases/{case}/undertaking` | Atomically record the case/reference/amount-bound payroll undertaking and request reservation; `authorised=true`, positive `requested_deduction_minor`, optional agreement reference |
| POST | `/api/payroll-deduction/cases/{case}/reservation` | Compatibility request requiring a current case-bound undertaking consent; generic credit consent is insufficient |
| POST | `/api/payroll-deduction/cases/{case}/cancel` | Local cancellation only before uncertain/external reservation exposure; otherwise `cancellation_pending` until evidenced release |
| GET | `/api/operations/payroll-deduction/cases` | Authorised operations queue |
| GET | `/api/operations/payroll-deduction/cases/{case}` | Operations case, immutable transition evidence and per-attempt results |
| POST | `/api/operations/payroll-deduction/cases/{case}/affordability` | Record affordability evidence and buy-off exception, not an invented external check |
| POST | `/api/operations/payroll-deduction/cases/{case}/reservation` | Positive confirmation requires reservation/agreement references and a current undertaking |
| POST | `/api/operations/payroll-deduction/cases/{case}/key-facts` | Current reservation and mandate required; retain submitted facts |
| POST | `/api/operations/payroll-deduction/cases/{case}/vote-decision` | Positive approval checks locked reservation expiry and current undertaking |
| POST | `/api/operations/payroll-deduction/cases/{case}/payroll-submission` | Government Code 482; increment submission attempt, preserve prior attempts |
| POST | `/api/operations/payroll-deduction/cases/{case}/payroll-result` | Period must match submission; each attempt retains original outcome, amounts and provider evidence |
| POST | `/api/operations/payroll-deduction/cases/{case}/amend` | Return to reservation-pending, never manufacture renewed external reservation |
| POST | `/api/operations/payroll-deduction/cases/{case}/reconcile` | Derive expected amount from recorded result; operator cannot force a match by supplying an expected amount |
| POST | `/api/operations/payroll-deduction/cases/{case}/cancellation-release` | Require explicit released status and recorded release reference before closure |
| POST | `/api/partner/financial-intents/{customer}` | Governed active partner source binding, mandatory stable idempotency key, no financial commitment on customer's behalf |
| GET | `/api/partner-financial-intents` | Current customer's referrals |
| POST | `/api/partner-financial-intents/{request}/confirm` | Active/nondeleted Space authority; once-only canonical intent and central audit; changed Space/principles replay rejected |
| POST | `/api/partner-financial-intents/{request}/decline` | Customer-controlled, audited decline without creating a financial intent |

All payroll writes require `Idempotency-Key`; a supplied `X-Correlation-ID` must be a UUID. Keys are bound to command, canonical payload and actor. The Flutter application stores uncertain command identity in its protected session store and reuses it after interrupted responses; changing an uncertain instruction is rejected. A plain HTTP 200 is not confirmation of account closure or financial finality.

The account blocker record contains `code`, `label`, `reference`, `status`, `amount_minor`, `currency`, `due_date` and `provider` with recorded name/phone/email/address and `direct_contact_available`. Missing contacts remain null and must be described as unrecorded. `amount_basis=contract_total_not_current_balance` explicitly identifies a canonical arrangement's contract total rather than an asserted live debt balance. Structured blocker details must not enter redirect URLs.

Closure checks include canonical financing arrangements, compatibility loans, Essentials advances and unresolved durable collection/reversal instructions, recorded personal obligations, peer commitments, savings, protection/claims, investments, capital mandates, payroll reservations and pending financial actions/movement represented in the schema. Rejection creates no misleading pending support/deletion request and does not revoke servicing access. Once resolved, the customer submits a fresh request.

Optional deletion categories are `location_context`, `financial_planning`, `linked_accounts`, `household_and_microbusiness`, and `profile_preferences`. Location deletion is restricted to the requester's optional personal discovery context; it cannot erase another member's data or regulated asset/claim evidence. Required accounting, KYC/AML, credit reporting, settlement, security, consent and audit evidence are retained.

Partner source attribution is the separately reviewed `financial_intent_source_platform` of the partner account. Existing accounts are not backfilled with invented provenance. The existing independent partner approval workflow may configure the permitted source. `ALL_SUITABLE` does not waive valid Sharia approval for Islamic products.

## Post-merge compatibility hardening — 26 September 2026

Treasury cashbook writes remain idempotent and require an explicit `Idempotency-Key` header or the compatibility `idempotency_key` body field. Keyless cashbook writes return HTTP `422`: OpFin does not derive retry identity from transaction content because two legitimate postings can otherwise be identical in every financial field. Exact replay with the same key returns the original entry; reusing that key with a changed canonical instruction returns a conflict.

The opening-balance baseline is fixed and immutable from treasury-account creation; rebaselining requires a separately reviewed accounting correction. Mobile-money durable intent events retain the established `mobile_money.<direction>.requested` audit event alongside the newer intent/provider-response events. Automated tests explicitly disable production funding/disclosure/EFRIS/Cito-certification activation flags.

## Mobile Home aggregate — 27 September 2026

- `GET /api/mobile/home` — authenticated mobile Home snapshot combining Financial Compass, customer credit-profile state, up to five current personal protection summaries and authorised Financial Spaces. Premium-payment and claim history remain on the dedicated protection APIs and are not embedded in Home.
- Optional query: `currency` (three-letter code, default `UGX`).
- Response includes `freshness.observed_at`, `freshness.window_seconds=300` and `freshness.server_authoritative=true`.
- The endpoint returns a private `ETag`. A matching `If-None-Match` returns HTTP `304` with no replacement financial payload.
- This is a read-orchestration endpoint only. It does not create a second financial source of truth; underlying domain services and Space permissions remain authoritative.
