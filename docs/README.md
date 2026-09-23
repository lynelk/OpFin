# OpFin documentation hub

Updated: 22 September 2026

This is the starting point for OpFin documentation. The goal is simple: a non-developer should be able to understand what the product does, while a developer should be able to find the exact API, control or implementation rule without excavating Git history like an archaeologist with a deadline.

## Start by role

| Audience | Start here | Then read |
| --- | --- | --- |
| Product / leadership | `LAUNCH_CUSTOMER_JOURNEY.md` | `UMRA_DIGITAL_LENDING_CONTROLS.md`, production readiness |
| Customer support / trainers | `TRAINING_AND_USER_GUIDE_FOUNDATION.md` | customer UAT, operational runbook |
| Developer | `DEVELOPER_START_HERE.md` | API docs, architecture, component README |
| API integrator | `../apps/api/docs/api/API_QUICK_REFERENCE.md` | current endpoints, frontend/backend contract |
| Operations / compliance | `../apps/api/docs/operations/operational-runbook.md` | UMRA controls, compliance centre, UAT |
| Security / release | `../SECURITY.md` | production readiness, release gates |

## Current product documents

- `LAUNCH_CUSTOMER_JOURNEY.md` — canonical borrower journey across App, WhatsApp and USSD.
- `UMRA_DIGITAL_LENDING_CONTROLS.md` — implemented digital-lending regulatory controls.
- `DEVELOPER_START_HERE.md` — practical repository/API/development guide.
- `product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md` — canonical inclusion, programme, alternative-data, collateral and OpFin/Stolets boundary contract.
- `product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md` — canonical provider-independence, Stolets Financial Passport, partner reporting and universal service-economics contract.
- `TRAINING_AND_USER_GUIDE_FOUNDATION.md` — source foundation for staff training and customer/user guides.
- `BRAND_IMPLEMENTATION.md` — implementation of OpFin brand tokens and assets.
- `GOOGLE_PLAY_LAUNCH_V1.md` — Android release evidence and store requirements.
- `GOOGLE_PLAY_ACCOUNT_DELETION.md` — deletion path and regulatory retention behaviour.

## API documentation

Canonical API documentation lives in `../apps/api/docs/`.

Key files:

- `api/API_QUICK_REFERENCE.md`
- `api/current-endpoints.md`
- `api/frontend-backend-contract.md`
- `architecture/api-design.md`
- `architecture/security-and-compliance.md`
- `architecture/testing-strategy.md`

The live Laravel route table remains authoritative for exact registered routes:

```bash
cd apps/api
php artisan route:list
php artisan route:list --path=api/credit
php artisan route:list --json
```

## Search documentation

From repository root:

```bash
python3 scripts/search-docs.py "credit reporting"
python3 scripts/search-docs.py "complaint" --api
python3 scripts/search-api.py "umra"
python3 scripts/search-api.py "receipts"
```

The search tools deliberately use the repository itself as the index, so they cannot become stale merely because someone forgot to regenerate a separate search database.

## Current versus historical documents

Documents under dated `audit/`, `demo/`, migration or checkpoint paths are historical evidence unless a current index explicitly says otherwise. They may describe superseded architecture or demo behaviour and must not override:

1. current source code;
2. root `AGENTS.md` / `SECURITY.md`;
3. current docs listed above;
4. current API docs.

Historical documents should retain their original date/context rather than being rewritten to pretend the past never happened.

## Documentation quality rule

Every system/API change must update the relevant current documentation in the same pull request. CI checks documentation drift for route, backend, web and mobile changes. Documentation that materially disagrees with production behaviour is treated as a defect.


## Canonical product baseline — 20 September 2026

The fully enabled product is a financial operating platform, not a lending-only application. Cito is the preferred third-party integration gateway and CPay is the preferred payment route, but OpFin remains independently operable through governed provider adapters. Inclusive-finance programme delivery is governed by `product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md`. For current work, read `product/OPFIN_PRODUCT_BLUEPRINT.md` and `architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md` before the older lending-specific journey documents. Current manuals live under `manuals/` and are authoritative for user, training, operations and UAT guidance.

The current hierarchy is: source code and registered routes → Product Blueprint/domain model → current API references → current manuals → lending/regulatory specialist guides → dated audit/demo/history. A specialist lending guide must not be interpreted as the whole-product architecture.
