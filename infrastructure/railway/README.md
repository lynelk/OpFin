# Railway service mapping

The monorepo preserves independent services:

| Service | Root directory | Runtime |
| --- | --- | --- |
| opfin-api | `/apps/api` | Laravel HTTP API |
| opfin-worker | `/apps/api` | Queue worker |
| opfin-scheduler | `/apps/api` | Five-minute scheduler/reconciliation |
| opfin-web | `/apps/web` | Next.js |
| PostgreSQL | unchanged | Managed database |

Flutter clients are built through release pipelines and are not Railway runtime services. Secrets remain scoped to the minimum required service.
