# OpFin documentation hub

Status: Current documentation and evidence index  
Reviewed: 26 September 2026  
Language: English (United Kingdom)

Start with the [updated concept note](product/OPFIN_CONCEPT_NOTE.md), [product evolution and current position](product/PRODUCT_EVOLUTION_2026-09-26.md), and [current state](CURRENT_STATE.md). They preserve the original financial-wellbeing ethos while distinguishing the continuing product ambition, source implementation, recorded tests, deployment, acceptance and external activation.

## Find the right document

| Audience or task | Current reference | Supporting material |
| --- | --- | --- |
| Leadership, product and communications | [Concept note 2.1](product/OPFIN_CONCEPT_NOTE.md) | [Evolution review](product/PRODUCT_EVOLUTION_2026-09-26.md), [Blueprint](product/OPFIN_PRODUCT_BLUEPRINT.md), [backlog](product/IMPLEMENTATION_BACKLOG.md) |
| Original-plan comparison | [24 September comparison](product/CONCEPT_AND_PLAN_COMPARISON.md) | [Dated implementation index](product/CANONICAL_IMPLEMENTATION_STATUS.md), [current state](CURRENT_STATE.md) |
| Customer and support | [User manual](manuals/OPFIN_USER_MANUAL.md) | [Capability supplement](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md), [specialist borrower journey](LAUNCH_CUSTOMER_JOURNEY.md) |
| Trainers | [Training manual](manuals/OPFIN_TRAINING_MANUAL.md) | [Training foundation](TRAINING_AND_USER_GUIDE_FOUNDATION.md), [treasury and Essentials exercises](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) |
| Operations and acceptance | [Operational manual](manuals/OPFIN_OPERATIONAL_MANUAL.md) | [UAT](manuals/OPFIN_UAT_MANUAL.md), [current state](CURRENT_STATE.md), [25 September lending evidence](operations/LENDING_DELIVERY_2026-09-25.md) |
| Developers | [Developer start](DEVELOPER_START_HERE.md), [API entry point](../API.md) | [API index](../apps/api/docs/README.md), [Location Context](architecture/LOCATION_CONTEXT.md), [engineering](../AGENTS.md), [security](../SECURITY.md) |
| API and AI integrators | [Developer interface](../apps/api/docs/api/DEVELOPER_INTERFACE.md) | [API/AI platform](developer/API_AND_AGENT_PLATFORM.md), [quick reference](../apps/api/docs/api/API_QUICK_REFERENCE.md), [endpoints](../apps/api/docs/api/current-endpoints.md), [capability contracts](../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Club/treasury administrators | [Treasury specification](product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md) | [Task and acceptance supplement](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) |
| Lending and Essentials partners | [Lender orchestration](architecture/LENDER_ORCHESTRATION.md), [Essentials specification](product/OPFIN_ESSENTIALS.md) | [Current acceptance position](CURRENT_STATE.md), [field-level API guide](../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Programme/MEL partners | [Inclusive-finance framework](product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md) | [Partner reporting standard](product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md) |
| Brand and publication owners | [Brand System v3 release candidate](../brand/v3/OPFIN_BRAND_SYSTEM_V3.md), [publication standard](PUBLICATION_STANDARD.md) | [Register](PUBLICATION_REGISTER.md), [deployment guidance](../infrastructure/railway/README.md), [UMRA controls](UMRA_DIGITAL_LENDING_CONTROLS.md) |
| Quality, security and governance | [Integrated management-system policy proposal](governance/INTEGRATED_MANAGEMENT_SYSTEM.md) | [ISO readiness register](governance/ISO_READINESS_ACTION_REGISTER.md); adoption/effectiveness are not yet evidenced |

## What this concept review changes

The concept's original 32 subject areas, vision, mission, four delivery phases and financial-progression philosophy are retained. The narrative incorporates Financial Spaces, richer everyday-money records, programme delivery, club treasury, Essentials, explicit lender participation, NIN evidence reuse and developer discovery as enhancements to the existing relationship.

The dated evolution record explains the latest successful API/Web/worker/scheduler deployment statuses and separately identifies still-unmerged financial controls. It recognises PR #118's newer branch-specific PostgreSQL verification without claiming the branch is already main. It does not assign an unsupported completion percentage or infer impact/profitability from reporting endpoints.

The original 24 September [comparison](product/CONCEPT_AND_PLAN_COMPARISON.md) and [delivery evidence](operations/DELIVERY_EVIDENCE_2026-09-24.md) remain dated baseline records. Their historical failures and limitations must not be silently overwritten or treated as the freshest universal state.

## Discover APIs and documents

From repository root:

```bash
python3 scripts/search-docs.py "treasury"
python3 scripts/search-docs.py "Essentials" --api
python3 scripts/search-docs.py "concept"
python3 scripts/search-api.py "essentials"
python3 scripts/search-api.py "statement"
make docs-check
make publication-check
make api-docs-check
make agent-docs-test
```

With local API dependencies installed, `cd apps/api && php artisan route:list --json` shows registered routes. The merged Developer Centre adds source-linked search and role-filtered catalogue inspection. Registration is not complete schema coverage, permission certification or proof of production availability. The strict `api:catalogue --check --require-complete` gate must continue to reveal missing reviewed native contracts.

HTML/CSV exports and framework errors must not be assumed to share one JSON envelope. The local MCP bridge is documentation-only, not a financial command interface.

## Publication, authority and history

Publication classes identify intended audiences, not technical access controls. A public repository does not protect a file labelled internal. Never place secrets, real customer identity evidence or confidential financial records in documentation.

Placeholder checks have limited editorial scope; they do not establish semantic, legal, device or API acceptance. Original requirements, later decisions, observed code and release evidence are different authorities. Code proves behaviour, not that the behaviour fulfils the requirement.

Navigation, employer-data boundaries, provider exceptions, lender roles and mobile completeness contain documented differences discussed in the [evolution review](product/PRODUCT_EVOLUTION_2026-09-26.md). That review does not silently adopt a new policy to reconcile them.

Dated audit, demo, migration and release files remain historical. Specialist lending documentation is not the whole-product architecture. The [user](manuals/OPFIN_USER_MANUAL.md), [training](manuals/OPFIN_TRAINING_MANUAL.md), [operational](manuals/OPFIN_OPERATIONAL_MANUAL.md) and [UAT](manuals/OPFIN_UAT_MANUAL.md) manuals continue to be read with the capability supplement and the current-state index.

## Continuing maintenance

Every material system/API change must update the affected current contracts, manual tasks and UAT evidence in the same change. The concept records stable purpose; the current-state index records current evidence; the evolution record records a dated review.

GitHub Actions remains deferred by owner instruction. Retain equivalent candidate-specific verification without enabling Actions, weakening tests or provisioning additional infrastructure. Earlier successful runs or deployments do not certify a new candidate.

The [lender implementation contract](architecture/LENDER_ORCHESTRATION.md), [executed roadmap](development/2026-09-25-lending-platform-roadmap.md), [cross-border backlog](development/CROSS_BORDER_ROADMAP.md) and [lending evidence](operations/LENDING_DELIVERY_2026-09-25.md) preserve the 25 September lending work. This concept update does not expand weekly management-review scope or cadence.
