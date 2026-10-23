# OpFin documentation hub

Status: Current documentation and evidence index  
Reviewed: 21 October 2026  
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
| Beginner and advanced API integrators | [Practical integrator guide](../apps/api/docs/api/INTEGRATOR_GUIDE.md) | [Developer Centre](../apps/api/docs/api/DEVELOPER_INTERFACE.md), [current routes](../apps/api/docs/api/current-endpoints.md) |
| API and AI integrators | [Developer interface](../apps/api/docs/api/DEVELOPER_INTERFACE.md) | [API/AI platform](developer/API_AND_AGENT_PLATFORM.md), [quick reference](../apps/api/docs/api/API_QUICK_REFERENCE.md), [endpoints](../apps/api/docs/api/current-endpoints.md), [capability contracts](../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Role-based exercises | [Training and UAT paths](manuals/ROLE_BASED_TRAINING_PATHS.md) | [Operations](manuals/OPFIN_OPERATIONAL_MANUAL.md), [UAT](manuals/OPFIN_UAT_MANUAL.md) |
| Club/treasury administrators | [Treasury specification](product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md) | [Task and acceptance supplement](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) |
| Lending and Essentials partners | [Lender orchestration](architecture/LENDER_ORCHESTRATION.md), [institutional ECL provisioning](architecture/INSTITUTIONAL_ECL_PROVISIONING.md), [Essentials specification](product/OPFIN_ESSENTIALS.md) | [Current acceptance position](CURRENT_STATE.md), [field-level API guide](../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Programme/MEL partners | [Inclusive-finance framework](product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md) | [Partner reporting standard](product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md) |
| Brand and publication owners | [Brand System v3 release candidate](../brand/v3/OPFIN_BRAND_SYSTEM_V3.md), [publication standard](PUBLICATION_STANDARD.md) | [Register](PUBLICATION_REGISTER.md), [deployment guidance](../infrastructure/railway/README.md), [UMRA controls](UMRA_DIGITAL_LENDING_CONTROLS.md) |
| Quality, security and governance | [Integrated management-system policy proposal](governance/INTEGRATED_MANAGEMENT_SYSTEM.md) | [ISO readiness register](governance/ISO_READINESS_ACTION_REGISTER.md); adoption/effectiveness are not yet evidenced |

## Stolets merchant-commerce integration

The [Stolets–OpFin unified finance programme](integrations/STOLETS_OPFIN_UNIFIED_FINANCE.md) defines OpFin's financial ownership, an initial **untrusted preflight-only** stock-finance evidence contract, authorisation and provider gates, and all planned cross-platform implementation workstreams. The historical `OpFin-BE` and `OpFin-FE` repositories are archived; all new implementation is in this canonical monorepo and in the independent Stolets repo. No lender, payment or customer finance surface is activated by documentation or validation-only code.

## Latest source and review boundary

The 26 September concept/evolution review is retained as a **dated assessment**, not the latest code/deployment status. Current review baseline: `main` at `c04d72f894aba163e6c8aaae668d8ad35a7af731` on 21 October 2026. Source now includes Financial Life and growth surfaces, club ownership/treasury/recovery, Essentials durable collection, Financial Intelligence and statement review flows, institutional finance-assurance proposals and governed partner/SACCO work.

Read the [current-state index](CURRENT_STATE.md) and [source-to-release register](operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md) before interpreting a milestone as activated. Four service deployment statuses were successful at the baseline, but do not establish full App release, financial settlement, issuer authenticity, certified shared-credit accounting, or closed security remediation.

The continuing vision, original concept, customer financial dignity and independent product boundaries remain in force. Source changes do not silently amend the underlying regulatory or customer-protection requirements.

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

GitHub Actions workflows are currently active. Retain candidate-specific verification without weakening tests or provisioning unapproved infrastructure. Earlier successful runs or deployments do not certify a new candidate.

The [21 October documentation register](operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md) identifies current authorities, owners, known gaps and verification steps. The [lender implementation contract](architecture/LENDER_ORCHESTRATION.md), [executed roadmap](development/2026-09-25-lending-platform-roadmap.md), [cross-border backlog](development/CROSS_BORDER_ROADMAP.md) and [lending evidence](operations/LENDING_DELIVERY_2026-09-25.md) preserve the 25 September lending work. This concept update does not expand weekly management-review scope or cadence.


## Application documentation alignment (21 October 2026)

The current implementation references distinguish the following capabilities and their release boundaries:

- **Club accounting:** encrypted server-persisted instruction/statement requests; exact identity/hash resume after reload/device change; explicit acknowledgement or cancellation only before domain submission; former-member access restricted to their own retained history; and authenticated Web/native mobile statement export. See the [recovery API contract](../apps/api/docs/api/CLUB_CLIENT_RECOVERY.md) and [club implementation evidence](operations/CLUB_RECOVERY_EXPORT_IMPLEMENTATION_2026-09-26.md).
- **Essentials:** durable payload-bound collection identity; original-request reconciliation for uncertain outcomes; one-in-flight capacity controls; reservation separated from due debt; append-only balanced servicing/reversal evidence. See [current API endpoint index](../apps/api/docs/api/current-endpoints.md) and [PR #134](https://github.com/lynelk/OpFin/pull/134).

Neither area is a blanket production acceptance. Full database/migration/concurrency suites, complete Web/App builds, device/accessibility checks and independent financial review remain outstanding. The new Laravel integration tests have not run in the complete application environment. Essentials funding/drawdown, biller settlement, lender release, activation/opening accounting, mandate compatibility, older-record adoption and refunds after service reversal remain incomplete; the separate credential-log incident is unresolved. Do not infer provider activation, live payment, deployment or launch acceptance.
