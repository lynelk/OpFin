# OpFin Backend

OpFin is a Uganda-first personal finance backend built on Laravel 11 and PHP 8.2. The current backend contains API authentication, role foundations, audit logging, NIN validation hooks, credit application flows, loan creation, repayment schedule generation, payment callbacks, transaction records, accounts, and journal entries.

This repository is not yet production-ready for regulated financial operations. Several broader OpFin modules, including dedicated consent records, affordability checks, loan offers, compliance reporting, employer benefits, savings, investments, and insurance, still need implementation behind audited service boundaries.

## Local Setup

Required tools:

- PHP 8.2+
- Composer
- SQLite for local tests, or MySQL/PostgreSQL for development parity
- Node.js/npm only if working on Laravel UI assets

Setup:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Run the API locally:

```bash
php artisan serve
```

## Environment Variables

Core:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_DEBUG`
- `APP_URL`
- `APP_TIMEZONE`
- `DB_CONNECTION`
- `DB_DATABASE`
- `QUEUE_CONNECTION`
- `CACHE_STORE`
- `SESSION_DRIVER`
- `SANCTUM_TOKEN_EXPIRY`
- `CORS_ALLOWED_ORIGINS`

Security and integrations:

- `PAYMENT_CALLBACK_SECRET`
- `CRB_URL`
- `CRB_CLIENT_ID`
- `CRB_CLIENT_SECRET`
- `AIRTEL_CLIENT_ID`
- `AIRTEL_CLIENT_SECRET`
- `AIRTEL_BASE_URL`
- `AIRTEL_COUNTRY`
- `AIRTEL_CURRENCY`
- `AIRTEL_PIN`
- `AIRTEL_PUBLIC_KEY`
- `MTN_MOMO_COLLECTION_SUB_KEY`
- `MTN_MOMO_DISBURSEMENT_SUB_KEY`
- `MTN_MOMO_BASE_URL`
- `MTN_MOMO_CALLBACK_URL`
- `MTN_MOMO_API_USER`
- `MTN_MOMO_API_KEY`
- `MTN_MOMO_CURRENCY`
- `MTN_MOMO_ENVIRONMENT`
- `YO_SMS_GATEWAY`
- `YO_SMS_ACCOUNT`
- `YO_SMS_PASSWORD`
- `OPENAI_API_KEY`
- `PINECONE_API_KEY`
- `PINECONE_URL`

No live credentials should be committed. Demo and test environments must use sandbox credentials only.

## Test and Quality Commands

Run tests:

```bash
php artisan test
```

Run a specific test class:

```bash
php artisan test --filter=FoundationApiTest
php artisan test --filter=ApiSecurityTest
php artisan test --filter=BackendCheckpointTest
```

Run migrations from a clean database:

```bash
php artisan migrate:fresh --seed
```

Run Laravel Pint if installed:

```bash
./vendor/bin/pint
```

## Current Module Status

Implemented/foundation:

- Sanctum API login/logout.
- Standard API response helper for foundation endpoints.
- User profile endpoint.
- Role constants for platform admin, operations, customer, employer admin, and support.
- Audit log model, migration, service, and profile audit middleware.
- Health check endpoint.
- NIN validation endpoint backed by CRB configuration.
- Credit product listing and loan application endpoints.
- Loan account creation from successful disbursement transactions.
- Repayment schedule generation from loan terms.
- Payment callback endpoints with shared-secret protection.
- Transaction approval authorization for platform admin and operations roles.
- Sensitive-action audit middleware on profile access, loan application status updates, and transaction approvals.

Partial or legacy:

- Credit application, disbursement, repayment, accounts, and journal logic exists but still mixes controller and service responsibilities.
- Financial migrations use a mix of `string`, `decimal`, and integer-like columns for money; the target standard is integer minor units only.
- Payment gateway services exist, but demo/live separation needs stronger environment gates.
- Some web controllers and Blade views remain from the older admin surface.

Missing or not yet production-grade:

- Dedicated consent module.
- Consent revocation enforcement.
- Formal affordability checks.
- Loan offer module.
- Policies, form requests, and API resources for the financial API.
- Idempotency-key tables/unique constraints for disbursement and repayment commands.
- Full ledger balancing guarantees.
- Compliance reporting.
- Savings, investments, insurance, employer-linked benefits, and CRB reporting workflows.

## Documentation

- Backend audit: `docs/audit/backend-audit.md`
- Architecture guide: `docs/architecture/`
- Checkpoint findings: `docs/audit/backend-checkpoint.md`
- API summaries: `docs/api/`
