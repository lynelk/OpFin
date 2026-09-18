# OpFin Backend

OpFin is the server-authoritative financial and customer-state layer for the Uganda-first OpFin platform. The launch borrower experience is deliberately simple even though the backend retains governed identity, scoring, affordability, offer, payment, accounting, reconciliation and audit controls.

## Launch borrower contract

The launch path is:

`Phone → OTP → names → 6-digit PIN → identity verification → credit profile/limit → loan request → formal offer → verified-wallet disbursement → repayment`

The same customer state is consumed by the Flutter app, WhatsApp and USSD. A second phone is optional.

Read `../../docs/LAUNCH_CUSTOMER_JOURNEY.md` and `docs/api/current-endpoints.md` before changing authentication, KYC, score/limit, loan, wallet or repayment behaviour.

## Production invariants

- Money is stored/transferred as integer minor units. UGX uses exponent `0`.
- CPay is the production collection/payout boundary unless architecture is explicitly changed and revalidated.
- Provider acknowledgement is not product/accounting finality.
- Financial requests are idempotent where a retry could duplicate an economic event.
- Ledger postings are append-only, positive-integer and debit/credit balanced.
- Reconciliation never invents balancing entries.
- KYC verification requires attributable evidence/results. Provider unavailability or inconclusive checks remain pending/review.
- CRB, MNO, approved third-party and internal score components remain attributable and decomposable. Missing provider data is not replaced with a fabricated score.
- A profile credit limit is a risk-based maximum exposure signal, not a promise of approval.
- Automatic approval requires the current KYC/consent/CRB/profile gates **and** verified affordability information within the configured debt-service threshold.
- Credit limit is profile-level. Linking multiple phones/wallets never multiplies exposure.
- Wallet selection is server-authorised and restricted to the authenticated customer's verified wallets.
- Pending payout/collection must not be presented as completed money movement.
- Legacy loan origination is compatibility-only. New lending uses the production decision → offer → provider finality → schedule → ledger → reconciliation path.

## Customer identity and authentication

New mobile customers:

1. request OTP for their phone;
2. verify OTP;
3. provide first name, optional other name and last name;
4. create/confirm a six-digit PIN;
5. receive an authenticated session and continue directly to Home.

Weak repeated/sequential PINs are rejected. Login attempts are rate-limited. Legacy password input remains a migration compatibility path only.

Android OTP auto-fill uses SMS Retriever/app-signature support and does not require broad SMS-reading permission.

## KYC

Required launch evidence:

- 14-character NIN;
- National ID front;
- National ID back;
- photo of the customer holding the National ID.

The configured identity adapter records NIN validity, liveness, face match and NIN/phone linkage. KYC evidence is private and production must use `KYC_FILESYSTEM_DISK` pointing to persistent private/object storage.

Assisted/PWD verification may change the interaction method but not the identity-assurance standard. Support/helpers must never request a customer's PIN or OTP.

## Credit profile and decisioning

`CustomerCreditProfileService` aggregates:

- verified identity/consent;
- CRB component;
- MNO component where configured;
- approved third-party component where configured;
- internal behaviour component;
- current exposure;
- amount due / total outstanding / next due date;
- profile credit limit and available-to-borrow amount;
- next customer action.

`AffordabilityService` separately enforces verified monthly income/obligation and the configured debt-service ratio. Missing verified affordability data refers a request instead of inventing capacity.

Default score weights and limit bands are configuration, not permanent product promises. Changes require Product/Risk/Compliance approval, test updates and documentation changes.

## Lending economics

Production credit uses the configured loan product/term and offer snapshot. For flat-interest terms:

```text
term_rate_percent = configured_rate / cycle_days * duration_days
interest_minor = round(principal_minor * term_rate_percent / 100)
```

Financed fees:

```text
net_disbursement_minor = principal_minor
total_repayment_minor = principal_minor + interest_minor + fees_minor
```

Deducted fees:

```text
net_disbursement_minor = principal_minor - fees_minor
total_repayment_minor = principal_minor + interest_minor
```

Formal offer acceptance is bound to the immutable disclosure hash. The mobile app may select only an authenticated customer's verified payout wallet.

## Repayment

Repayment initiation:

- requires a positive amount within the current outstanding obligation;
- carries an idempotency key;
- may select only a verified repayment wallet owned by the authenticated user;
- remains pending until provider success;
- allocates oldest due first using the versioned production policy;
- posts immutable accounting only after verified finality.

