# OpFin documentation hub

Status: Publication-ready documentation index  
Updated: 24 September 2026  
Language: English (United Kingdom)

This is the navigation point for current OpFin documentation. Publication class, audience and evidence boundaries are controlled by [PUBLICATION_STANDARD.md](PUBLICATION_STANDARD.md) and [PUBLICATION_REGISTER.md](PUBLICATION_REGISTER.md).

## Start by role

| Audience | Start here | Then read |
| --- | --- | --- |
| Leadership / product | [Current state](CURRENT_STATE.md) | [Product blueprint](product/OPFIN_PRODUCT_BLUEPRINT.md), [implementation status](product/CANONICAL_IMPLEMENTATION_STATUS.md) |
| Customer / support | [User manual](manuals/OPFIN_USER_MANUAL.md) | [Training manual](manuals/OPFIN_TRAINING_MANUAL.md), [launch journey](LAUNCH_CUSTOMER_JOURNEY.md) |
| Operations / compliance | [Operational manual](manuals/OPFIN_OPERATIONAL_MANUAL.md) | [UAT manual](manuals/OPFIN_UAT_MANUAL.md), [UMRA controls](UMRA_DIGITAL_LENDING_CONTROLS.md) |
| Developer | [Developer start](DEVELOPER_START_HERE.md) | [API docs](../apps/api/docs/README.md), [Location Context](architecture/LOCATION_CONTEXT.md), `AGENTS.md`, `SECURITY.md` |
| API integrator | [API quick reference](../apps/api/docs/api/API_QUICK_REFERENCE.md) | [Current endpoints](../apps/api/docs/api/current-endpoints.md) |
| Programme / MEL partner | [Inclusive-finance framework](product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md) | [Partner reporting standard](product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md) |
| Release owner | [Current state](CURRENT_STATE.md) | [Publication register](PUBLICATION_REGISTER.md), [Railway topology](../infrastructure/railway/README.md), store/release documentation |

## Publication control

Use the publication register before sharing a repository document externally.

- **Public** documents may be shared externally once any stated legal/compliance gates are complete.
- **Controlled external** documents are suitable for a defined professional audience.
- **Controlled internal** documents are polished operating/engineering artefacts, not unrestricted public copy.
- **Historical evidence** remains dated evidence and must not be presented as current policy.

Run:

```bash
make publication-check
```

The check rejects unresolved editorial markers in the current Public and Controlled external set. Release worksheets with deliberately unresolved factual inputs are not disguised as finished public copy.

## Current product baseline

OpFin is a financial operating platform, not a lending-only application. Current implemented domains include Financial Spaces, everyday financial management, responsible credit, financial health, provider-gated savings/investment/protection, optional Location Context, employer capabilities, inclusive-finance programmes, partner/MEL reporting, programme follow-ups/localisation, governed provider evidence, and commercial/service-economics reporting.

Cito is the preferred external-integration gateway where configured. CPay is the preferred production money-movement route. Neither statement means every underlying provider is automatically active.

Stolets remains a separate SME operating product. Any OpFin use of Stolets evidence requires a specific consented and governed interface.

## Website documentation boundary

The public Web homepage describes the broad platform proposition. It is not the source of truth for provider activation, credit policy, regulatory status, exact API behaviour, app-store publication or release certification.

The homepage's Web sign-in is a Workspace/customer compatibility route. The canonical new-customer onboarding journey remains phone → OTP → names → six-digit PIN in the mobile experience.

## API documentation

Canonical API documentation lives under `../apps/api/docs/`.

Use the registered Laravel route table for exact registered routes:

```bash
cd apps/api
php artisan route:list
php artisan route:list --json
```

Use prose contracts for purpose, validation, permissions, failure semantics and safe operation. A registered route alone is not a complete API contract.

## Search

From repository root:

```bash
python3 scripts/search-docs.py "programme"
python3 scripts/search-docs.py "commercial performance"
python3 scripts/search-docs.py "credit reporting" --api
python3 scripts/search-api.py "programme"
python3 scripts/search-api.py "receipts"
```

## Current versus historical

Apply this hierarchy:

1. source code, migrations, registered routes and automated tests;
2. Product Blueprint/domain models;
3. [Current state](CURRENT_STATE.md) and canonical implementation status;
4. current API documentation;
5. current manuals;
6. specialist regulatory/release/deployment guides;
7. dated audit/demo/migration/checkpoint evidence.

Historical evidence should retain its original date and context.

## Documentation quality rule

Every material system/API/customer-workflow change must update the relevant current documentation in the same change. Unknown provider, commercial or release facts remain unknown. Missing values are not zero; unavailable evidence is not success; deployed source is not automatically release-certified.

The manual set and website copy were reconciled against `main` on 23 September 2026. See [current state](CURRENT_STATE.md) for the reviewed evidence boundary.

## OpFin Essentials

- `product/OPFIN_ESSENTIALS.md` — controlled product, utility/rent, third-party-lender orchestration, embedded-platform and operating contract.
- Essentials extends OpFin; it does not redefine OpFin as a lending-only application or make OpFin the primary lender.
