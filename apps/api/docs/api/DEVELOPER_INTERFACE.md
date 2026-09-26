# Developer and AI discovery API

Status: Implemented candidate; full integration results are recorded in its pull request.  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

## Purpose

This interface makes the running OpFin API discoverable to people and AI-assisted integrations without creating another financial execution engine. It complements [current domain contracts](current-endpoints.md) and the [client contract](frontend-backend-contract.md).

The Developer Centre is `/developers` on the API origin. It offers public learning tracks, full-text search, authenticated role-filtered operation discovery, field/response inspection, explicit coverage status and export of reviewed OpenAPI contracts. A token entered into the browser stays in memory, is used only for same-origin documentation requests, and is not persisted.

## Routes

| Method | Path | Access and purpose |
| --- | --- | --- |
| GET | `/api/developer/manifest` | Public discovery links, fingerprints and limitations |
| GET | `/api/developer/public` | Explicitly published, unauthenticated documentation only |
| GET | `/api/developer/guides` | Public guide search using `q` |
| GET | `/api/developer/guides/{guide}` | Published guide selected by an allow-listed identifier |
| GET | `/api/developer/catalogue` | Sanctum-authenticated, role-filtered route and contract search |
| GET | `/api/developer/operations/{operation}` | One visible operation contract or registration-only entry |
| GET | `/api/developer/openapi` | Raw reviewed OpenAPI 3.1.1, excluding incomplete contracts |
| GET | `/api/developer/agent-tools` | Four documentation-only AI tool definitions |

Catalogue filters are `q` (maximum 160 characters), `page` (1–1000), `limit` (1–50), `group` and `method`. All search words must match. The authenticated role is derived from the server user, not query parameters. Metadata visibility is not permission to execute a domain endpoint.

JSON responses use `success`, `message`, `data`, except the raw OpenAPI export. Source revision is reported only when supplied by the deployment platform. Deterministic runtime/contract fingerprints are always present. Correlation and no-store headers apply to successful discovery responses; this does not rewrite every legacy API envelope or header contract.

## Readiness and honest coverage

Every non-demo registered route is inventoried internally. Explicit reviewed contracts are marked `documented`; other operations remain `registration_only`. The OpenAPI export excludes unreviewed operations from SDK generation. The catalogue reports missing contracts instead of inferring uncertain request/response schemas from route names.

The new interface supplies definitions for its own eight JSON routes. It does not claim that every existing financial endpoint now has a complete reviewed OpenAPI schema. The `--require-complete` gate must pass before that claim is made.

Documentation, implementation, deployment, provider activation and financial acceptance are separate states. Current financial/security findings remain outstanding until their own remediation evidence closes them.

## AI integration

The repository's `tools/opfin-mcp/server.py` supplies a local stdio MCP bridge using the 2025-06-18 protocol subset. It exposes API search, operation description, guide search and guide reading. It does not expose arbitrary URLs, code/SQL execution or financial mutations. The REST tool catalogue is not a hosted MCP/OAuth transport.

The bridge uses a configured approved origin and environment-supplied token, rejects redirects, bounds input/output size and returns explicit errors. It cannot switch to another API origin based on tool arguments. Retrieved content remains data, not authority to override domain controls.

Native AI-assisted actions still need least-privilege delegation, correct Financial Space, policy/eligibility/consent, risk approval where required, payload-bound idempotency and the expected accounting/reconciliation. A generic executor is deliberately not provided while native financial acceptance remains incomplete.

## Synchronisation

The catalogue reads registered routes and checked-in guides from the running build. Fingerprints change with source or contract content. It does not claim running production equals remote main.

`php artisan api:catalogue` exports a snapshot, reviewed OpenAPI, coverage and guides. `--check` validates definitions; `--require-complete` fails for missing contracts; `--baseline` compares an approved earlier snapshot. Runtime-service changes still require substantive review even if the route signature is unchanged.

Update the explicit contracts, examples, learning task and negative tests with each relevant change. GitHub Actions remains deferred; no hosted scheduled workload or new infrastructure is added by this interface.

## Verification

The candidate includes standalone catalogue tests, Python MCP safety tests and Laravel feature tests. Record actual results at the exact revision. PHP syntax checks and standalone tests do not substitute for the full Laravel suite, deployment verification, PostgreSQL concurrency or independent financial review.
