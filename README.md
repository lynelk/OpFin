# OpFin

Status: Public repository/product reference  
Updated: 23 September 2026  
Language: English (United Kingdom)

OpFin is the canonical monorepo for the OpFin financial operating platform. One identity can participate in multiple Financial Spaces while the backend keeps identity, permissions, product eligibility, provider orchestration, financial truth, ledger and reconciliation authoritative.

**Current state:** see [docs/CURRENT_STATE.md](docs/CURRENT_STATE.md).

## Product position

OpFin helps people and organisations understand, manage, plan and improve their financial position. Lending is one capability, not the platform boundary.

Current product layers include:

- Personal, Household, Savings Group and authorised organisation Financial Spaces;
- everyday money, budgets, goals, assets, liabilities and financial-health guidance;
- responsible credit with disclosed offers, affordability, verified-wallet disbursement, repayment and receipts;
- provider-gated savings, investment and protection;
- employer financial-wellbeing capabilities;
- inclusive-finance programme delivery, follow-ups, localisation and privacy-suppressed reporting;
- partner/provider integrations through Cito where configured, with governed direct-provider fallback;
- CPay-preferred money movement;
- commercial/service-economics reporting that remains downstream of customer need, suitability and financial truth.

Stolets remains a separate SME automation and commerce product. Cross-product evidence must use explicit, consented, governed interfaces.

## New-customer journey

The canonical mobile journey is:

`Phone → OTP → names → 6-digit PIN → Home → progressive verification → financial position / eligible service → disclosed action → confirmed outcome`

The Web marketing site and Workspace sign-in are not the preferred new-customer registration path. Legacy password-compatible Web sign-in remains a compatibility/operational surface.

Key rules:

- a second phone is optional;
- KYC and provider results remain attributable;
- unavailable source data stays unavailable;
- profile limits do not multiply across phones or wallets;
- provider acknowledgement is not financial finality;
- programme measurement and protected attributes do not become underwriting inputs;
- app, web, WhatsApp, USSD and assisted channels use server-authoritative state;
- high-impact actions require authenticated confirmation;
- PINs and OTPs are never requested through support conversations.

## Repository layout

| Path | Responsibility |
| --- | --- |
| `apps/api` | Laravel API, worker, scheduler, financial and compliance domain |
| `apps/web` | Next.js marketing, customer, workspace and operational surfaces |
| `apps/client` | Flutter Android/iOS app |
| `packages/contracts` | shared contract/schema conventions |
| `docs` | current product, operational, training and release documentation |
| `infrastructure/railway` | deployment boundaries and release controls |
| `distribution/google-play` | Android store listing/release evidence |

## Current documentation

Start with:

- [Current state](docs/CURRENT_STATE.md)
- [Documentation hub](docs/README.md)
- [Product blueprint](docs/product/OPFIN_PRODUCT_BLUEPRINT.md)
- [Canonical implementation status](docs/product/CANONICAL_IMPLEMENTATION_STATUS.md)
- [Inclusive-finance programme framework](docs/product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md)
- [Partner financial/compliance reporting standard](docs/product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md)
- [Developer start](docs/DEVELOPER_START_HERE.md)
- [User manual](docs/manuals/OPFIN_USER_MANUAL.md)
- [Training manual](docs/manuals/OPFIN_TRAINING_MANUAL.md)
- [Operational manual](docs/manuals/OPFIN_OPERATIONAL_MANUAL.md)
- [UAT manual](docs/manuals/OPFIN_UAT_MANUAL.md)
- [API quick reference](apps/api/docs/api/API_QUICK_REFERENCE.md)
- [Current API endpoints](apps/api/docs/api/current-endpoints.md)

## Website and deployment

The Web service setup endpoint is `https://opfin-web-production.up.railway.app`; the API setup endpoint is `https://opfin-production.up.railway.app`.

At the reviewed 23 September 2026 `main` head, Railway commit statuses report successful API, Web, worker and scheduler deployments. No GitHub Actions workflow run is attached to that exact head, so do not describe that commit as fully release-certified merely because deployment succeeded.

See [deployment documentation](infrastructure/railway/README.md) for the service topology and [current state](docs/CURRENT_STATE.md) for evidence boundaries.

## Local verification

Run the affected project gates or aggregate suite:

`make api-test`, `make web-test`, `make client-test` or `make test`.

Useful discovery commands:

```bash
python3 scripts/search-docs.py "programme"
python3 scripts/search-docs.py "credit reporting" --api
python3 scripts/search-api.py "programme"
python3 scripts/search-api.py "umra"
```

A build or deployment is not proof that provider credentials, legal approvals, store publication, physical-device accessibility or full release-gate evidence exist.

Read `SECURITY.md`, `AGENTS.md` and the relevant current documentation before changing authentication, KYC, credit, provider routing, money movement or customer-facing financial state.

## OpFin Essentials

OpFin Essentials adds purpose-bound financing for verified electricity, water, connectivity, household energy and rent without changing OpFin's wider financial-operating-platform identity. OpFin orchestrates and services the journey; an approved third-party provider is the lender and is named in every offer.

Cito is the mandatory route for gnuGrid services, CPay is the preferred settlement/reconciliation route, and approved platforms such as Stolets can originate Essentials journeys only under customer-controlled Financial Space permissions.

See `docs/product/OPFIN_ESSENTIALS.md`.
