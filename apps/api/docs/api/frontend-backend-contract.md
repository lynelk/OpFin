# Frontend–backend contract

This document defines what mobile/web/WhatsApp/USSD clients may assume about the current OpFin API.

## 1. One server-authoritative customer state

Clients do not calculate their own credit limit, amount due, exposure, score or payment finality.

Use:

- `GET /api/credit/profile` for customer financial state and next action;
- `GET /api/credit/options` for eligible repayment terms;
- `GET /api/wallets` for verified payout/repayment choices;
- offer endpoints for pricing/disclosures;
- repayment/provider status for finality.

The App, WhatsApp and USSD should therefore remain consistent even when the customer switches channels.

## 2. Authentication contract

New registration:

1. `POST /api/generate-otp`
2. `POST /api/verify-otp`
3. collect first/optional other/last names and six-digit PIN
4. `POST /api/register`
5. save returned bearer token securely and open Home

Do not force the customer back to login after registration.

Android may send `app_signature` to `generate-otp` for SMS Retriever auto-fill. Manual OTP entry always remains possible.

Login uses `phone + pin`. Legacy `password` remains accepted temporarily for migrated accounts.

Never send PINs into analytics, logs, WhatsApp or USSD.

## 3. Progressive setup

After login, clients call `GET /api/credit/profile`.

Use `data.next_action.code` rather than duplicating state rules. Current examples:

- `VERIFY_PHONE`
- `VERIFY_IDENTITY`
- `GRANT_CREDIT_CONSENT`
- `CALCULATE_PROFILE`
- `REPAY`
- `BORROW`
- `VIEW_PROFILE`

Second phone is explicitly optional.

## 4. KYC

Mobile submits multipart KYC to `POST /api/kyc/cases`. Required evidence is NIN + National ID front/back + selfie holding ID.

The client may display sanitised check states returned by `/api/kyc/status`, but must not expect raw evidence paths/provider payloads.

If disability or another access need prevents ordinary camera completion, create a support case for assisted identity verification. Do not lower identity controls and do not ask a helper to handle PIN/OTP.

## 5. Score and limit presentation

Primary UI may show:

- OpFin Composite Score /100
- plain-language band
- available-to-borrow amount
- amount due
- total outstanding
- next due date
- plain-language explanations

Detailed component breakdown is progressive disclosure. Do not show internal probability-of-default values as a primary customer metric.

A missing score is **pending/not ready**, never zero.

## 6. Loan request

The mobile Loan Application page displays both `available_to_borrow_minor` and `amount_due_minor`.

Amount is bounded by the server profile. Eligible terms come from `/api/credit/options`. Do not restore hard-coded 14/30/60-day or fixed-limit client rules.

The request is not an offer and does not move money.

## 7. Offer

The offer endpoint is the pricing source of truth. Display the supplied amount received, interest, fees, total repayment, duration/frequency and store-policy disclosure values.

Accept using the exact `disclosure_hash`, a verified `wallet_id`, and explicit `credit_reporting_consent: true`. The reporting consent is stored as a separate versioned consent purpose and external bureau submission is blocked when consent is absent.

Do not say "disbursed" until provider success is confirmed.

## 8. Repayment

Every initiated repayment has an idempotency key and verified wallet ID.

A 202 response means **collection request accepted**. Show wording such as "Payment request sent; confirm on your phone" until provider finality updates the loan.

## 8.5 Receipts and complaints

After provider-confirmed financial finality, clients may read:

- `GET /api/receipts`
- `GET /api/receipts/{receipt}`

Do not create/display a final receipt for a merely pending provider request.

Customer complaints submitted through `/api/support-cases` return the complaint procedure and carry a regulatory due date. Client/admin screens should display the case reference and due/SLA state where relevant.

## 8.6 Guarantors

Where a product requires guarantors, the borrower may manually provide no more than two contacts. Do not request address-book/contact-list access. Each guarantor independently confirms or rejects through the confirmation workflow.

## 9. Error handling

Customers should see safe, actionable messages:

- what happened;
- what they can do next;
- no stack traces, raw provider errors or secrets.

Retryable network failure must not cause duplicate loan/repayment actions. Use backend idempotency/finality contracts.

## 10. Accessibility

Clients must:

- preserve logical reading/focus order;
- label controls for TalkBack/VoiceOver;
- honour device text size;
- offer large-text/reduced-motion preferences;
- use icon + text for primary actions;
- avoid colour-only status;
- keep touch targets accessible;
- keep the primary screen simple and reveal technical detail only on request.


## Financial-life space binding

Financial-life summaries, asset lists/creation, obligation lists/creation and settlements use the `{space}` route parameter to resolve the existing financial space before checking active membership. Unknown spaces return 404; authenticated users without an active membership receive 403. Record lists and settlements stay scoped to that space, including when the same user belongs to more than one space. A settlement referencing another space's obligation returns 404 without changing it. These endpoints record financial-life obligations; they do not initiate provider payments.
