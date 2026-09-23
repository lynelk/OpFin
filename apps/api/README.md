# OpFin Backend

Updated: 23 September 2026

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

Cito is the preferred third-party gateway where configured. gnuGrid/CRB and MNO data may route through Cito under the configured capability policy.

CPay is the preferred production money-movement route. Certified direct-provider fallback remains an explicit, governed configuration, not an automatic escape hatch.

KYC capability routing may use Cito/gnuGrid for NIN/phone evidence while biometric/document evidence uses an evidence-capable provider until an equivalent certified contract exists.

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
cp .env.example .env
php artisan key:generate
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

At reviewed `main` commit `1a580e490ccb4cd5cc55f06ea9d09fad6619fd7e`, Railway statuses report successful API/web/worker/scheduler deployment, but no GitHub Actions workflow run is attached to that exact head. Do not represent the exact head as fully release-certified until the required gates run.

External provider credentials/contracts, legal approvals, programme agreements and production certification remain activation evidence, not values to invent in source or documentation.
