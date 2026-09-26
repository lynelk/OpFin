# OpFin API documentation

Status: Source-based developer, integration and operations reference  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

## Start by purpose

| Need | Reference |
| --- | --- |
| New developer or AI integrator | [Root API entry point](../../../API.md), [Developer Centre interface](api/DEVELOPER_INTERFACE.md) |
| Current achievements and restrictions | [Current state](../../../docs/CURRENT_STATE.md), [concept/plan comparison](../../../docs/product/CONCEPT_AND_PLAN_COMPARISON.md) |
| Find an endpoint | [Quick reference](api/API_QUICK_REFERENCE.md), [current endpoint index](api/current-endpoints.md), [preserved detailed domain contracts](api/domain-endpoints.md) |
| Integrate treasury, Essentials or Location Context | [Current capability contracts](api/CURRENT_CAPABILITY_CONTRACTS.md) |
| Understand client responsibilities | [Frontend/backend contract](api/frontend-backend-contract.md) |
| Build an AI integration | [API and AI platform](../../../docs/developer/API_AND_AGENT_PLATFORM.md), [documentation-only MCP bridge](../../../tools/opfin-mcp/README.md) |
| Understand structure and security | [System overview](architecture/system-overview.md), [API design](architecture/api-design.md), [security/compliance](architecture/security-and-compliance.md) |
| Operate and verify | [Runbook](operations/operational-runbook.md), [readiness](operations/production-readiness-checklist.md), [current UAT](../../../docs/manuals/OPFIN_UAT_MANUAL.md), [dated delivery evidence](../../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) |
| Regulatory and security controls | [UMRA mapping](../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md), [security standard](../../../SECURITY.md) |

## Searchable Developer Centre

The source-linked `/developers` interface runs on the API origin when its change is deployed. Its six learning guides serve novice, application-developer and advanced/AI audiences. The same guide files are available as readable text through the browser and machine-readable endpoints.

Public metadata is explicitly allow-listed. The authenticated catalogue derives visibility from the actual Sanctum user, not a caller-supplied role. It inventories current routes, distinguishes reviewed contracts from registration-only gaps, and exports only reviewed operations as SDK-oriented OpenAPI.

The MCP bridge exposes four read-only documentation tools. It is not a financial write proxy, hosted OAuth server or arbitrary URL executor. Native domain permissions and financial controls remain authoritative.

## Source authority and usability

The API owns identity, consent, Financial Space authority, financial/product state, provider coordination, expected accounting, reconciliation and programme/commercial evidence. Clients submit authorised instructions and display returned state; they do not maintain competing prices, credit limits or financial finality.

From repository root:

```bash
make api-docs
make api-docs-check
make agent-docs-test
python3 scripts/search-api.py "essentials"
python3 scripts/search-api.py "statement"
python3 scripts/search-docs.py "repayment" --api
```

With local API dependencies installed:

```bash
cd apps/api
php artisan route:list --json
php artisan api:catalogue --check --require-complete
```

The strict completion gate deliberately fails until every registered operation has its reviewed contract. Complete route discovery is not complete field-level/API acceptance. Read validation, service rules and behavioural tests alongside prose. A source implementation that violates an approved requirement is a defect, not authority to rewrite that requirement.

## Recent contract distinctions

Treasury imports are reviewable source evidence, not confirmed money movement. Issued statements are frozen OpFin snapshots and preserve currency separation. PR #116 repaired the earlier historical-date/baseline and role-catalogue regression failures; their earlier dated failure record remains historical, not current unresolved status.

PR #117 added governed internal Cito NIN evidence reuse and scheduled revalidation. Its reviewed API build passed 283 tests. The capability remains unactivated until genuine policy settings and subject consent exist; it does not establish a direct NIRA API contract or full KYC reuse.

Essentials names the third-party lender and intends settlement to a verified biller/rental beneficiary. Its controller requires a body `idempotency_key` for repayment and returns 201 for accepted repayment records; neither that status nor a success envelope proves collection finality. Current unresolved financial-control reviews, including unmerged changes, remain separate work.

Location is purpose-specific optional context with server-side provider access. Programme measurements and health outcomes remain non-credit. `programme_partner` and Essentials `partner_api` roles are not interchangeable.

Do not assume all responses use the custom JSON envelope. Raw OpenAPI, HTML/CSV exports and framework/proxy failures require their own handling. Never put live tokens, customer identity records or provider credentials in examples.

## Maintenance and acceptance

The catalogue reads routes and checked-in guides from the running build. Fingerprints identify runtime/contract changes; they do not establish that production equals remote main. `api:catalogue --baseline=<approved snapshot>` highlights route/controller changes, while domain-service semantics still require review.

API changes update affected fields, errors, client contracts, manual tasks and UAT evidence in the same change. Existing documentation/publication checks have a limited scope and are not full schema, permission or financial certification.

GitHub Actions remains deferred by owner instruction. Keep equivalent candidate-specific tests/audits and independent review. The new interface does not close outstanding Essentials, full club accounting, native-schema coverage, PostgreSQL/concurrency or the credential-log security incident. Its own integration results are recorded against the exact PR candidate.
