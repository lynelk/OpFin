# OpFin shared contracts

Status: Controlled external developer reference  
Updated: 23 September 2026  
Language: English (United Kingdom)

This directory is the home for shared API/schema artefacts and compatibility tooling.

## Runtime authority

The registered Laravel API, handlers and automated tests remain authoritative for runtime behaviour.

Human-readable contracts live in `apps/api/docs/api/API_QUICK_REFERENCE.md`, `apps/api/docs/api/current-endpoints.md` and `apps/api/docs/api/frontend-backend-contract.md`.

Use:

```bash
python3 scripts/search-api.py "programme"
cd apps/api && php artisan route:list --json
```

## Contract direction

OpenAPI, JSON Schema or generated-client artefacts may be added here when generated from or mechanically validated against the authoritative API.

Do not hand-maintain a second competing specification.

Generated TypeScript/Dart clients must carry source/provenance/version information and fail validation when their source contract drifts.

## Publication rule

Developer-facing schemas and examples must not contain secrets, real customer data or invented production provider values. Run `make docs-check` and `make publication-check` before external distribution.
