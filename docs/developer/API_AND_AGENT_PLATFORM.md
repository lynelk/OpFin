# OpFin API and AI-integration platform

Status: Current implementation direction and acceptance contract  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

## Product direction

OpFin is an AI-assisted financial operating platform, not a generic API wrapper or lending-only product. Rich discovery and intelligent guidance must use the same server-authoritative services as human applications. Models can interpret, retrieve, explain and propose; they do not independently create financial truth.

The first implementation in this change is the read-only Developer Centre and agent-discovery interface. Its [API reference](../../apps/api/docs/api/DEVELOPER_INTERFACE.md) distinguishes delivered functionality from wider execution and financial acceptance.

## Three learning levels

The novice track explains origins, methods, authentication, identifiers, first requests and error interpretation. The application-developer track covers schemas, Space/role checks, environment isolation, idempotency, provider states and troubleshooting. The advanced/AI track covers source fingerprints, compatibility checks, tool discovery, prompt-injection boundaries, action approval and deterministic financial services.

The same checked-in guide content powers the browser and machine-readable guide endpoints. New runtime routes enter the catalogue automatically as explicit registration-only gaps until reviewed. Existing root/domain documentation remains linked rather than being declared obsolete or silently re-certified.

## Required full API completion standard

A production-ready operation needs a reviewed purpose, audience, request and response schema, example, authentication/authorisation contract, Space and consent rules, errors, idempotency/concurrency, provider state transitions, expected accounting where financial, observability, sandbox fixture and actual acceptance evidence.

API completeness is not the number of routes or the successful rendering of OpenAPI. The `api:catalogue --check --require-complete` gate deliberately fails while required coverage is missing. Automated generation must not hide unresolved validation or ownership semantics behind a permissive guessed schema.

## AI-assisted workflow and provider independence

The governing path remains authentication → target Space → entitlement/permission → environment → policy/consent → risk/approval → deterministic domain instruction → attributable execution → verification/accounting/reconciliation.

CPay is preferred, but internal record keeping, planning, reporting and discovery should continue through a provider outage. A verified internal NIN receipt can be reused only within approved source, subject, consent and freshness rules. There is no invented direct NIRA contract or universal retention interval.

Provider failover must not duplicate an ambiguous payment. A model's confidence is not provider finality, a lawful processing basis, a credit decision or an accounting event.

## Delivery boundaries

This change adds no financial write relay, generic code/SQL execution, hosted model requirement, new Railway service or Actions workflow. The supplied MCP bridge is documentation-only. Dedicated agent delegation, controlled proposals/approvals and every native financial operation require their own reviewed implementation and tests before they can be called complete.

The existing investment-club accounting work, Essentials financial/control findings and credential-log incident are not closed by the documentation interface. They remain launch acceptance requirements.

## Reference specifications

The OpenAPI profile follows OpenAPI 3.1.1, published by the OpenAPI Initiative. The local bridge implements the MCP 2025-06-18 stdio tools subset. These are explicitly selected interoperability versions, not claims about the latest specification. Tool annotations are descriptive, not security enforcement.

Canonical references: `https://spec.openapis.org/oas/v3.1.1.html`, `https://modelcontextprotocol.io/specification/2025-06-18/server/tools`, and `https://modelcontextprotocol.io/specification/2025-06-18/basic/transports`.
