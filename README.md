# OpFin

Canonical monorepo candidate for the complete OpFin platform.

## Imported source

- Backend: `lynelk/OpFin-BE@fda182cc2d3d741c5f9b462e5d80d5caea6e594a`
- Frontend/mobile: `lynelk/OpFin-FE@1ef9a0e9a4766ba802afc97f9b91f366620d053a`
- Generated: 2026-09-04

Both source histories are retained as parents in this Git graph. The old repositories remain the historical record until the new `lynelk/OpFin` repository is created, validated and cut over.

## Layout

- `apps/api`: Laravel API, queue worker and scheduler source.
- `apps/web`: Next.js web experience.
- `apps/client`: Flutter Android, iOS, Huawei and desktop client.
- `packages/contracts`: shared API-contract home.
- `infrastructure/railway`: Railway service-boundary documentation.
- `docs`: cross-platform architecture and migration evidence.

## Local verification

`make api-test`, `make web-test`, `make client-test` or `make test`.

Each application remains independently buildable and deployable. Repository consolidation does not combine runtimes, secrets or failure domains.
