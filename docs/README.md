# OpFin documentation hub

Review date: 18 September 2026. This hub separates product explanations, implementation references, training sources and release evidence.

## Choose your starting point

| Audience | Start here | Next |
| --- | --- | --- |
| Customers, support and trainers | [Training foundation](TRAINING_AND_USER_GUIDE_FOUNDATION.md) | [Learning paths](manuals/TRAINING_PATHS.md), [glossary](GLOSSARY.md) |
| Product and leadership | [Launch customer journey](LAUNCH_CUSTOMER_JOURNEY.md) | [Digital-lending controls](UMRA_DIGITAL_LENDING_CONTROLS.md) |
| New developers | [Developer start here](DEVELOPER_START_HERE.md) | [Contribution workflow](../CONTRIBUTING.md), [engineering rules](../AGENTS.md) |
| API integrators | [Integrator guide](../apps/api/docs/api/INTEGRATOR_GUIDE.md) | [Task-oriented API index](../apps/api/docs/api/API_QUICK_REFERENCE.md), [current endpoints](../apps/api/docs/api/current-endpoints.md) |
| Operations and compliance | [Operational runbook](../apps/api/docs/operations/operational-runbook.md) | [Readiness checklist](../apps/api/docs/operations/production-readiness-checklist.md), [customer UAT](../apps/api/docs/uat/customer-uat-scenarios.md) |
| Security and release owners | [Security requirements](../SECURITY.md) | [Documentation maintenance](DOCUMENTATION_MAINTENANCE.md), [Android launch evidence](GOOGLE_PLAY_LAUNCH_V1.md) |

## Search and browse

From repository root:

```bash
make docs-search QUERY="credit reporting"
python3 scripts/search-docs.py "complaint" --api
python3 scripts/search-docs.py "migration" --history
make api-search QUERY="receipts"
make docs-build
make docs-serve
```

`make docs-build` creates the offline, browser-searchable portal at `.build/docs/index.html`. `make docs-serve` serves only that generated directory on `127.0.0.1:8008`, not the repository or its environment files. Search excludes recognised historical evidence by default. The portal is a source viewer, not a customer-facing help centre or an API execution console.

## Product and channel references

Use the [launch journey](LAUNCH_CUSTOMER_JOURNEY.md), [training foundation](TRAINING_AND_USER_GUIDE_FOUNDATION.md), [brand implementation](BRAND_IMPLEMENTATION.md), [account-deletion guide](GOOGLE_PLAY_ACCOUNT_DELETION.md) and [dormant community-finance design](COMMUNITY_FINANCE_AND_SACCO_CORE.md). A design document or registered endpoint is not proof that a capability is enabled.

## API and implementation references

The [API documentation index](../apps/api/docs/README.md) connects architecture, endpoints, client contracts, integrations and operations. The [shared-contracts directory](../packages/contracts/README.md) explains how generated discovery artefacts are produced without creating a second contract authority.

The registered Laravel route table supplies exact route metadata. Controllers, validation, middleware and behavioural tests establish meaning. The generated reference reports missing narrative coverage rather than inventing request or response schemas.

## Maintaining the source

Follow [documentation maintenance](DOCUMENTATION_MAINTENANCE.md). Relevant docs and tests change in the same pull request as the implementation. Training content derives from these sources and the verified application build, using [the task template](manuals/TASK_GUIDE_TEMPLATE.md).

Historical audit, demo, migration, checkpoint and dated release records remain evidence of their original context. They must not override current approved requirements or be presented as current operating instructions. Conflicts between requirements and implementation are defects to resolve, not reasons to silently alter a policy.