## UMRA digital-lending controls

The backend now includes:

- automated positive/negative credit-information reporting with data-quality, consent, due-date and retry controls;
- 30-day complaint-resolution clock and SLA evidence;
- NPL/default-interest cap tracking with explicit enforcement state;
- provider-finality-backed transaction receipts;
- maximum-two guarantor contact confirmation;
- maker-checker credit-term changes with mandatory prior UMRA evidence for interest-rate changes;
- regulator evidence packs/books and records in the Admin Compliance Centre.

See `../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md`.

### Additional production configuration

```text
OPFIN_LICENSED_ENTITY_NAME
OPFIN_LICENSED_TRADING_NAME
OPFIN_UMRA_LICENSE_NUMBER
OPFIN_BUSINESS_ADDRESS
OPFIN_COMPLAINTS_EMAIL
OPFIN_COMPLAINTS_PHONE
OPFIN_COMPLAINTS_URL
UMRA_COMPLAINT_RESOLUTION_DAYS
UMRA_CREDIT_REPORTING_DUE_DAYS
UMRA_ENFORCE_NPL_CAP
CREDIT_REFERENCE_REPORTING_URL
CREDIT_REFERENCE_REPORTING_TOKEN
CREDIT_REFERENCE_REPORTING_PROVIDER
```

Missing regulatory/provider details must remain explicit configuration gaps; do not invent them.

## Cross-channel rules

### WhatsApp

Production webhook signatures are verified. Short-lived sessions are OTP-backed. LIMIT/PROFILE/KYC are supported, including guided NIN → ID front → ID back → selfie-with-ID capture. High-impact BORROW/REPAY actions use secure authenticated hand-off. PINs are never collected in chat.

### USSD

USSD reads the same borrower state but does not capture KYC images or PINs. Production requires aggregator callback authentication/secret and external short-code provisioning.

## Accessibility

Customer interfaces must preserve simple language, logical screen-reader order, text scaling, reduced motion and practical touch targets. Assisted identity verification is supported without creating a lower-assurance account type.

## Important environment variables

Core:

```text
APP_ENV
APP_KEY
APP_DEBUG
APP_URL
APP_TIMEZONE
DB_CONNECTION
QUEUE_CONNECTION
CACHE_STORE
SANCTUM_TOKEN_EXPIRY
CORS_ALLOWED_ORIGINS
```

Credit / identity:

```text
CRB_URL
CRB_CLIENT_ID
CRB_CLIENT_SECRET
IDENTITY_VERIFICATION_URL
IDENTITY_VERIFICATION_TOKEN
KYC_FILESYSTEM_DISK
MNO_SCORING_URL
MNO_SCORING_TOKEN
THIRD_PARTY_SCORING_URL
THIRD_PARTY_SCORING_TOKEN
OPFIN_MAX_DSR_PERCENT
OPFIN_MIN_LIMIT_COVERAGE_PERCENT
OPFIN_CREDIT_MODEL_VERSION
OPFIN_AUTO_DECISION_POLICY_VERSION
```

Money movement:

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
```

Assisted channels:

```text
WHATSAPP_BASE_URL
WHATSAPP_PHONE_NUMBER_ID
WHATSAPP_ACCESS_TOKEN
WHATSAPP_APP_SECRET
WHATSAPP_VERIFY_TOKEN
OPFIN_WEB_URL
USSD_SHARED_SECRET
```

Missing external credentials keep the affected capability unavailable/pending. Never insert fake production credentials or provider results.

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

For repository release, use the root CI/security/deployment gates as documented in `SECURITY.md` and `AGENTS.md`.

## Documentation

Current sources of truth:

- root `README.md`
- root `AGENTS.md`
- root `SECURITY.md`
- `../../docs/LAUNCH_CUSTOMER_JOURNEY.md`
- `docs/README.md`
- `docs/api/current-endpoints.md`
- `docs/api/frontend-backend-contract.md`
- `docs/operations/production-readiness-checklist.md`

Dated audit/checkpoint files remain historical evidence and must not override newer code/current-contract documentation.


## Developer discovery

From `apps/api`:

```bash
python3 ../../scripts/search-api.py "umra"
python3 ../../scripts/search-docs.py "receipt" --api
php artisan route:list --path=api/admin/umra
```

Documentation changes are verified in CI alongside code changes.
