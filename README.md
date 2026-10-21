# OpFin

**Your next step, clearer.**

**North Star: Every financial life deserves a future worth looking forward to.**

OpFin exists to help everyone understand their finances, make confident decisions and pursue what matters to them, at every age, every stage and every financial scale. **We don't define success for people. We help them make progress towards their own.**

Financial continuity, dignity and personal aspiration are architectural obligations: retain people and attributable financial records across lawful life-stage transitions; preserve customer dignity when a specific service is restricted; and keep customer-owned goals separate from provider/product eligibility and underwriting. See [Financial Life Architecture](docs/architecture/FINANCIAL_LIFE_CONTINUITY_AND_DIGNITY.md) and [consolidated P0–P1 delivery](docs/product/OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md). These documents describe the target direction, not a completed youth-account or advanced-guidance release.

Status: Public repository and product reference  
Reviewed: 21 October 2026  
Language: English (United Kingdom)

OpFin helps people understand and manage their financial lives, build resilience and access appropriate financial services through one continuing, consent-led relationship. Personal money comes first. Households, savings groups, investment clubs, employers, SACCOs and financial partners extend that relationship through governed **Financial Spaces**.

This enhances OpFin's original financial-wellbeing and access concept. It does not replace the brand or reduce the product to lending. Everyday money, planning, responsible borrowing, saving, protection and growth belong to one relationship. Complexity belongs behind the interface; the customer should see their position and the next useful action.

Start with the [updated concept note](docs/product/OPFIN_CONCEPT_NOTE.md), [product evolution and current position](docs/product/PRODUCT_EVOLUTION_2026-09-26.md), [current state](docs/CURRENT_STATE.md) and [API entry point](API.md).

## What remains central

Personal-first financial wellbeing; progressive verification; inclusion across literacy, device and connectivity constraints; consent and privacy; understandable financial choices; and continuing support before, during and after a product transaction. Credit is one capability, not the purpose of every visit.

One person can participate in several Financial Spaces without duplicating identity or exposing private personal records. Essential Individual and Savings Group management remains intended to work in the App without a paid subscription or computer. A missing channel journey is acceptance work, not a reason to weaken that principle.

## What the current source supports

The platform has a continuing Financial Life experience with Today/Home, learning, aspirations, financial position and responsible product journeys. Financial Spaces support person-centred participation in households, groups, investment clubs and organisations. The backend owns the financial records and consent/role boundaries; a working screen is not proof that an external regulated service is activated.

The source also contains lender/product/funding controls, governed partner referrals, payroll case handling, investment-club capital and investment accounting, separate treasury/statement workflows, durable saved-request recovery and native statement export. Essentials provides controlled purpose-bound finance and repayment servicing. Financial Intelligence includes source-referenced analysis and statement review/appeal flows behind role/entitlement and feature gates. SACCO/white-label, partner evidence, asset finance and institutional assurance have their own activation boundaries.

The searchable Developer Centre and documentation-only agent bridge expose registered routes, learning guides and reviewed contracts. Discovery does not give an agent permission to execute a financial transaction. New domain coverage is linked in the [documentation hub](docs/README.md), [integrator guide](apps/api/docs/api/INTEGRATOR_GUIDE.md) and [evidence register](docs/operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md).

Implementations, activated providers, actual money movement, reconciled external records and independent acceptance are distinct states. Unknown financial values must remain unknown.

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

## Provider and ecosystem boundaries

OpFin owns customer identity/consent, Financial Space authority, financial intent, credit/product policy, servicing and its financial evidence. Cito is the governed external-service orchestration boundary where the capability and country permissions have been verified. OpFin must continue to provide internal record-keeping and guidance during external outages, without fabricating verification or automatically repeating an uncertain financial request.

