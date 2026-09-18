# OpFin shared contracts

This directory is the home for shared API/schema artefacts and compatibility tooling.

## Current authority

The registered Laravel API and its tests remain authoritative for runtime behaviour. Human-readable client contracts live in:

- `apps/api/docs/api/API_QUICK_REFERENCE.md`
- `apps/api/docs/api/current-endpoints.md`
- `apps/api/docs/api/frontend-backend-contract.md`

Use:

```bash
python3 scripts/search-api.py "credit"
cd apps/api && php artisan route:list --json
```

## Contract direction

Shared OpenAPI/JSON Schema/generated-client artefacts may be added here when they are generated from or mechanically validated against the authoritative API. Do not hand-maintain a second competing specification.

Any generated TypeScript/Dart clients must carry provenance/version information and must fail CI when their source contract drifts.
