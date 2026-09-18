# Current API endpoints

Generated from the launch borrower contract on **18 September 2026**. Routes remain subject to the middleware and role gates in source.

All JSON API responses use the standard envelope:

```json
{
  "success": true,
  "message": "Human-readable result",
  "data": {}
}
```

Validation/business failures return `success: false` with safe error details.

## 1. Health

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/health` | Combined application health |
| GET | `/api/health/live` | Liveness |
| GET | `/api/health/ready` | Readiness, including runtime dependencies/heartbeats |

## 2. Account authentication

Public, throttled:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/generate-otp` | Send a six-digit OTP; accepts optional Android app signature for SMS Retriever |
| POST | `/api/verify-otp` | Verify OTP and return short-lived phone-verification token |
| POST | `/api/register` | Create account after verified phone; preferred payload is names + six-digit PIN |
| POST | `/api/login` | Phone + PIN; legacy password remains migration-compatible |
| POST | `/api/reset-password` | OTP-backed PIN reset; endpoint name retained for compatibility |

Authenticated:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/logout` | Revoke current token |
| DELETE | `/api/account` | Regulated account-deletion workflow; preferred re-authentication field is `pin`, with `password` retained for migrated accounts |
| GET | `/api/profile` | Sanitised profile; NIN is masked |

Preferred registration payload:

```json
{
  "phone": "2567XXXXXXXX",
  "verification_token": "<64-char one-time token>",
  "first_name": "Amina",
  "other_name": "",
  "last_name": "Kato",
  "pin": "482951",
  "pin_confirmation": "482951",
  "terms_accepted": true
}
```

Successful registration returns an access token and authenticated user; clients should go directly to Home rather than forcing another login.

## 3. Phone numbers and wallets

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/phone-numbers` | List profile phone numbers and confirm second phone is optional |
| POST | `/api/phone-numbers/secondary` | Add a second phone after OTP verification |
| GET | `/api/wallets` | List active verified wallets |
| POST | `/api/wallets` | Create/link a wallet to a verified phone |
| PATCH | `/api/wallets/{wallet}/default` | Set disbursement, repayment or both defaults |

Wallet ownership is enforced server-side. Credit limit is profile-level, never per wallet.

## 4. Identity and consent

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/kyc/status` | Sanitised identity status and check results |
| POST | `/api/kyc/cases` | Multipart KYC submission: NIN, ID front, ID back, selfie holding ID |
| GET | `/api/consents` | Current consent records |
| POST | `/api/consents` | Grant explicit versioned consent |
| DELETE | `/api/consents/{consent}` | Revoke consent |

Two consent purposes matter to the lending journey:

- `credit_processing` supports scoring/credit assessment.
- `credit_reporting` is recorded separately from the accepted loan offer/disclosure and gates outbound positive/negative credit-information reporting.

KYC multipart fields:

- `national_id`
- `national_id_front`
- `national_id_back`
- `selfie_with_id`
- optional `capture_channel=app|whatsapp|mobile_web`

Private evidence paths are not customer response fields.

## 5. Credit profile

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/credit/profile` | Composite score, limit, exposure, due state, setup and next action |
| POST | `/api/credit/profile/refresh` | Refresh eligible source data and recompute profile |
| GET | `/api/credit/options?distribution_channel=play_store` | Eligible active repayment options for the channel |

Typical profile data includes:

- `status`
- `composite_score`
- `band`
- `coverage_percent`
- `credit_limit_minor`
- `current_exposure_minor`
- `available_to_borrow_minor`
- `amount_due_minor`
- `total_outstanding_minor`
- `next_due_date`
- `component_breakdown`
- customer explanations
- setup state and `next_action`

Do not replace unavailable external data with made-up score values.

## 6. Credit applications and offers

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/credit/applications` | Customer's recent applications |
| POST | `/api/credit/applications` | Submit limit-aware request and run automatic decision where eligible |
| GET | `/api/credit/applications/{application}` | Application/decision/offer state |
| GET | `/api/credit/offers` | Customer offers |
| GET | `/api/credit/offers/{offer}` | Full disclosure and disclosure hash |
| POST | `/api/credit/offers/{offer}/accept` | Accept exact disclosures and choose verified payout wallet |

Application payload:

```json
{
  "loan_product_id": 1,
  "loan_product_term_id": 3,
  "institution_id": 1,
  "amount_minor": 150000,
  "reason": "School or education",
  "distribution_channel": "play_store"
}
```

The server rejects an amount above `available_to_borrow_minor`.

Offer acceptance:

```json
{
  "accept_disclosures": true,
  "disclosure_hash": "<64-char hash>",
  "wallet_id": 12
}
```

A successful acceptance may return `disbursement_pending`; it must not be presented as provider-confirmed money until finality is received.

### Guarantor-backed applications

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/credit/applications/{application}/guarantors/request-code` | Send a guarantor-specific electronic consent code |
| POST | `/api/credit/applications/{application}/guarantors` | Record electronically verified guarantor consent and automatically resume decisioning when the required count is met |

