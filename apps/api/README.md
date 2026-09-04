# OpFin Backend

OpFin is a Uganda-first financial platform backend built on Laravel 11 and PHP 8.2. It provides the governed product and relationship layer for borrowing, savings, protection, financial wellbeing, linked accounts, community finance, asset finance, participatory finance and partner distribution. External money movement is executed through CPay; OpFin remains authoritative for product decisions, obligations, schedules, customer state and product accounting.

## Production invariants

The backend is designed around explicit financial invariants rather than controller-side arithmetic:

- Money is stored and transferred as integer minor units. UGX currently uses minor-unit exponent `0`.
- CPay is the only production collection/payout boundary. The mock adapter is test/local only.
- An idempotency key is bound to one canonical money instruction. Reuse with a different provider, direction, amount, currency, party, source or reference is rejected.
- Provider success is not equivalent to product settlement until the relevant product service applies the verified finality event.
- Provider refunds/reversals normalize to the terminal `reversed` state. A clean unrepaid credit-disbursement reversal is corrected through an append-only reversal ledger transaction; repayment activity blocks automatic rewriting and creates an operations exception.
- Every production credit disbursement must have exactly one immutable disbursement ledger posting. Missing expected postings are critical financial-integrity findings.
- Every ledger transaction must contain positive integer entries and total debits must equal total credits.
- Reconciliation never manufactures balancing entries. Differences remain exceptions until resolved with provider and source evidence.
- Production credit uses exact integer schedules. Remainders are allocated deterministically to the final instalment.
- Production affordability applies the configured debt-service-ratio control: `estimated_monthly_obligation_minor / verified_monthly_income_minor * 100`, with `OPFIN_MAX_DSR_PERCENT=35` by default.
- Legacy loan origination is compatibility-only. New production lending must use the production decision → offer → CPay finality → exact schedule → ledger path.

## Credit economics

Production credit currently requires flat-interest product terms so pricing and disclosures are reproducible.

For a term with configured rate `r`, rate-cycle days `c`, duration `d` and principal `P`:

```text
term_rate_percent = r / c * d
interest_minor = round(P * term_rate_percent / 100)
```

Financed fees:

```text
net_disbursement_minor = P
total_repayment_minor = P + interest_minor + fees_minor
```

Deducted fees:

```text
net_disbursement_minor = P - fees_minor
total_repayment_minor = P + interest_minor
```

A deducted-fee disbursement posts the complete economic event:

```text
Dr Loan receivable             principal
Cr Provider disbursement cash  net cash paid
Cr Credit-fee clearing         deducted fees
```

The posting is rejected unless `net cash paid + deducted fees = principal`.

Repayments allocate oldest due first using the versioned policy `oldest-due-interest-fees-principal-v1`. The full collected amount must be consumed exactly.

## Long-range financial controls

Participatory finance reserves approved target capacity while a commitment awaits step-up. Reservation creation locks the listing and calculates:

```text
unreserved_capacity = target - settled_funding - active_reserved_commitments
```

A new commitment cannot exceed that amount. Failed/reversed provider collections release their reservation. Settlement revalidates the locked listing and cannot overfund it.

Asset finance enforces:

```text
0 <= deposit < asset_price
maximum_finance = asset_price - deposit
0 < approved_finance <= maximum_finance
```

Deposit collection requires an approved request, exact approved deposit amount, fresh OTP step-up and CPay finality.

Savings balances distinguish collected money from partner-confirmed custody:

```text
confirmed_balance = confirmed_contributions - paid_withdrawals
available_balance = confirmed_balance - reserved_withdrawals
```

## Financial integrity audit

`php artisan opfin:integrity-audit` verifies both arithmetic balance and event completeness. It checks, among other controls:

- ledger debit/credit equality;
- orphan ledger entries and duplicate immutable references;
- successful/unreconciled payment exceptions and duplicate provider references;
- successful production disbursements missing their expected ledger posting;
- reversed production disbursements missing append-only reversal accounting;
- false long-range settlement without provider finality;
- participatory funded-vs-settled mismatches and over-reservation;
- referral reward ledger mismatches;
- asset-finance price/deposit/approved-finance inconsistencies.

The production scheduler runs this audit repeatedly. A ledger is not considered financially sound merely because the entries that happen to exist balance.

## Local setup

Requirements:

- PHP 8.2+
- Composer
- SQLite for tests or PostgreSQL for production parity
- Node.js/npm only for Laravel asset work

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Run quality gates:

```bash
php artisan test
./vendor/bin/pint --test
composer audit
```

## Important environment variables

Core runtime:

```text
APP_ENV
APP_KEY
APP_DEBUG
APP_URL
APP_TIMEZONE
DB_CONNECTION
QUEUE_CONNECTION
CACHE_STORE
SESSION_DRIVER
SANCTUM_TOKEN_EXPIRY
CORS_ALLOWED_ORIGINS
```

Production financial controls:

```text
MOBILE_MONEY_PROVIDER=cpay
CPAY_BASE_URL
CPAY_MERCHANT_NUMBER
CPAY_MERCHANT_ID
CPAY_PRIVATE_KEY
CPAY_CALLBACK_URL
CPAY_CALLBACK_SECRET
CPAY_CALLBACK_REPLAY_WINDOW_SECONDS
CPAY_ENVIRONMENT=production
CPAY_COUNTRY=UG
CPAY_CURRENCY=UGX
CPAY_MINOR_UNIT_EXPONENT=0
OPFIN_MAX_DSR_PERCENT=35
OPFIN_ENABLE_LEGACY_LOAN_ORIGINATION=false
```

OTP/SMS, WhatsApp, CRB, KYC and partner-specific integrations require their own production credentials. Missing external credentials must keep affected capabilities fail-closed; no fake secrets belong in source or deployment configuration.

## Architecture boundaries

**OpFin owns:** identity and consent state, credit decisions, pricing snapshots, disclosures, product obligations, schedules, savings/protection state, financial wellbeing, product ledgers, audit evidence and operational exceptions.

**CPay owns:** execution of collections/payouts and provider-side payment/finality evidence.

A production money-changing path must therefore follow:

```text
authenticated product instruction
→ canonical idempotent intent
→ step-up where required
→ CPay execution
→ verified provider finality
→ product state transition
→ immutable accounting
→ reconciliation
→ integrity audit
```

## Demo and legacy surfaces

`/api/demo/*` is intentionally mock-labelled and is not a production financial rail. Older account, transaction, journal and web/Blade surfaces remain only where required for historical compatibility. New financial implementation must not depend on those legacy paths when an integer-minor-unit production service exists.

Do not treat old demo arithmetic, seed data, legacy journal balances or direct provider-era code as production source of truth.

## Documentation

Start with:

- `AGENTS.md` for engineering rules and financial invariants.
- `docs/integrations/mobile-money.md` for CPay, callbacks, idempotency and reconciliation.
- `docs/api/frontend-backend-contract.md` for client/server contracts.
- `docs/audit/backend-checkpoint.md` for historical findings and remediation status.
- `docs/architecture/` for architecture records.

The root README is the current high-level source of truth. Historical audit documents should be read as dated evidence, not as a description of the current production state.
