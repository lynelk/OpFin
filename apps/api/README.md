# OpFin Backend

Reviewed: 21 October 2026

OpFin is the server-authoritative financial, customer, programme and partner-state layer for the Uganda-first OpFin platform.

Start with [../../docs/CURRENT_STATE.md](../../docs/CURRENT_STATE.md), [../../docs/product/OPFIN_PRODUCT_BLUEPRINT.md](../../docs/product/OPFIN_PRODUCT_BLUEPRINT.md) and [docs/api/current-endpoints.md](docs/api/current-endpoints.md).

## Authoritative responsibilities

The backend owns:

- identity, authentication, consent and verification state;
- Financial Spaces, memberships, roles and permissions;
- financial position, obligations and product eligibility;
- credit profile, affordability, offers and repayment state;
- programme enrolment, measurement consent, instruments/follow-ups and partner isolation;
- provider routing, provenance and activation gates;
- money-movement finality;
- immutable accounting and reconciliation;
- receipts, reporting and regulatory evidence;
- commercial/service-economics evidence downstream of customer/product activity.

Clients render and submit authorised instructions. They do not create competing financial truth.

## Core invariants

- Money uses integer minor units.
- Provider acknowledgement is not finality.
- Economic retries are idempotent where duplication is possible.
- Ledger postings are append-only and balanced.
- Reconciliation does not invent balancing entries.
- KYC/provider evidence remains attributable; unavailable data stays unavailable.
- Credit limits are profile-level and are not multiplied by wallets or phones.
- Automatic credit decisions still require configured identity/consent/affordability/policy gates.
- Programme measurement/protected attributes remain outside underwriting.
- Commercial economics are recorded consequences, not recommendation inputs.
- Ambiguous primary-provider failure does not silently trigger direct fallback.

## Integrations

Cito is the governed third-party orchestration boundary where capability and country permissions are verified. Identity, credit-information, communications and money movement retain separate consent, source and regulatory controls. A certified provider route may be explicitly configured, but an ambiguous original request must be reconciled before any different route is considered. Biometric/document/official identity evidence is not fabricated when a verified provider capability is absent.

## New-customer authentication

Canonical mobile onboarding is phone → OTP → names → six-digit PIN. Weak/repeated/sequential PINs are rejected and authentication is rate-limited.

Web password-compatible login remains a compatibility/authorised-access route and must not redefine the canonical new-customer journey.

## Financial and programme separation

Financial-health snapshots, impact indicators, programme responses, voluntary inclusion fields and programme-partner reporting remain non-credit unless a separate explicitly approved risk-data path applies to a non-protected signal.

Provider ingestion verifies provenance. It does not automatically make a signal risk-eligible.

## Programme delivery

The backend supports configurable programmes, versioned instruments/questions, follow-up schedules, reviewed localisation, App/Web/verified WhatsApp/USSD/assisted capture, dedicated partner identities, privacy-suppressed aggregate exports and programme-to-commercial analytics.

Programme exit preserves historical audit evidence and closes the participation window for later outcome reporting.

## Commercial/service economics

Acquisition attribution, governed cost events, recorded revenue and service-economics evidence support funnel/CAC/contribution and partner reports.

Missing costs/revenue remain missing. Principal, premium and investment capital are not platform revenue.

## Local verification

```bash
composer install
if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate
fi
# Verify the selected isolated local database before running migrations.
php artisan migrate
php artisan test
./vendor/bin/pint --test
composer audit
```

## Discovery

```bash
python3 ../../scripts/search-api.py "programme"
python3 ../../scripts/search-api.py "umra"
python3 ../../scripts/search-docs.py "provider" --api
php artisan route:list --json
```

## Release evidence

At 21 October 2026, baseline `main` `c04d72f894aba163e6c8aaae668d8ad35a7af731` has successful GitHub deployment-status contexts for API, Web, worker and scheduler. This is not a fresh full Laravel/PostgreSQL, Flutter/device, live provider or financial acceptance pass. Follow the [source-to-release register](../../docs/operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md).

External provider credentials/contracts, legal approvals, programme agreements and production certification remain activation evidence, not values to invent in source or documentation.

## Developer and agent discovery (21 October 2026)

Read [API entry](../../API.md) and the [novice-to-advanced integrator guide](docs/api/INTEGRATOR_GUIDE.md). Discovery routes are in `routes/developer.php`; public manifest/guides differ from Sanctum-authenticated role-filtered catalogue, reviewed-only OpenAPI and read-only agent-tool descriptions. Routes in `routes/financial_intelligence.php`, `club_accounting.php`, `club_client_requests.php`, `essentials_collections.php`, `sacco_core.php` and `payroll_deduction.php` have different role, consent, feature and provider gates. The route list alone is not their contract or proof of activation.

Before publishing an endpoint change, update [current routes](docs/api/current-endpoints.md), field-level contract, positive and denied tests, operator/user tasks and UAT. `php artisan api:catalogue --check --require-complete` must expose missing reviewed schemas rather than guess them.