Product terms expose `guarantors_required` with an allowed range of 0–2. OpFin does not read the customer's contact list.

## 7. Repayment

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/loans/{loan_id}/repay` | Initiate full/partial collection from verified repayment wallet |

Required idempotency key is accepted in the `Idempotency-Key` header or body.

```json
{
  "amount_minor": 50000,
  "wallet_id": 12,
  "idempotency_key": "app-repayment:123:unique-value"
}
```

HTTP 202 means the collection request was accepted, not that repayment is economically final.

Before collection is initiated, non-performing loans are evaluated against the configured UMRA recovery-control record. In `enforce` mode, a collection request above the remaining tracked recovery ceiling is rejected.

### Transaction receipts

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/receipts` | List the authenticated customer's provider-confirmed transaction receipts |
| GET | `/api/receipts/{receipt}` | Retrieve one immutable receipt owned by the customer |

Only provider-confirmed successful disbursements/collections receive a successful e-receipt. Receipt payloads carry a SHA-256 evidence hash.

## 8. Support and accessibility

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/support-cases` | Customer support cases |
| POST | `/api/support-cases` | Create support/assisted-KYC case |
| PATCH | `/api/accessibility-preferences` | Persist language/access preferences |

Accessibility preferences include simple language, large text, screen-reader optimisation and reduced motion.

Customer complaints are classified for UMRA consumer protection, receive a 30-day SLA date, and require a customer-facing resolution summary before operations can close or resolve the case.

### Credit-term variations

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/credit/term-variations` | List proposed changes affecting the authenticated customer's loans, including consent hash |
| POST | `/api/credit/term-variations/{variation}/accept` | Record explicit consent to the exact proposed change |

Interest-rate variations cannot be accepted until prior UMRA approval evidence has been recorded. The original accepted offer remains immutable.

## 9. WhatsApp and USSD

Public provider callbacks:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/webhooks/whatsapp` | Meta verification challenge |
| POST | `/api/webhooks/whatsapp` | Signed WhatsApp messages/media |
| POST | `/api/ussd` | USSD aggregator callback |

WhatsApp signatures are checked with the configured Meta app secret in production. The WhatsApp KYC sequence supports NIN plus three guided photos. PINs are never collected in chat.

USSD supports status/limit/borrow/repay/loan/profile-help menus but hands image/commitment steps to an authenticated channel. Configure `USSD_SHARED_SECRET` or an approved provider-native equivalent.

## 10. Admin/operations

Existing admin KYC review, CRB ingestion, credit decision approval, offer generation, payment refresh and reconciliation endpoints remain role-gated. Manual approval is a controlled fallback when automatic profile decisioning cannot safely approve.

Demo routes remain disabled unless explicitly enabled in configuration/testing.


## 11. UMRA operations and books

Role-gated to authorised operations/support roles as defined in routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/admin/umra/credit-reference-submissions` | Positive/negative outbound CRB queue and summary |
| POST | `/api/admin/umra/credit-reference-submissions/{submission}/retry` | Retry a failed/pending CRB submission |
| GET | `/api/admin/umra/npl-controls` | NPL principal/default-interest/recovery-ceiling register |
| POST | `/api/admin/umra/loans/{loan}/evaluate-npl` | Re-evaluate a loan's NPL control |
| GET | `/api/admin/umra/term-variations` | Governed credit-term variation register |
| POST | `/api/admin/umra/loans/{loan}/term-variations` | Propose a separate versioned term variation |
| POST | `/api/admin/umra/term-variations/{variation}/umra-approval` | Record prior UMRA interest-rate approval reference + evidence hash |
| POST | `/api/admin/umra/term-variations/{variation}/apply` | Deliberately fail-closed until a separately controlled versioned economic-amendment executor exists |
| POST | `/api/admin/governance/regulatory-reports` | Generate a validated regulatory report |
| POST | `/api/admin/governance/regulatory-reports/{report}/approve` | Maker-checker approval |
| GET | `/api/admin/governance/regulatory-reports/{report}/export?format=json|csv` | Download an evidence-hashed report/register |

UMRA report profiles include:

- `umra_digital_credit_supervision`
- `umra_books_and_records`
- `umra_credit_information_exchange`
- `umra_npl_recovery`
- `umra_transaction_receipts`
- `umra_term_variations`
- `umra_guarantor_controls`
- `consumer_protection_complaints`

The Admin web application exposes these through **Compliance reports** and the **UMRA Control Desk**.
