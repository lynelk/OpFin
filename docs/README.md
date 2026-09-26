# OpFin documentation hub

Status: Current documentation and evidence index  
Reviewed: 26 September 2026  
Language: English (United Kingdom)

Start with [current state](CURRENT_STATE.md), the [concept and plan comparison](product/CONCEPT_AND_PLAN_COMPARISON.md), and the [dated delivery evidence](operations/DELIVERY_EVIDENCE_2026-09-24.md). They separate what has been implemented, what was actually tested, unresolved internal defects and external activation.

## Find the right document

| Audience or task | Current reference | Supporting material |
| --- | --- | --- |
| Leadership and product | [Concept and plan comparison](product/CONCEPT_AND_PLAN_COMPARISON.md) | [Blueprint](product/OPFIN_PRODUCT_BLUEPRINT.md), [implementation status](product/CANONICAL_IMPLEMENTATION_STATUS.md), [backlog](product/IMPLEMENTATION_BACKLOG.md) |
| Customer and support | [User manual](manuals/OPFIN_USER_MANUAL.md) | [Current capability supplement](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md), [specialist borrower journey](LAUNCH_CUSTOMER_JOURNEY.md) |
| Trainers | [Training manual](manuals/OPFIN_TRAINING_MANUAL.md) | [Training foundation](TRAINING_AND_USER_GUIDE_FOUNDATION.md), [treasury and Essentials exercises](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) |
| Operations and acceptance | [Operational manual](manuals/OPFIN_OPERATIONAL_MANUAL.md) | [UAT manual](manuals/OPFIN_UAT_MANUAL.md), [delivery evidence](operations/DELIVERY_EVIDENCE_2026-09-24.md) |
| Developers | [Developer start](DEVELOPER_START_HERE.md) | [API index](../apps/api/docs/README.md), [Location Context](architecture/LOCATION_CONTEXT.md), [engineering](../AGENTS.md), [security](../SECURITY.md) |
| API integrators | [API quick reference](../apps/api/docs/api/API_QUICK_REFERENCE.md) | [Current endpoints](../apps/api/docs/api/current-endpoints.md), [new capability contracts](../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Club/treasury administrators | [Treasury specification](product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md) | [Task and acceptance supplement](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md), [club accounting workflows](manuals/CLUB_ACCOUNTING_CLIENT_WORKFLOWS.md) |
| Essentials partners | [Essentials specification](product/OPFIN_ESSENTIALS.md) | [Current control findings](operations/DELIVERY_EVIDENCE_2026-09-24.md), [field-level API guide](../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Programme/MEL partners | [Inclusive-finance framework](product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md) | [Partner reporting standard](product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md) |
| Publication and release owners | [Publication standard](PUBLICATION_STANDARD.md) | [Register](PUBLICATION_REGISTER.md), [deployment guidance](../infrastructure/railway/README.md), [UMRA controls](UMRA_DIGITAL_LENDING_CONTROLS.md) |
| Quality, security and governance | [Integrated management-system policy proposal](governance/INTEGRATED_MANAGEMENT_SYSTEM.md) | [ISO readiness action register](governance/ISO_READINESS_ACTION_REGISTER.md); adoption and effectiveness are not yet evidenced |

## Club client implementation: 26 September 2026

Current branch work adds encrypted saved-request recovery across reloads/devices, retained former-member history navigation and native statement save/share/print. Use the [client workflow guide](manuals/CLUB_ACCOUNTING_CLIENT_WORKFLOWS.md), [recovery/API contract](../apps/api/docs/api/CLUB_CLIENT_RECOVERY.md), and [exact implementation evidence](operations/CLUB_RECOVERY_EXPORT_IMPLEMENTATION_2026-09-26.md).

These references distinguish source implementation from dependency-aware builds, device acceptance, independent approval and deployment. They do not certify the broader Essentials lifecycle or resolve the credential-log incident. Earlier dated evidence retains its original scope.

## What changed in the current review

The achievement comparison retains the original concept's four delivery phases and distinguishes later Financial Space, programme, club-treasury and Essentials decisions. It does not assign a misleading completion percentage or infer business success from a reporting endpoint.

The general manuals distinguish treasury records from full investment-club accounting, identify the current Web import/reconciliation boundary, and qualify Essentials instructions while its financial-control work remains unresolved. For the newer club accounting candidate, use the dated client/API references above rather than treating the earlier treasury-only comparison as its current implementation contract.

Optional Location Context, programme privacy and independent Stolets boundaries remain part of the whole-product story. No new brand, provider activation or original-concept rewrite is implied by this update.

## Discover APIs and documents

From repository root:

```bash
python3 scripts/search-docs.py "treasury"
python3 scripts/search-docs.py "Essentials" --api
python3 scripts/search-docs.py "concept"
python3 scripts/search-docs.py "club recovery"
python3 scripts/search-api.py "essentials"
python3 scripts/search-api.py "statement"
make docs-check
make publication-check
```

With local API dependencies installed, `cd apps/api && php artisan route:list --json` shows registered routes. Registration is not a complete request/response schema, permission certificate or production-availability claim. HTML/CSV exports and framework errors must not be assumed to share a single JSON response envelope.

## Publication and history

Publication classes identify intended audiences, not technical access controls. A file in a public repository is publicly accessible even when labelled controlled internal. Do not place secrets, real customer identity evidence or confidential financial records in any documentation.

Placeholder checks have a limited editorial scope; they do not establish complete semantic, legal, device or API acceptance. Use the actual review evidence and distinguish implemented, deployed, activated and accepted.

Original approved requirements, later decisions, observed code and release evidence are separate. Code proves behaviour, not that the behaviour fulfils the original requirement. Preserve original source terminology in comparisons and record deviations explicitly.

Dated audit, demo, migration and release files remain historical. They are not silently restamped as currently verified. Specialist lending documentation is not the whole-product architecture.

## Continuing maintenance

Each material system/API change must update the relevant current contracts, manual task and UAT record in the same change. The current-state and comparison records must identify the source and acceptance evidence, not simply the latest merge date.

GitHub Actions remains disabled by the owner's instruction. Retain equivalent candidate-specific verification and do not re-enable it or provision additional infrastructure merely to produce checks. Earlier failed runs remain historical; only actual candidate-specific evidence establishes a passing release.

## Lending platform implementation: 25 September 2026

Current implementation: [lender orchestration contract](architecture/LENDER_ORCHESTRATION.md), [executed development roadmap](development/2026-09-25-lending-platform-roadmap.md), [cross-border backlog](development/CROSS_BORDER_ROADMAP.md), and [validation/release evidence](operations/LENDING_DELIVERY_2026-09-25.md). These record platform-admin affiliated lending and configurable distribution; they do not amend the weekly management-review schedule.
