# OpFin shared contracts

The Laravel implementation and its behavioural tests establish current API behaviour. Curated contracts explain purpose, payloads, validation, client responsibilities and safe operation.

Start with [the integrator guide](../../apps/api/docs/api/INTEGRATOR_GUIDE.md), [current endpoints](../../apps/api/docs/api/current-endpoints.md) and [the frontend/backend contract](../../apps/api/docs/api/frontend-backend-contract.md).

## Generated discovery reference

From repository root, use the commands in [documentation maintenance](../../docs/DOCUMENTATION_MAINTENANCE.md) to export Laravel's route table. The generated `api-routes.json` and `API_ROUTE_REFERENCE.md` live under `.build/api/` and carry the source commit, tracked-worktree state and registration environment.

The export checks curated endpoint entries against registered routes and reports missing narrative coverage. It is regenerated for each candidate rather than manually edited or committed as a competing contract.

## Contract completeness

A route index is not a complete OpenAPI document or an SDK-generation input. Request/response schemas, error cases, permissions, idempotency and examples must be verified against the handler and behavioural tests before a complete machine-readable contract is claimed.

Any future OpenAPI, JSON Schema or generated TypeScript/Dart client must carry provenance, be mechanically validated against implementation, and fail CI when it drifts. Do not guess schemas from endpoint names or turn a testing export into a production-availability claim.
