# OpFin developer start here

Review date: 18 September 2026. Audience: new and experienced developers. Commands below are for a local checkout, not production.

## Understand the boundary first

| Location | Responsibility |
| --- | --- |
| `apps/api` | Laravel API, identity, consent, credit, offers, payments, ledger, reconciliation, worker and scheduler |
| `apps/web` | Next.js web/customer and operational interface |
| `apps/client` | Flutter Android/iOS customer app |
| `packages/contracts` | Shared-contract guidance and generated-reference conventions |
| `docs` | Product, cross-channel, training and maintenance references |
| `infrastructure/railway` | Deployment boundaries |
| `distribution/google-play` | Android store evidence and listing pack |

The API owns financial decisions and state. Web, mobile, WhatsApp and USSD must not develop competing credit limits or accounting calculations. Read [engineering rules](../AGENTS.md) before changing these boundaries.

## Install the tools used by this checkout

Use Git, Python 3.10 or later for documentation tools, PHP/Composer for the API, Node/npm for the web, and Flutter with the required platform toolchain for mobile. GNU Make is optional: each target exposes ordinary commands. Windows contributors can use WSL for the shell examples.

At the reviewed commit, `apps/api/composer.json` declares PHP `^8.2` and Laravel `^12.61.1`; the active CI configuration selects Node 22 and Flutter 3.47.1. Do not substitute the older Laravel 11 architecture description. Re-check [Composer requirements](../apps/api/composer.json), [web dependencies](../apps/web/package.json), [Flutter requirements](../apps/client/pubspec.yaml) and [CI](../.github/workflows/ci.yml) whenever dependencies change. Lockfiles, not a manually copied list of transitive versions, are authoritative for installation.

## Start with documentation only

No PHP, Node, database or provider account is needed to search the tracked documentation.

```bash
make help
make docs-search QUERY="loan offer"
make docs-build
make docs-serve
```

Open `.build/docs/index.html` directly or browse the loopback server on port 8008. Stage new Markdown files before building: the index deliberately reads tracked files, not private untracked notes.

## Set up the API locally

From repository root:

```bash
cd apps/api
composer install --no-interaction --prefer-dist
# Create local configuration only when it does not already exist:
if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate
fi
```

Review `.env` before the next commands. Use `APP_ENV=local`, a dedicated local SQLite database, mock money movement and no production credentials. The example defaults to SQLite, database-backed sessions/queue/cache, `MOBILE_MONEY_PROVIDER=mock` and dormant community-finance capabilities. Do not overwrite another developer's `.env`, run destructive migration commands, or connect a local test runner to production.

For a fresh local SQLite database only:

```bash
mkdir -p database
touch database/database.sqlite
php artisan config:clear
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal, confirm the API is responding:

```bash
curl --fail-with-body -H 'Accept: application/json' http://127.0.0.1:8000/api/health/live
```

The liveness endpoint confirms that the service responds; it does not prove provider readiness, worker health or a usable customer account. The readiness response must be inspected for dependency and integration state, not merely HTTP 200.

Use `make api-test` from repository root for the existing API test runner. PostgreSQL-sensitive changes still require PostgreSQL-backed evidence; a successful SQLite test does not establish locking, replay or isolation behaviour in production.

The example has no configured SMS gateway. Email logging is not a substitute for OTP SMS delivery. Use the supported test fixtures or an explicitly approved sandbox integration; do not weaken OTP checks to make onboarding appear functional.

## Set up the web application

```bash
cd apps/web
npm ci --legacy-peer-deps
# Do not overwrite existing local settings:
test -f .env.local || cp .env.example .env.local
npm run dev
```

Configure the local API origin in `.env.local`:

```dotenv
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

The browser origin must match the API's allowed CORS origin. `localhost` and `127.0.0.1` are different origins. Public web variables are visible to the browser; never put provider secrets there. Run `make web-test` for the repository's web checks.

## Set up the Flutter client

```bash
cd apps/client
flutter pub get
flutter analyze
flutter test
```

Use `OPFIN_API_BASE_URL` through the established `--dart-define` configuration and verify that the target device can reach that address. A physical phone's `localhost` points to the phone, not your development computer. Inspect platform transport restrictions before attempting local cleartext HTTP. Do not weaken release transport security or put provider secrets into a build.

Run a development build against an approved test API. Release APK/AAB and iOS builds use the checked-in release workflows, signing controls and production gates; `flutter run` is not a release procedure. See [mobile documentation](../apps/client/README.md) and [Android release guidance](GOOGLE_PLAY_LAUNCH_V1.md).

## Find and change an API safely

```bash
python3 scripts/search-api.py "credit"
python3 scripts/search-docs.py "repayment" --api
```

Read [the integrator guide](../apps/api/docs/api/INTEGRATOR_GUIDE.md), locate the handler and its tests, then update the implementation and relevant contract in the same PR. The current routes use `/api/...`; do not add an invented `/api/v1` prefix to client requests.

For financial changes, review authentication, record ownership, policy validation, idempotency, verified provider outcomes, database/ledger integrity and reconciliation. Never replace unavailable provider information with fabricated data.

## Finish the change

Follow [contribution rules](../CONTRIBUTING.md): update API, component, operations and training sources as affected; stage new docs; run `make docs-test` and `make docs-check BASE=origin/main`; run the affected product gates; and record evidence for the exact candidate. [Documentation maintenance](DOCUMENTATION_MAINTENANCE.md) explains the route export and coverage report.

A successful build does not prove live provider activation, store publication, real-device accessibility or production acceptance.
