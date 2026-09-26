# Current API endpoints

Status: Current endpoint navigation and contract index  
Reviewed: 25 September 2026  
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

## 2. Account authentication

Read [authentication, registration and closure](domain-endpoints.md#2-account-authentication). New App registration remains phone, OTP, names and six-digit PIN; migrated Web-password compatibility is separate.

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


## Financial Intelligence candidate (26 September 2026)

Read the [Financial Intelligence contract](FINANCIAL_INTELLIGENCE_CONTRACT.md), [source-scope register](../../../../docs/product/FINANCIAL_INTELLIGENCE.md) and [acceptance runbook](../../../../docs/operations/FINANCIAL_INTELLIGENCE_RUNBOOK.md). These are disabled-by-default candidate routes, not a completed or deployed product. Existing domain and Developer Centre contracts above remain in force.

The institutional namespace is `/api/financial-spaces/{space}/intelligence`. It includes role-aware context, source registration, staged JSON/CSV imports, independent publication, source-reconciled portfolio analysis, comparison and sensitivity analysis, assigned cases, expiring access grants, frozen reports and explicit report-sharing mandates. Personal owners receive statement permissions only. Imports and statement evidence do not post payments, alter core accounting or become credit decisions.

The candidate also includes jurisdiction-specific issuer-version administration under `/api/intelligence/admin/issuers` and purpose-bound statement evidence under the scoped namespace. Original PDFs remain quarantined until an accepted scanner/parser pipeline exists. Arithmetic consistency is not issuer authentication. The actual application, database, browser and mobile build gates remain outstanding; discoverable routes or written tests do not establish acceptance.


## Post-merge compatibility hardening — 26 September 2026

Treasury cashbook writes remain idempotent. New clients should send `Idempotency-Key`; established clients that already supply a stable `transaction_reference` may use that reference as the compatibility request identity. Opening-balance baselines are immutable after the first cashbook transaction. Mobile-money durable intent events retain the established `mobile_money.<direction>.requested` audit event alongside the newer intent/provider-response events. Automated tests explicitly disable production funding/disclosure/EFRIS/Cito-certification activation flags.
