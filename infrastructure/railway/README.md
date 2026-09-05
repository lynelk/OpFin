# OpFin deployment: three application parts

OpFin is one source repository, not one executable application. Deploying its root with automatic language detection fails because the app manifests live under `apps/`.

## 1. Backend: API, worker, scheduler and PostgreSQL

| Railway service | Source root | Config file (absolute repository path) | Role |
| --- | --- | --- | --- |
| OpFin (existing service; API role) | `/apps/api` | `/infrastructure/railway/api.json` | Laravel HTTP API |
| opfin-worker | `/apps/api` | `/infrastructure/railway/worker.json` | Persistent queue consumer |
| opfin-scheduler | `/apps/api` | `/infrastructure/railway/scheduler.json` | Persistent Laravel scheduler |
| Postgres | Existing image and persistent volume | Unchanged | Shared backend data, queue and cache |

Set the root directory separately in Railway service settings. Railway config-file paths are relative to the repository, not to the selected application root. Never create separate databases for the API, worker and scheduler.

All application services use `lynelk/OpFin:main`. Keep one scheduler replica. The schedule in `apps/api/routes/console.php` retains five-minute payment status, financial reconciliation, integrity-audit and worker-heartbeat tasks; platform autopilot runs every fifteen minutes. Only the API pre-deploy hook runs forward migrations. No automatic seeding, schema reset, or shared cache flush is permitted.

Use the versioned startup scripts rather than Railpack's default Laravel startup, which runs `optimize:clear`. Flushing the shared database cache during a restart can remove locks and security state. The custom Caddyfile serves only `/app/public`, suppresses debug request logging and starts four PHP threads.

### Backend variable contract

Set values on backend services only; use Railway references rather than copying database passwords into source files.

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Africa/Kampala`.
- `APP_KEY`: preserve the same secret across API, worker and scheduler. The worker and scheduler can reference `${{OpFin.APP_KEY}}` while the API service retains its existing name. Never regenerate it on restart or expose it to the web/client.
- `DB_CONNECTION=pgsql`, `DB_URL=${{Postgres.DATABASE_URL}}?sslmode=require`. When a database URL already includes a query string, append SSL options using the correct URL separator. `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` reference the corresponding Postgres `PG*` variables.
- `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=120`, `CACHE_STORE=database`, `CACHE_PREFIX=opfin_production_` on every backend process.
- `RAILPACK_SKIP_MIGRATIONS=true`, `RAILPACK_NODE_VERSION=22.23.2`, `RAILPACK_PHP_EXTENSIONS=pdo_pgsql,pgsql,pcntl,bcmath`.
- `MOBILE_MONEY_PROVIDER=cpay`, `CPAY_ENVIRONMENT=production`, `OPFIN_ENABLE_DEMO_ROUTES=false`, `OPFIN_ENABLE_LEGACY_LOAN_ORIGINATION=false`.
- `LOG_CHANNEL=stderr`, `LOG_LEVEL=warning`. Secure, encrypted database-backed sessions are configured on the API.
- API: `PORT=8080`, `APP_URL=https://opfin-production.up.railway.app`, `CORS_ALLOWED_ORIGINS=https://opfin-web-production.up.railway.app`.
- The callback route implemented in source is `/api/webhooks/cpay`.

The worker timeout is 75 seconds and the database retry interval is 120 seconds. Job-specific timeouts must remain below the retry interval. Railway's `ALWAYS` restart policy restarts the worker after its one-hour planned recycling. Worker and scheduler must remain private, without public domains. Give production provider credentials only to the backend processes that require them, through service-scoped variables; no credential values belong in this document.

## 2. Web frontend

| Setting | Value |
| --- | --- |
| Service | `opfin-web` |
| Source root | `/apps/web` |
| Config file | `/infrastructure/railway/web.json` |
| Build | `npm run build` |
| Start | `npm run start -- --hostname 0.0.0.0 --port 3000` |
| Public address | `https://opfin-web-production.up.railway.app` |
| API base | `NEXT_PUBLIC_OPFIN_API_URL=https://opfin-production.up.railway.app/api` |

Set `NODE_ENV=production`, `PORT=3000`, `RAILPACK_NODE_VERSION=22.23.2`, `NEXT_PUBLIC_USE_MOCK_API=false`, `OPFIN_ENABLE_DEMO_SHORTCUTS=false`, and `NEXT_TELEMETRY_DISABLED=1`. The web service must not receive database passwords, `APP_KEY`, CPay private keys or messaging-provider secrets. Public build variables are visible to clients by design.

These generated Railway addresses are the setup endpoints, not a claim that custom-domain DNS or any older deployment has been cut over.

## 3. Flutter client releases

`/apps/client` is an Android/iOS client build target, not an always-running Railway service. The canonical CI includes Flutter analysis/tests and Android/iOS release-mode compile checks. Those checks are not signed production releases: Android CI may use debug signing and iOS CI uses `--no-codesign`.

For a distributable build, first configure the existing Android signing keystore outside source control or the Apple Developer team/provisioning on macOS. Preserve published application identifiers unless a deliberate migration is approved. Then run:

```sh
cd apps/client
export OPFIN_API_BASE_URL=https://opfin-production.up.railway.app/api
bash tool/build_release.sh android
# On a correctly provisioned macOS/Xcode machine:
bash tool/build_release.sh ios
```

The helper requires a public HTTPS API base including `/api`, runs analysis and tests, and refuses to fall back to Android CI debug signing. It leaves App Store P2P borrowing disabled. No mobile/desktop publication, signing ownership, physical-device certification, or unimplemented platform runner is implied by this setup. Distribution credentials remain outside Railway web/API build variables.

## Release and operational gates

Before a source merge, require the exact-head canonical CI and Deployment contract checks. Enable Railway **Wait for CI** for each GitHub-backed service. This is an account/service setting, not a setting implemented by these JSON files. Do not treat an agent's usage limit as a reason to skip the gate.

Back up any populated database and verify restore procedures before applying migrations. During the initial empty-database bootstrap, the live pre-deploy command refuses to initialize a non-empty schema. Replace that one-time guard with the API's versioned pre-deploy hook only after the bootstrap has been verified.

Verify API `/api/health/live` and `/api/health/ready`, web loading, allowed/rejected CORS origins, unauthenticated API denial, and fresh worker heartbeat with an empty queue. Observe at least two scheduler cycles and verify reconciliation/integrity outcomes. The current readiness response can return HTTP 200 while `integration_readiness` is `blocked`; HTTP 200 is not approval for live financial operations.

Before customer launch, complete provider configuration and certification, historical credential remediation in issue #20, persistent private document storage, backup/restore verification, least-privilege database roles, and custom-domain/app-signing work where needed. A fresh database bootstrap does not migrate customer data from a former environment.