A configured provider, issuer, capital mandate or partner integration does not establish a licence, live custody, funding, statement authenticity or final settlement. Separate evidence and acceptance remain required for each capability. [Lender orchestration](docs/architecture/LENDER_ORCHESTRATION.md), [Essentials scope](docs/product/OPFIN_ESSENTIALS.md), [institutional accounting decision](docs/finance/INSTITUTIONAL_RECONCILIATION_AND_SHARED_EXPOSURE_DECISION.md) and [Cito integration matrix](docs/integrations/CITO_OPFIN_DELIVERY_MATRIX.md) provide the boundaries.

Stolets remains an independently viable SME operations product. Where expressly approved, it may exchange consented financing-intent/evidence signals with OpFin; POS, inventory, procurement and merchant records do not become OpFin's owned operating modules.

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

## Verified source and release position (21 October 2026)

Current documentation baseline: `main` commit `c04d72f894aba163e6c8aaae668d8ad35a7af731`. At review, GitHub combined status reported **successful deployment contexts** for the API, Web, worker and scheduler. These are source/deployment-context signals, not new mobile release, provider, security or end-to-end acceptance evidence.

The previously dated September test failures, PR findings and deployment attempts remain historical in their original records. Do not describe PR #118 as currently open: it and PR #126 were closed without merging, while later implementation changes entered `main` through other reviewed commits/PRs. Newer Financial Life, institutional-control, growth-readiness and dependency work has landed since those historical reports. Read the [21 October evidence register](docs/operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md), not a September snapshot, for the source-to-acceptance distinction.

Outstanding external evidence includes licensed/provider and issuer authenticity, signed institutional balances/agreements, qualified impairment-model acceptance where needed, and security incident remediation. The published source code and successful deployment statuses alone do not close these gates.

## Current documentation

| Need | Reference |
| --- | --- |
| Understand the enduring concept | [Concept note 2.1](docs/product/OPFIN_CONCEPT_NOTE.md) |
| Understand changes and current evidence | [Evolution review](docs/product/PRODUCT_EVOLUTION_2026-09-26.md), [current state](docs/CURRENT_STATE.md) |
| Navigate documents | [Documentation hub](docs/README.md) |
| Compare original requirements | [24 September concept/plan comparison](docs/product/CONCEPT_AND_PLAN_COMPARISON.md), [dated implementation index](docs/product/CANONICAL_IMPLEMENTATION_STATUS.md) |
| Follow product and visual contracts | [Product Blueprint](docs/product/OPFIN_PRODUCT_BLUEPRINT.md), [Brand System v3 release candidate](brand/v3/OPFIN_BRAND_SYSTEM_V3.md) |
| Set up development | [Developer start](docs/DEVELOPER_START_HERE.md), [API entry point](API.md) |
| Learn APIs from first request to AI discovery | [Integrator guide](apps/api/docs/api/INTEGRATOR_GUIDE.md), [API entry](API.md) |
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

GitHub Actions workflows are currently active, including OpFin Monorepo CI, security monitoring and deployment-contract checks. Retain candidate-specific build, test and audit evidence; successful CI does not establish live production acceptance. Do not weaken release checks or provision unapproved infrastructure.

Existing Web/API setup references are `https://opfin-web-production.up.railway.app` and `https://opfin-production.up.railway.app`. They are not proof of current live health or custom-domain cutover. Read [security](SECURITY.md), [engineering](AGENTS.md), [deployment guidance](infrastructure/railway/README.md) and current contracts before changing authentication, providers, credit or money movement.

Enhance working capabilities, preserve customer and financial history, and describe only what the evidence supports.


## Release boundaries and documentation maintenance (21 October 2026)

The source includes club accounting recovery/export and Essentials durable collections, but end-to-end financial acceptance remains evidence-driven. No documentation change activates lender funding, biller settlement, customer refunds, issuer verification, institutional shared origination or an investor custody rail.

See the [current capability supplement](docs/manuals/CURRENT_CAPABILITY_SUPPLEMENT.md), [API integrator guide](apps/api/docs/api/INTEGRATOR_GUIDE.md), [role-based training](docs/manuals/ROLE_BASED_TRAINING_PATHS.md), and [21 October release-evidence register](docs/operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md). Every code/API change must update its contract, task guide and negative UAT case in the same change.
