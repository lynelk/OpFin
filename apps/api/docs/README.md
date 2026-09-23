# OpFin API documentation

Status: Controlled external developer reference  
Updated: 23 September 2026  
Language: English (United Kingdom)  
Scope: `apps/api` and client-facing contracts used by App, Web, WhatsApp, USSD and authorised integrations

## Purpose

This is the publication-ready index for OpFin's API documentation. It is written for developers, integrators, testers and operations teams.

Use the documentation to understand intent, permissions, state transitions and failure handling. Use the registered Laravel route table and automated tests to confirm exact runtime registration.

## Start here

### New developer or integrator

1. `../../../docs/CURRENT_STATE.md`
2. `../../../docs/product/OPFIN_PRODUCT_BLUEPRINT.md`
3. `api/API_QUICK_REFERENCE.md`
4. `api/current-endpoints.md`
5. `api/frontend-backend-contract.md`
6. `architecture/system-overview.md`
7. `architecture/api-design.md`
8. `architecture/security-and-compliance.md`

### Operations, compliance or UAT

Use `operations/operational-runbook.md`, `operations/production-readiness-checklist.md`, `uat/customer-uat-scenarios.md`, `../../../docs/manuals/OPFIN_UAT_MANUAL.md`, `../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md` and `../../../SECURITY.md`.

## Product/API baseline

OpFin is a financial operating platform with server-authoritative state across identity, Financial Spaces, financial management, responsible credit, financial resilience, inclusive-finance programmes, partner/provider orchestration, money movement, ledger/reconciliation and commercial/service-economics evidence.

Canonical new-customer App onboarding is:

`Phone → OTP → names → six-digit PIN → Home → progressive verification → eligible financial journey`

The Web password-compatible sign-in route is retained for existing/authorised access and does not redefine new-customer onboarding.

## API authority

Exact registered routes:

```bash
cd apps/api
php artisan route:list
php artisan route:list --json
```

Search from repository root:

```bash
python3 scripts/search-api.py "financial-spaces"
python3 scripts/search-api.py "programme"
python3 scripts/search-api.py "umra"
python3 scripts/search-docs.py "provider finality" --api
```

A route entry confirms registration. It does not by itself document permissions, request validation, idempotency, provider finality or business semantics.

## Cross-cutting rules

- clients do not calculate their own credit limits, pricing, repayment allocation or financial finality;
- provider acknowledgement is not completed money movement;
- ambiguous primary-provider failure does not silently invoke a direct provider;
- programme/protected attributes remain outside underwriting;
- Financial Space membership does not expose a member's Personal Space;
- unknown commercial/provider values remain unknown rather than becoming zero;
- provider, licence and certification activation must not be inferred from source code.

## Documentation maintenance

Every material API/runtime change updates the affected current API documentation in the same pull request. Historical audit/demo documents remain evidence of earlier states.

Run `make docs-check` and `make publication-check` before publication or external distribution.
