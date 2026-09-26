# OpFin

**Your next step, clearer.**

Status: Public repository and product reference  
Updated: 26 September 2026  
Language: English (United Kingdom)

OpFin helps people understand and manage their financial lives, build resilience and access appropriate financial services through one continuing, consent-led relationship. Personal money comes first. Households, savings groups, investment clubs, employers, SACCOs and financial partners extend that relationship through governed **Financial Spaces**.

This enhances OpFin's original financial-wellbeing and access concept. It does not replace the brand or reduce the product to lending. Everyday money, planning, responsible borrowing, saving, protection and growth belong to one relationship. Complexity belongs behind the interface; the customer should see their position and the next useful action.

Start with the [updated concept note](docs/product/OPFIN_CONCEPT_NOTE.md), [product evolution and current position](docs/product/PRODUCT_EVOLUTION_2026-09-26.md), [current state](docs/CURRENT_STATE.md) and [API entry point](API.md).

## What remains central

Personal-first financial wellbeing; progressive verification; inclusion across literacy, device and connectivity constraints; consent and privacy; understandable financial choices; and continuing support before, during and after a product transaction. Credit is one capability, not the purpose of every visit.

One person can participate in several Financial Spaces without duplicating identity or exposing private personal records. Essential Individual and Savings Group management remains intended to work in the App without a paid subscription or computer. A missing channel journey is acceptance work, not a reason to weaken that principle.

## What has been enhanced

The current source connects identity/consent, Financial Spaces, everyday money and Compass surfaces, governed credit and servicing, provider-gated Save/Protect/Grow foundations, employer/community capabilities, inclusion programmes, commercial/service-economics reporting, investment-club treasury/statements and Essentials bill/rent financing.

The latest lender-orchestration work adds explicit lender identity, independent and affiliated participation, funding ownership and country/product/channel distribution. The selected institution is the lender of record. Core Synergies affiliated participation must satisfy the same authority, funding and customer-protection controls; an affiliation setting is not a licence or funded balance.

The treasury-baseline repair and governed fresh NIN receipt reuse are recorded as merged. The merged Developer Centre extends source-linked discovery without enabling AI financial execution. These advances preserve earlier useful capabilities rather than authorising an indiscriminate rewrite.

Club treasury is not complete member-capital/NAV/distribution accounting. Programme measurement is not underwriting. A reporting dashboard is not proof of profitability or causal impact. Implemented source, deployed source, provider activation and accepted customer delivery remain distinct.

## Customer relationship

`Phone → OTP → names → six-digit PIN → Home → progressive verification → financial position / eligible service → disclosed action → confirmed outcome`

The continuing experience follows `Observe → Understand → Plan → Act → Monitor → Adjust`.

Personal is the default context. A second phone is optional. Existing Web password-compatible sign-in is a compatibility surface, not the preferred new App registration journey. KYC/scoring sources remain attributable and unavailable information is not fabricated. Limits must not multiply across phones, wallets or lender relationships. PINs and OTPs are never requested in support conversations.

Fresh Cito NIN evidence can be reused only under activated policy, purpose, context, expiry and consent controls. It is not a direct NIRA contract, universal identity database or substitute for complete KYC.

App, Web, Workspace, Partner, WhatsApp, USSD and assisted channels use server-authoritative identity and financial state. High-impact commitments require authenticated confirmation. Offline-aware records and provider acknowledgements are not final payment confirmation.

## Repository layout and lineage

`lynelk/OpFin` is the canonical monorepo. OP44/Base44 and the earlier `OpFin-FE`/`OpFin-BE` repositories remain implementation history; their old CI results do not certify the current release.

| Path | Responsibility |
| --- | --- |
| `apps/api` | Laravel API, financial/compliance domain, worker and scheduler responsibilities |
| `apps/web` | Next.js public, customer, Workspace and operational surfaces |
| `apps/client` | Flutter Android/iOS App |
| `packages/contracts` | Shared API/schema conventions |
| `tools/opfin-mcp` | Read-only AI documentation bridge |
| `docs` | Concept, product contracts, manuals, operations and evidence |
| `brand` | Existing OpFin identity system and controlled assets |
| `infrastructure/railway` | Existing service boundaries and release controls |
| `distribution/google-play` | Controlled Android store/release material |

## Partner boundaries

OpFin owns customer/product state, financial intent, experience, intelligence, servicing and its financial evidence. Cito is the preferred third-party gateway and CPay the preferred payment route. Explicitly configured, production-certified direct-provider exceptions remain governed; an ambiguous primary request must be reconciled before switching routes. The specific requirement to consume gnuGrid services through Cito remains in force.

Essentials provides purpose-bound financing for verified electricity, water, connectivity, household energy and rent. It extends financial wellbeing without replacing planning, savings, protection or ordinary responsible-credit journeys. Every activated offer identifies its lender and satisfies the relevant authority, funding and acceptance controls.

Stolets remains a separate SME operating product. OpFin may consume approved, consented signals or refer customers, but does not absorb POS, inventory, purchasing or merchant operations.

