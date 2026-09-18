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
| POST | `/api/credit/offers/{offer}/accept` | Accept exact disclosures, separately consent to credit-information reporting, and choose verified payout wallet |

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
  "credit_reporting_consent": true,
  "disclosure_hash": "<64-char hash>",
  "wallet_id": 12
}
```

A successful acceptance records a separate versioned `credit_information_reporting` consent and may return `disbursement_pending`; it must not be presented as provider-confirmed money until finality is received.

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

## 8. Support and accessibility

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/support-cases` | Customer support cases |
| POST | `/api/support-cases` | Create support/assisted-KYC case |
| PATCH | `/api/accessibility-preferences` | Persist language/access preferences |

Accessibility preferences include simple language, large text, screen-reader optimisation and reduced motion.

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


## 11. UMRA digital-lending controls

Customer:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/receipts` | Customer transaction/e-receipt history |
| GET | `/api/receipts/{receipt}` | Customer-owned receipt detail |
| GET | `/api/credit/applications/{application}/guarantors` | List guarantor confirmations |
| POST | `/api/credit/applications/{application}/guarantors` | Add one of maximum two manually-entered guarantors |
| POST | `/api/guarantors/confirm` | Independent guarantor confirm/reject response |

Admin/operations:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/admin/umra/credit-reporting` | Credit-information exchange register/status |
| POST | `/api/admin/umra/credit-reporting/submit` | Submit eligible pending outbound reports |
| POST | `/api/admin/umra/loans/{loan}/evaluate-npl` | Evaluate/update UMRA NPL controls |
| POST | `/api/admin/umra/loans/{loan}/default-interest` | Accrue default interest within configured cap |
| PATCH | `/api/admin/umra/loans/{loan}/npl-enforcement` | Explicitly enable/disable per-loan cap enforcement while retaining tracking |
| GET | `/api/admin/umra/term-changes` | Credit-term governance register |
| POST | `/api/admin/umra/product-terms/{term}/changes` | Submit governed term change |
| POST | `/api/admin/umra/term-changes/{change}/approve` | Maker-checker approval; interest change requires prior UMRA evidence |
| POST | `/api/admin/umra/term-changes/{change}/apply` | Apply approved change to future offers |
| GET | `/api/admin/governance/regulatory-reports/{report}` | Inspect generated report/books payload and validation evidence |

Offer acceptance now additionally records explicit electronic consent for complete positive/negative credit-information reporting.
