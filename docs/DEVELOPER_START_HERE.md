# OpFin developer start here

Status: Controlled internal developer guide  
Updated: 23 September 2026  
Language: English (United Kingdom)

Read [CURRENT_STATE.md](CURRENT_STATE.md) first. This guide is the shortest path from checkout to a safe change.

## Repository map

| Path | Owns |
| --- | --- |
| `apps/api` | Laravel API, identity, consent, Financial Spaces, credit, programmes, partners, provider routing, money movement, ledger, reconciliation and governance |
| `apps/web` | marketing site, customer Web, Workspaces and operational/admin UI |
| `apps/client` | Flutter Android/iOS mobile-complete Individual and Savings Group experience |
| `packages/contracts` | shared contract/schema conventions |
| `docs` | product, operational, training, UAT and release documentation |
| `infrastructure/railway` | deployment topology and gates |
| `distribution/google-play` | Android store evidence/listing pack |

The API is authoritative for financial and regulated state. Clients do not create parallel pricing, scoring, settlement or programme-governance truth.

## Domain baseline

New work starts from:

`Person → Financial Space → Membership/Role → Capability → Entitlement → Eligibility`

Do not create a new account/person type when a Space, role or capability models the requirement.

Inclusive-finance programme measurement, protected attributes and provider evidence remain outside underwriting unless a separate approved risk-data pathway explicitly permits a non-protected signal. Commercial economics remain downstream of customer need, eligibility and suitability.

Stolets is a separate product; use explicit governed integration contracts rather than importing merchant operations into OpFin.

## Customer channel baseline

Canonical new-customer mobile journey:

`Phone → OTP → names → six-digit PIN → Home → progressive verification → financial position / eligible service → disclosed action → confirmed outcome`

The marketing website links existing/authorised users to Web sign-in. Do not treat its password-compatible login as the preferred new-customer journey.

## Local setup

### API

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

### Web

```bash
cd apps/web
npm ci --legacy-peer-deps
cp .env.example .env.local
npm run dev
```

### Flutter

```bash
cd apps/client
flutter pub get
flutter analyze
flutter test
flutter run
```

Use testing/local provider configuration. Never use production credentials merely to make a local journey look successful.

## Find the API and docs

```bash
python3 scripts/search-docs.py "programme"
python3 scripts/search-docs.py "credit offer" --api
python3 scripts/search-api.py "programme"
python3 scripts/search-api.py "umra"
```

Or:

```bash
cd apps/api
php artisan route:list --json
```

Registered routes establish exact addresses; handlers/tests establish behaviour; prose docs explain purpose and safe use.

## Where changes belong

| Change | Primary code | Documentation review |
| --- | --- | --- |
| API route/contract | API routes/controller/service | current endpoints, quick reference, client contract |
| Financial Space/member/role | API domain + clients | blueprint/domain model, manuals, UAT |
| Credit/scoring/pricing | API service/config/tests | credit/API/regulatory docs and UAT |
| Payment/ledger/reconciliation | API financial services | API, operations, security and UAT |
| Programme/impact | API + client programme surfaces | inclusive-finance framework, manuals, partner docs |
| Commercial/service economics | API + admin web | partner reporting standard, operations, UAT |
| Provider adapter/routing | service config + adapter | integration/readiness/current-state docs |
| Marketing-site claim | `apps/web/src/app/page.tsx` | web README/current state/product docs |
| Mobile customer journey | `apps/client/lib` | user/training/UAT/current journey |
| Release/deployment | workflows/infrastructure | release manifest, deployment/current state |

## Safe financial/provider change pattern

```text
authenticated instruction
→ ownership/authorisation
→ policy and consent validation
→ idempotent provider request
→ verified finality or explicit pending/error
→ locked domain state
→ immutable accounting where applicable
→ receipts/reporting after commit
→ reconciliation
```

Ambiguous Cito/provider failure must not silently fire a direct fallback. Reconcile first, then switch route explicitly under policy.

## Release evidence

A change is not done because it compiled or deployed. The exact candidate should have applicable CI, security, deployment, migration, provider and reconciliation evidence.

At the reviewed 23 September 2026 main head, Railway statuses are successful for API, web, worker and scheduler, but GitHub Actions produced no run for that exact SHA. Preserve that distinction in release notes and documentation.

## Documentation rule

Every material runtime/API/customer-workflow change updates relevant current docs in the same PR. Do not edit historical audit/demo records to make them look current.

Before review:

```bash
python3 scripts/search-docs.py "<feature>"
python3 scripts/verify-documentation-drift.py
make api-test
make web-test
make client-test
```

Run only the affected heavy gates locally if necessary, but the repository release process remains the authority for final acceptance.