See the [lender contract](docs/architecture/LENDER_ORCHESTRATION.md), [Essentials specification](docs/product/OPFIN_ESSENTIALS.md) and [current state](docs/CURRENT_STATE.md). External credentials are not the only remaining work: internal controls and complete acceptance evidence also matter.

## Developer and AI access

The merged source-linked Developer Centre is `/developers` on the API origin when the relevant release is deployed. It provides learning tracks, search, role-filtered catalogue/contract inspection, source fingerprints, reviewed OpenAPI export and explicit coverage gaps.

The [local MCP bridge](tools/opfin-mcp/README.md) searches and reads documentation only. It cannot approve credit, call arbitrary native endpoints, move money or alter accounting. Future native AI-assisted actions still require the same domain authority, consent and financial controls as other clients.

```bash
make api-docs
make api-docs-check
make agent-docs-test
cd apps/api && php artisan api:catalogue --check --require-complete
```

The strict completeness check must fail while native operations lack reviewed machine-readable contracts. Route discovery is not complete API specification. See [Developer interface](apps/api/docs/api/DEVELOPER_INTERFACE.md) and [API/AI platform](docs/developer/API_AND_AGENT_PLATFORM.md).

## Where the platform currently stands

The final connected status check for reviewed `main` commit `b1686989a317619562a8592728a310fbcb8f9113` reported successful API, Web, worker and scheduler deployments. The API success recorded at 22:15:09 UTC on 25 September follows an earlier failed attempt; historical failures remain in their dated records rather than being presented as the latest status.

The PR #124 merge record reports 332 API tests and 2,230 assertions, dependency audit, catalogue checks and assets passing for its integrated candidate. Known financial-control PRs #113 and #118 remain open at this review. PR #118 now records scoped SQLite/PostgreSQL 18 verification, but remains unmerged pending independent approval. The [evolution record](docs/product/PRODUCT_EVOLUTION_2026-09-26.md) keeps that branch evidence separate from main.

Deployment statuses and recorded test results are not fresh live-health checks, universal financial acceptance, provider activation, store publication, ISO certification or business outcomes. The separately recorded credential-log incident remains controlled security work.

## Current documentation

| Need | Reference |
| --- | --- |
| Understand the enduring concept | [Concept note 2.1](docs/product/OPFIN_CONCEPT_NOTE.md) |
| Understand changes and current evidence | [Evolution review](docs/product/PRODUCT_EVOLUTION_2026-09-26.md), [current state](docs/CURRENT_STATE.md) |
| Navigate documents | [Documentation hub](docs/README.md) |
| Compare original requirements | [24 September concept/plan comparison](docs/product/CONCEPT_AND_PLAN_COMPARISON.md), [dated implementation index](docs/product/CANONICAL_IMPLEMENTATION_STATUS.md) |
| Follow product and visual contracts | [Product Blueprint](docs/product/OPFIN_PRODUCT_BLUEPRINT.md), [Brand System v3 release candidate](brand/v3/OPFIN_BRAND_SYSTEM_V3.md) |
| Set up development | [Developer start](docs/DEVELOPER_START_HERE.md), [API entry point](API.md) |
| Find APIs | [Developer interface](apps/api/docs/api/DEVELOPER_INTERFACE.md), [quick reference](apps/api/docs/api/API_QUICK_REFERENCE.md), [endpoints](apps/api/docs/api/current-endpoints.md), [capability contracts](apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Use and train | [User](docs/manuals/OPFIN_USER_MANUAL.md), [training](docs/manuals/OPFIN_TRAINING_MANUAL.md), [capability supplement](docs/manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) |
| Operate and accept | [Operations](docs/manuals/OPFIN_OPERATIONAL_MANUAL.md), [UAT](docs/manuals/OPFIN_UAT_MANUAL.md), [lending delivery evidence](docs/operations/LENDING_DELIVERY_2026-09-25.md) |
| Govern programmes and partners | [Programme framework](docs/product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md), [reporting standard](docs/product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md) |
| Track management-system readiness | [Policy proposal](docs/governance/INTEGRATED_MANAGEMENT_SYSTEM.md), [ISO action register](docs/governance/ISO_READINESS_ACTION_REGISTER.md) |

## Verification and production

Use `make api-test`, `make web-test`, `make client-test` or `make test` for affected project gates, and `make docs-check` / `make publication-check` for their documented scope. Editorial checks do not substitute for semantic, legal, device or production acceptance.

```bash
python3 scripts/search-docs.py "treasury"
python3 scripts/search-docs.py "Essentials" --api
python3 scripts/search-api.py "essentials"
python3 scripts/search-api.py "statement"
```

GitHub Actions remains deferred under the owner's instruction. Retain equivalent candidate-specific validation. Do not re-enable Actions, disable tests/audits or provision new infrastructure to manufacture release evidence.

Existing Web/API setup references are `https://opfin-web-production.up.railway.app` and `https://opfin-production.up.railway.app`. They are not proof of current live health or custom-domain cutover. Read [security](SECURITY.md), [engineering](AGENTS.md), [deployment guidance](infrastructure/railway/README.md) and current contracts before changing authentication, providers, credit or money movement.

Enhance working capabilities, preserve customer and financial history, and describe only what the evidence supports.
