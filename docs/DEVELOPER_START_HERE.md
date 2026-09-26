# OpFin developer start here

Status: Current developer and verification guide  
Reviewed: 24 September 2026  
Language: English (United Kingdom)

Read [current state](CURRENT_STATE.md), the [original concept comparison](product/CONCEPT_AND_PLAN_COMPARISON.md) and [current delivery evidence](operations/DELIVERY_EVIDENCE_2026-09-24.md) before changing the platform. Source implementation, passing tests, deployed versions and activated providers are different states.

## Repository and domain map

| Location | Responsibility |
| --- | --- |
| `apps/api` | Identity/consent, Financial Spaces, financial life, credit, programmes, providers, treasury, Essentials, expected accounting and reconciliation |
| `apps/web` | Marketing, unified sign-in, customer access, Workspaces and role-gated operations |
| `apps/client` | Flutter App and essential Individual/Savings Group journeys |
| `packages/contracts` | Shared contract/schema conventions |
| `docs` | Product, comparison, manuals, operations and evidence |
| `infrastructure/railway` | Existing approved service topology and release controls |
| `distribution/google-play` | Controlled Android release/listing material |

Start from **Person → Financial Space → Membership/Role → Capability → Entitlement → Eligibility**. Do not introduce a duplicate identity where a Space or role models the requirement. Clients do not calculate parallel prices, exposure, repayment allocation or finality.

Programme/protected measurements are not underwriting; permitted external risk data uses a separate governed purpose/provenance/consent pathway. Commercial terms remain downstream of need and suitability. Stolets stays a separate operating product.

## Safe local setup

Use the versions declared in the current dependency manifests/lockfiles and release contracts. Do not use a historical architecture version label as the installation authority. Read root and component `AGENTS.md` before changing their code.

### API

From repository root, install into a dedicated local checkout:

```bash
cd apps/api
composer install --no-interaction --prefer-dist
if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate
fi
```

Review `.env` before migrations or tests. Use an isolated local/test database and approved mock/sandbox providers. Never copy production credentials or point local tests at a production database. Do not overwrite an existing `.env` or regenerate an established application key.

After confirming the local configuration:

```bash
php artisan migrate
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

Use the root `make api-test` for the repository's existing API test procedure. PostgreSQL locking, concurrent requests and migrations require production-like database evidence where relevant; a SQLite success is not that evidence.

### Web

In a separate terminal from repository root:

```bash
cd apps/web
npm ci --legacy-peer-deps
test -f .env.local || cp .env.example .env.local
npm run dev
```

Local settings use `NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api`, `NEXT_PUBLIC_USE_MOCK_API=false` and `OPFIN_ENABLE_DEMO_SHORTCUTS=false`. Keep browser origin/CORS consistent. Browser-visible variables must not contain provider secrets.

### Flutter

```bash
cd apps/client
flutter pub get
flutter analyze
flutter test
```

Set the established API build configuration for an authorised test environment. A phone's localhost is not the developer's computer. Do not weaken release transport security to make local networking work. Follow the current Android release contract for package identity, SDK and signing; ordinary `flutter run` is not a distribution procedure.

## Product and channel baseline

New App onboarding remains phone → OTP → names → six-digit PIN → authenticated Home with progressive verification. Web uses unified role-routed access and retains legacy-password compatibility; it does not create a new person for each portal.

Treasury account/statement foundations do not equal complete member-capital/NAV/distribution accounting. Detailed import/reconciliation currently uses Web administration. Essentials arranges named third-party lender finance to verified service/rental beneficiaries, not unrestricted wallet cash-out. Its financial controls are still under acceptance review.

## Find the API

```bash
python3 scripts/search-docs.py "treasury"
python3 scripts/search-docs.py "Essentials" --api
python3 scripts/search-api.py "essentials"
python3 scripts/search-api.py "statement"
```

With API dependencies installed, `cd apps/api && php artisan route:list --json` supplies registered metadata. Read the [new-capability contracts](../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md), [current endpoints](../apps/api/docs/api/current-endpoints.md) and [client contract](../apps/api/docs/api/frontend-backend-contract.md) alongside handlers/tests.

For example, ordinary loan repayment and Essentials repayment do not have identical status/idempotency contracts. Essentials requires a body key and returns 201 for a created repayment record, not final money collection. CSV/HTML exports and framework errors are not all custom JSON envelopes.

## Change impact

| Change | Required documentation and evidence |
| --- | --- |
| Routes, fields, responses, auth or retry | API reference/client contract plus positive and negative behavioural tests |
| Space/role/capability | Domain/Blueprint, user task, denied-access cases and actor/audit context |
| Financial state, treasury or Essentials | Financial policy/operations, expected ledger/reconciliation events, replay/concurrency and independent review where required |
| Programme/impact/provider data | Consent/purpose, suppression, source attribution, programme and partner guidance |
| Web/App workflow | Component README, exact screen labels, task/training/UAT including recovery/accessibility |
| Release/security/configuration | Current evidence, manifest, runbooks and configuration/deployment tests |

## Current build correction and remaining blockers

The Web login-error query object receives an explicit `Record<string, string>` annotation in this change. An isolated TypeScript 5.8.3 strict test reproduced TS2345 before and passed afterwards. It is a type-only correction: credentials, cookies, role checks and redirects are unchanged. Full repository and deployment results must be recorded separately.

The six current API failures and Essentials review findings are not solved by this annotation or by new documentation. Historical treasury dates must remain valid test cases, and new financial paths need their own expected accounting and concurrency tests.

## Verify and release

Run affected implementation checks and `make docs-check`/`make publication-check` for their stated scope. Retain the exact candidate, command, environment and actual result. Failed or absent checks are not a pass.

GitHub Actions remains disabled by owner instruction. Do not re-enable it, remove existing build tests/audits or use Railway Agent. Use only approved existing infrastructure; a build/deployment request is not consent for a new service, database, volume or replica.

After a permitted release, verify actual running source, forward migrations, API/Web health, worker/scheduler freshness and applicable reconciliation. Preserve original concept requirements and historical evidence; do not rewrite requirements to legitimise a defect.
