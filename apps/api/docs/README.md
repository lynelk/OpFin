# OpFin API documentation

Status: Source-based developer, integration and operations reference  
Reviewed: 24 September 2026  
Language: English (United Kingdom)

## Start by purpose

| Need | Reference |
| --- | --- |
| Current achievements and restrictions | [Current state](../../../docs/CURRENT_STATE.md), [concept/plan comparison](../../../docs/product/CONCEPT_AND_PLAN_COMPARISON.md) |
| Find an endpoint | [Quick reference](api/API_QUICK_REFERENCE.md), [current endpoints](api/current-endpoints.md) |
| Integrate treasury, Essentials or Location Context | [Current capability contracts](api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Understand client responsibilities | [Frontend/backend contract](api/frontend-backend-contract.md) |
| Understand structure and security | [System overview](architecture/system-overview.md), [API design](architecture/api-design.md), [security/compliance](architecture/security-and-compliance.md) |
| Operate and verify | [Runbook](operations/operational-runbook.md), [readiness](operations/production-readiness-checklist.md), [current UAT](../../../docs/manuals/OPFIN_UAT_MANUAL.md), [delivery evidence](../../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) |
| Regulatory and security controls | [UMRA mapping](../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md), [security standard](../../../SECURITY.md) |

## Source authority and usability

The API owns identity, consent, Financial Space authority, financial/product state, provider coordination, expected accounting, reconciliation and programme/commercial evidence. Clients submit authorised instructions and display returned state; they do not maintain competing prices, credit limits or financial finality.

From repository root:

```bash
python3 scripts/search-api.py "essentials"
python3 scripts/search-api.py "statement"
python3 scripts/search-api.py "financial-spaces"
python3 scripts/search-docs.py "repayment" --api
```

With local API dependencies installed:

```bash
cd apps/api
php artisan route:list --json
```

Registration establishes exact routes, not a complete schema or permission certificate. Read controller validation, service rules and behavioural tests alongside prose. A source implementation that violates an approved requirement is a defect, not authority to rewrite that requirement.

## Recent contract distinctions

Treasury imports create parsed/reviewable source evidence, not confirmed money movement. Issued statements are frozen OpFin reporting snapshots and must preserve currency separation. Historical-date regression failures currently block acceptance of several workflows.

Essentials names the third-party lender and intends settlement to a verified biller/rental beneficiary. Its controller requires a body `idempotency_key` for repayment and returns 201 for accepted repayment records; neither that status nor a success envelope proves collection finality. Nullable Space/wallet inputs require careful policy and authorisation review. Current financial-control findings remain unresolved.

Location is a purpose-specific optional context with server-side provider access. Programme measurements and health outcomes remain non-credit. `programme_partner` and Essentials `partner_api` roles are not interchangeable.

Do not assume all errors use the custom JSON envelope. Framework/proxy failures and HTML/CSV exports require their own handling. Never put live tokens, customer identity records or provider credentials in examples.

## Maintenance and acceptance

API changes update the affected routes/fields, client contract, manual task and UAT case in the same change. Use [the detailed new-capability reference](api/CURRENT_CAPABILITY_CONTRACTS.md) rather than extending ordinary-loan behaviour to Essentials by assumption.

`make docs-check` and `make publication-check` cover their implemented checks only. They do not certify every schema, permission, provider or production journey. GitHub Actions remains disabled by owner instruction; retain equivalent candidate-specific evidence without removing build tests/audits or required independent review.

The latest inspected API build had six failures. Read the dated evidence before publishing a capability as accepted. Historical audit/demo/release documents retain their own dates.
