# OpFin

Status: Public repository and product reference  
Updated: 25 September 2026  
Language: English (United Kingdom)

OpFin is the canonical monorepo for the financial operating platform. One identity can participate in several Financial Spaces while the API owns permissions, product eligibility, provider orchestration, financial state, ledger and reconciliation.

Start with the [API and developer entry point](API.md), [current state](docs/CURRENT_STATE.md) and [achievements against the concept and plan](docs/product/CONCEPT_AND_PLAN_COMPARISON.md). Source implementation, tested behaviour, provider activation and financial acceptance remain separate. The treasury baseline repair and governed NIN evidence reuse are merged; wider Essentials, club-accounting and credential-security acceptance remains incomplete.

## Product position

OpFin helps people and organisations understand, manage, plan and improve their financial position. Lending is one capability, not the product boundary.

Current source includes Personal, Household, Savings Group and authorised organisation Spaces; everyday money, budgets, goals, assets, liabilities and health guidance; governed credit, offers, repayment and receipts; provider-gated savings, investment and protection; employer wellbeing; inclusive-finance programmes, follow-ups, localisation and suppressed reporting; commercial/service-economics reporting; investment-club treasury/statements; and Essentials bill/rent lender orchestration.

These are source capabilities, not a claim that every service is activated or every journey accepted. Club treasury is not complete member-capital/NAV/distribution accounting. Programme measurement is not underwriting, and dashboards are not proof of profitability or impact.

Stolets remains a separate SME operating product. Any cross-product evidence requires an explicit, consented and governed interface.

## Developer and AI access

The source-linked Developer Centre is `/developers` on the API origin once this change is deployed. It provides novice, application-developer and advanced/AI learning tracks; searchable route discovery; authenticated role-filtered contract inspection; reviewed OpenAPI; source fingerprints; and explicit coverage gaps.

The [local MCP bridge](tools/opfin-mcp/README.md) exposes documentation search and reading only. It cannot call arbitrary native endpoints, approve credit, move money or alter accounting. Native AI-assisted actions still use the same domain authorisation, consent, policy, approval and financial controls as other clients.

```bash
make api-docs
make api-docs-check
make agent-docs-test
cd apps/api && php artisan api:catalogue --check --require-complete
```

The strict command must fail while registered operations lack complete reviewed contracts. Complete route discovery is not a complete business/API specification. See [Developer interface](apps/api/docs/api/DEVELOPER_INTERFACE.md) and [API/AI platform](docs/developer/API_AND_AGENT_PLATFORM.md).

## New-customer journey

`Phone → OTP → names → six-digit PIN → Home → progressive verification → financial position / eligible service → disclosed action → confirmed outcome`

Existing Web password-compatible sign-in is an access/compatibility surface, not the preferred new App registration journey. A second phone is optional. KYC and scoring sources remain attributable; unavailable information is not fabricated. Limits must not multiply across phones or wallets. PINs and OTPs are not requested in support conversations.

The API remains authoritative across App, Web, WhatsApp, USSD and assisted channels. High-impact commitments require authenticated confirmation. A provider acknowledgement is not financial finality.

## Repository layout

| Path | Responsibility |
| --- | --- |
| `apps/api` | Laravel API, worker, scheduler, financial and compliance domain |
| `apps/web` | Next.js marketing, customer, Workspace and operational surfaces |
| `apps/client` | Flutter Android/iOS App |
| `packages/contracts` | Shared API/schema conventions |
| `tools/opfin-mcp` | Read-only AI documentation bridge |
| `docs` | Product, comparison, manuals, operations and evidence |
| `infrastructure/railway` | Existing service boundaries and release controls |
| `distribution/google-play` | Controlled Android store/release material |

## Current documentation

| Need | Reference |
| --- | --- |
| Navigate documents | [Documentation hub](docs/README.md) |
| Understand achievements and gaps | [Concept and plan comparison](docs/product/CONCEPT_AND_PLAN_COMPARISON.md), [implementation status](docs/product/CANONICAL_IMPLEMENTATION_STATUS.md) |
| Understand the intended platform | [Product Blueprint](docs/product/OPFIN_PRODUCT_BLUEPRINT.md) |
| Follow approved visual direction | [Brand System v3 release candidate](brand/v3/OPFIN_BRAND_SYSTEM_V3.md) |
| Set up development | [Developer start](docs/DEVELOPER_START_HERE.md), [API entry point](API.md) |
| Find APIs | [Developer interface](apps/api/docs/api/DEVELOPER_INTERFACE.md), [quick reference](apps/api/docs/api/API_QUICK_REFERENCE.md), [current endpoints](apps/api/docs/api/current-endpoints.md), [new capability contracts](apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Use and train | [User](docs/manuals/OPFIN_USER_MANUAL.md), [training](docs/manuals/OPFIN_TRAINING_MANUAL.md), [current capability supplement](docs/manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) |
| Operate and accept | [Operational manual](docs/manuals/OPFIN_OPERATIONAL_MANUAL.md), [UAT](docs/manuals/OPFIN_UAT_MANUAL.md), [dated delivery evidence](docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) |
| Govern programmes and partners | [Programme framework](docs/product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md), [partner reporting standard](docs/product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md) |

## Essentials and provider boundaries

Essentials adds purpose-bound financing for verified electricity, water, connectivity, household energy and rent. The named participating third party supplies credit; OpFin orchestrates the customer journey and servicing. Cito is the required route for gnuGrid services under this capability, and CPay is the preferred settlement/reconciliation route.

Partner platforms such as Stolets require customer-controlled permissions and exact Financial Space authority. Current review findings on accounting, permissions, deletion, concurrency and pending exposure remain acceptance blockers. See the [Essentials specification](docs/product/OPFIN_ESSENTIALS.md) together with current review evidence; do not describe external credentials as the only remaining work.

Approved fresh NIN receipts can avoid repeated Cito NIN checks after genuine policy settings and consent activate reuse. That feature is not a direct NIRA contract, a universal identity database or a substitute for complete KYC. Provider outages must not disable unrelated internal work or trigger an unsafe duplicate payment through a different provider.

## Verification and production

Run affected project gates using `make api-test`, `make web-test`, `make client-test` or `make test`. Use `make docs-check`, `make publication-check` and the catalogue checks for their documented scope, not as substitutes for semantic, legal or production acceptance.

```bash
python3 scripts/search-docs.py "treasury"
python3 scripts/search-docs.py "Essentials" --api
python3 scripts/search-api.py "essentials"
python3 scripts/search-api.py "statement"
```

GitHub Actions remains deferred under the owner's instruction. Equivalent candidate-specific validation must be retained. Do not disable tests/audits, re-enable Actions or provision additional infrastructure to manufacture release evidence.

The existing Web/API setup addresses are `https://opfin-web-production.up.railway.app` and `https://opfin-production.up.railway.app`. They are not proof of custom-domain cutover or health. Historical 24 September API failures were repaired by PR #116; PR #117 subsequently passed 283 API tests and added governed identity evidence. Use exact-candidate deployment records for the current release rather than treating an earlier dated report as a live status page.

The separately recorded credential-log incident requires controlled remediation and verified credential handling. It is not closed by a documentation or API-discovery feature.

Read [security](SECURITY.md), [engineering rules](AGENTS.md), [deployment guidance](infrastructure/railway/README.md) and the relevant current contracts before changing authentication, providers, credit, money movement or customer financial state. Historical records retain their original dates; approved requirements are not silently rewritten to match defects.
