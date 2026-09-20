# OpFin developer start here

Updated: 19 September 2026

This guide is the shortest route from “I have the repository” to “I understand where to make a safe change.”

## 1. Repository map

| Path | Owns |
| --- | --- |
| `apps/api` | Laravel API, identity, consent, scoring, credit, affordability, offers, payments, ledger, reconciliation, regulatory evidence |
| `apps/web` | Next.js customer/admin web experience |
| `apps/client` | Flutter Android/iOS customer app |
| `packages/contracts` | Shared contract/schema home |
| `docs` | Cross-product current documentation |
| `infrastructure/railway` | Deployment boundaries and release gates |
| `distribution/google-play` | Android store evidence/listing pack |

Do not create independent financial calculations in web/mobile clients. The API is authoritative for money, credit state, payment finality and regulatory controls.

## 2. Customer journey in one minute

`Phone → OTP → names → 6-digit PIN → Home → KYC → consent → credit profile → limit → application → formal offer → verified-wallet disbursement → repayment → receipt/reporting`

Important rules:

- second phone is optional;
- KYC requires NIN, ID front/back and photo holding ID;
- score components remain attributable;
- loan limit is profile-level;
- automatic approval also requires verified affordability;
- offer acceptance records exact disclosures and credit-reporting consent;
- provider acknowledgement is not financial finality;
- completed financial events create auditable receipts;
- positive/negative credit information is queued only when identity/data-quality/consent gates pass.

## 3. Local setup

### API

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

Use SQLite/testing configuration for normal automated tests unless the specific change requires PostgreSQL behaviour.

### Web

```bash
cd apps/web
npm ci --legacy-peer-deps
cp .env.example .env.local
npm run dev
```

Production-like web settings:

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

### Flutter

```bash
cd apps/client
flutter pub get
flutter analyze
flutter test
flutter run
```

## 4. Finding the API

For human-readable contracts:

```bash
python3 scripts/search-docs.py "loan offer" --api
```

For registered Laravel routes:

```bash
python3 scripts/search-api.py "credit"
python3 scripts/search-api.py "umra"
```

Or directly:

```bash
cd apps/api
php artisan route:list --path=api/credit
php artisan route:list --path=api/admin/umra
php artisan route:list --json
```

Start with `apps/api/docs/api/API_QUICK_REFERENCE.md`, then `current-endpoints.md`.

## 5. Where common changes belong

| Change | Primary code | Documentation to update |
| --- | --- | --- |
| New API route | `apps/api/routes` + controller/service | current endpoints + frontend/backend contract |
| Credit/scoring rule | API service/config/tests | launch journey + API/architecture + risk/compliance docs |
| Loan pricing/disclosure | offer/pricing services | API contract + UMRA controls + UAT |
| Payment/receipt | payment/ledger/reconciliation services | API + operational runbook + UAT |
| Customer app journey | `apps/client/lib` | client README + launch journey |
| Admin workflow | `apps/web/src/app/(portal)/admin` | web README/screen map + operations docs |
| Regulatory control | API + governance/admin | UMRA controls + reporting docs + UAT |
| External provider | `config/services.php` + adapter | integration doc + env example + readiness checklist |

## 6. Safe financial change pattern

A money-changing workflow should normally be:

```text
authenticated instruction
→ ownership/authorisation
→ policy validation
→ idempotent provider request
→ verified provider finality
→ database lock
→ product state
→ immutable ledger
→ receipt / regulatory side effects after commit
→ reconciliation
```

If a change skips one of those steps, explain why in code/tests/docs.

## 7. Documentation workflow

Before opening a PR:

```bash
python3 scripts/search-docs.py "<feature>"
python3 scripts/verify-documentation-drift.py
```

CI compares the PR to `main` and requires corresponding current docs when public API/backend/web/mobile contracts change.

For new developer-facing APIs:

1. update route/controller/service;
2. add tests;
3. update `apps/api/docs/api/current-endpoints.md`;
4. update `apps/api/docs/api/frontend-backend-contract.md` when clients are affected;
5. update `API_QUICK_REFERENCE.md` for new user-facing task categories;
6. update the relevant operations/UAT documentation.

## 8. Release definition

A change is not “done” because it compiled.

### CI workflow maintenance

`.github/workflows/ci.yml` must contain one definition for each job. A failed
workflow with no jobs may indicate invalid workflow YAML, not an application test
failure. Validate YAML (including duplicate mapping keys) and Bash syntax after
editing embedded scripts. Do not bypass the release gate to resolve this condition.

The API formatting check uses a NUL-delimited Git diff of added, copied, modified
and renamed PHP files, then removes the `apps/api/` prefix before running Pint
from that directory. This preserves filenames containing spaces, excludes deleted
files and fails if the comparison ref is unavailable. A docs-only change skips
Pint, but does not skip API tests or audits.

For production financial changes, the exact candidate must pass CI, security monitoring and deployment contract; migrations/provider configuration must be verified; production deployment must become healthy; and any required reconciliation/integrity checks must pass.
