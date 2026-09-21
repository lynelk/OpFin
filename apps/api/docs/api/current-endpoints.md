# Current API endpoints

Updated against the registered canonical platform routes on **21 September 2026**. Routes remain subject to the middleware and role gates in source.

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


## 17. Financial Spaces and multi-entity membership

Authenticated routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/financial-spaces` | List Financial Spaces the signed-in person can access |
| POST | `/api/financial-spaces` | Create Household, Savings Group, Business, SACCO, Investment/Fund or Partner Space |
| POST | `/api/financial-spaces/invitations/accept` | Join a Space using a single-use invitation token |
| GET | `/api/financial-spaces/{space}/members` | List members when authorised |
| POST | `/api/financial-spaces/{space}/invitations` | Invite a person with a scoped role |
| PUT | `/api/financial-spaces/{space}/capabilities` | Configure a Space capability when authorised |
| GET | `/api/financial-spaces/{space}/workspace` | Role-aware institutional workspace summary |
| PUT | `/api/financial-spaces/{space}/organisation-onboarding` | Progress Business/SACCO/Fund/Partner onboarding |
| POST | `/api/financial-spaces/{space}/employer/enable` | Enable Employer services on a Business Space |

A person is registered once and can hold different roles in many Spaces. Membership does not grant access to the member's Personal Space.

## 18. Complete financial-life APIs

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/financial-spaces/{space}/financial-life` | Cash, assets, debt, receivables, net position, upcoming commitments and safe-to-spend |
| GET/POST | `/api/financial-spaces/{space}/obligations` | List or record debt, payables and receivables |
| POST | `/api/financial-spaces/{space}/obligations/{obligation}/settlements` | Record settlement against an obligation |
| GET/POST | `/api/financial-spaces/{space}/assets` | List or record assets |

These endpoints are Space-scoped. Cross-Space access is denied unless an active membership/role permits the action.

## 19. Partner Catalogue, plans and subscriptions

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/marketplace/products` | Active eligible-market partner catalogue surface |
| GET | `/api/plans` | Active OpFin plans |
| POST | `/api/financial-spaces/{space}/subscription` | Activate plan entitlements for an authorised Space |

Permission, entitlement and product/regulatory eligibility are separate gates. Commercial economics must not determine financial-health advice.

## 20. Revenue events and CPay reconciliation

Operations/admin routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/admin/revenue-events` | Record idempotent subscription, commission, revenue-share, transaction, platform/API or servicing revenue |
| POST | `/api/admin/revenue-events/{event}/reconcile` | Attach CPay and reconciliation references and settle/reconcile the event |

CPay remains the approved execution/reconciliation boundary where money movement is required. Revenue events attribute commercial economics; they are not a replacement financial ledger.

## 21. Inclusive finance, programme delivery and alternative credit support

Customer routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET/PATCH | /api/inclusive-finance/profile | Read/update optional programme-measurement consent and service preferences |
| GET | /api/inclusive-finance/capability | Contextual financial-capability guidance and current credit position |
| POST | /api/inclusive-finance/capability/events | Record guidance/intervention/outcome evidence |
| GET | /api/inclusive-finance/reputation | Non-score financial-reputation pathway |
| GET/POST | /api/inclusive-finance/signals | Read or submit customer-reported non-risk signals |
| GET | /api/inclusive-finance/programmes | List active programmes |
| POST | /api/inclusive-finance/programmes/{programme}/enrol | Enrol in an open programme |
| GET/POST | /api/inclusive-finance/support-instruments | Read or submit alternative credit-support evidence |
| GET | /api/inclusive-finance/fair-treatment | Explain the governed credit-decision boundary |

Admin/operations routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | /api/admin/inclusive-finance/programmes | Programme register with enrolment counts |\n| GET | /api/admin/inclusive-finance/impact | Aggregate programme outcomes; optional programme_id query filter |
| POST | /api/admin/inclusive-finance/programmes | Create programme configuration |
| PATCH | /api/admin/inclusive-finance/programmes/{programme} | Update programme lifecycle/configuration |
| POST | /api/admin/inclusive-finance/signals | Ingest independently verifiable provider signal |
| PATCH | /api/admin/inclusive-finance/signals/{signal}/verify | Verify signal and, where permitted, mark it eligible for a governed risk model |
| PATCH | /api/admin/inclusive-finance/support-instruments/{instrument}/verify | Verify/reject alternative collateral or guarantee evidence |
| POST | /api/admin/inclusive-finance/fair-treatment/{application}/assess | Persist a fair-treatment decision review |

Programme-measurement attributes are technically separate from credit-decision inputs. Customer-reported signals are never risk eligible. Provider signals require provenance and active credit-processing consent before they may even become eligible for an approved scoring/product policy. Eligibility does not automatically alter the Composite Score.

Impact cohort reporting includes only consented programme-measurement profiles and suppresses cohort groups smaller than five. Alternative collateral verification records evidence; it does not automatically approve a loan or alter pricing.
