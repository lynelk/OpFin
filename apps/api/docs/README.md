# OpFin API documentation

Review date: 18 September 2026. Audience: developers, integrators, operations, support and testers.

## Start here

Read [the repository overview](../../../README.md), [developer setup](../../../docs/DEVELOPER_START_HERE.md) and [the launch journey](../../../docs/LAUNCH_CUSTOMER_JOURNEY.md). The API owns authoritative identity, credit, money movement, ledger and reconciliation state. Clients consume that state; they do not independently calculate financial outcomes.

## Find the contract

| Need | Reference |
| --- | --- |
| Understand API use without prior backend knowledge | [Integrator guide](api/INTEGRATOR_GUIDE.md) |
| Find an operation by customer or staff task | [API quick reference](api/API_QUICK_REFERENCE.md) |
| Read curated payloads and behaviour | [Current endpoints](api/current-endpoints.md) |
| Understand client responsibilities | [Frontend/backend contract](api/frontend-backend-contract.md) |
| Understand structure and constraints | [System overview](architecture/system-overview.md), [API design](architecture/api-design.md) |
| Review security and testing | [Security and compliance](architecture/security-and-compliance.md), [testing strategy](architecture/testing-strategy.md) |
| Operate and validate the service | [Operational runbook](operations/operational-runbook.md), [readiness](operations/production-readiness-checklist.md), [customer UAT](uat/customer-uat-scenarios.md) |
| Review digital-lending controls | [UMRA control implementation](../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md) |

## Search from repository root

```bash
python3 scripts/search-docs.py "credit reporting" --api
python3 scripts/search-api.py "credit"
python3 scripts/search-api.py "umra"
make docs-build
```

From `apps/api`, run `php artisan route:list --json` for exact registered metadata. See [maintenance instructions](../../../docs/DOCUMENTATION_MAINTENANCE.md) for generating the full route index and its narrative coverage report.

Registration is not availability: role checks, controller-level ownership checks, feature gates, provider readiness and the deployment environment still apply. The testing export is not a production certificate, an SDK or a complete OpenAPI schema.

## Documentation changes with the implementation

Update affected contract, setup, operational and training documents in the same PR. Do not replace absent source evidence with invented fields, response examples or provider capabilities. Historical audit/demo/checkpoint documents retain their context and do not override current references.
