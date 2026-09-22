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

`component_breakdown.positive_employment_behaviour` is a benefit-only enrichment object. It may show a capped uplift from verified positive employer signals. The client must never interpret missing, unavailable or negative employer-behaviour data as a deduction; those states are neutral by policy. The base composite value remains available inside that breakdown for explanation.

A missing score is **pending/not ready**, never zero. External source routing is also not a client concern: Cito is preferred when configured, while an explicitly selected direct provider may be used as backup. Clients consume the same OpFin profile contract either way.

## 6. Loan request

The mobile Loan Application page displays both `available_to_borrow_minor` and `amount_due_minor`.

Amount is bounded by the server profile. Eligible terms come from `/api/credit/options`. Do not restore hard-coded 14/30/60-day or fixed-limit client rules.

The request is not an offer and does not move money.

## 7. Offer

The offer endpoint is the pricing source of truth. Display the supplied amount received, interest, fees, total repayment, duration/frequency and store-policy disclosure values.

Accept using the exact `disclosure_hash`, a verified `wallet_id`, and explicit `credit_reporting_consent: true`. The reporting consent is stored as a separate versioned consent purpose and external bureau submission is blocked when consent is absent.

The internal `funding_pool_id` is an operations/capital-provenance field and is not a customer-facing recommendation signal. Customers receive the contractual credit terms, not internal capital-allocation mechanics.

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


## 11. Identity, Financial Spaces and progressive onboarding

A person registers once. Clients must not create separate identities for group membership, employment, SACCO membership or investment activity. Use Financial Spaces and memberships/roles to represent those contexts. The Personal Space is private by default.

The current Space contract includes listing/creating Spaces, accepting invitations, membership/invitation management, Space capabilities, financial-life summaries, assets/obligations, institutional workspace state and progressive organisation onboarding. Employer is enabled on a Business Space rather than represented as a second legal entity.

Permission, entitlement and eligibility are distinct server-side gates. A visible UI capability does not imply that a regulated product is eligible or activated. Commercial terms/revenue must not feed backwards into financial-health advice.

## 12. Channel contract

Individuals and Savings Groups must be able to complete normal journeys in the Flutter App. Web consumes the same server-authoritative Space state and adds analysis/productivity/institutional workspace depth. USSD/WhatsApp/assisted channels must not invent separate financial truth.


## 13. Inclusive-finance and programme-delivery contract

The mobile and web clients may use the inclusive-finance API as an additional resilience/programme surface. It does **not** replace the server-authoritative credit profile, decision, offer or payment contracts above.

Customer routes:

- `GET/PATCH /api/inclusive-finance/profile` for optional programme-measurement consent, voluntary inclusion attributes and service preferences;
- `GET /api/inclusive-finance/capability` for contextual guidance based on existing financial truth;
- `POST /api/inclusive-finance/capability/events` for intervention/outcome evidence;
- `GET /api/inclusive-finance/reputation` for the non-score financial-reputation pathway;
- `GET/POST /api/inclusive-finance/signals` for customer-reported, non-risk signals;
- `GET /api/inclusive-finance/programmes`, `POST /api/inclusive-finance/programmes/{programme}/enrol` and `DELETE /api/inclusive-finance/programmes/{programme}/enrol` for programme discovery, enrolment and voluntary exit;
- `GET/POST /api/inclusive-finance/support-instruments` for alternative credit-support evidence; and
- `GET /api/inclusive-finance/fair-treatment` for the documented decision boundary.

Client rules:

- voluntary programme-measurement fields are never presented as credit-score components;
- withdrawal of programme-measurement consent clears the stored voluntary attributes while retaining grant/withdrawal timestamps for audit evidence;
- customer-submitted alternative-data signals are never risk eligible;
- provider-supplied signals are not exposed as approved scoring inputs merely because an operator verifies their provenance;
- verified warehouse receipts, guarantees or other support evidence do not automatically change eligibility, limit, price or approval;
- programme eligibility and credit eligibility are separate concepts;
- leaving a programme is idempotent; historical evidence remains bounded by the recorded enrolment and exit timestamps, and the client must not silently re-enrol an exited participation;
- an automated fair-treatment reason-code review is evidence of that review scope only and must not be presented as certification of an external model/provider's fairness;
- high-contrast mode is an accessibility preference, not a claim of physical-device certification.

**OpFin/Stolets boundary:** Stolets remains a separate SME automation, digitisation and commerce product. A future Stolets-derived signal can enter OpFin only through an explicit, consented and governed provider interface. Clients must not merge the products, databases or user journeys.

## Inclusive impact contract

### Financial-health check-ins

`GET /api/inclusive-finance/impact/financial-health` returns:

- `latest` and recent `history`;
- a transparent `financial_health_status` of `struggling`, `stabilising`, `resilient` or `progressing`;
- human-readable `status_reasons`;
- `is_credit_score=false`;
- `credit_decision_eligible=false`.

The classification is a wellbeing/resilience aid, not a probability-of-default model. Client surfaces must not imply that the status approves, declines, prices or changes a credit limit.

Programme-linked check-ins, livelihood observations and empowerment observations require both:

1. active programme measurement consent; and
2. active enrolment in the referenced programme.

### Programme framework contract

The admin framework endpoint returns:

- programme identity/lifecycle;
- optional theory of change;
- assigned indicator definitions;
- programme targets/reporting configuration;
- a measurement-boundary notice.

Indicator definitions include outcome domain, value type, unit, calculation methodology, provenance expectations, verification requirements, frequency, privacy classification, framework/version and disaggregation dimensions. The API always returns `credit_decision_eligible=false`.

### Outcome reporting and privacy

Programme outcome summaries report aggregate coverage and indicator observations. For participant-specific indicators:

- distinct participant count below five suppresses participant values;
- boolean or category distributions are additionally withheld where releasing a small subgroup would reintroduce differencing risk;
- institutional/aggregate observations can be reported separately from participant evidence;
- the API states that observed change must not be described as programme-caused unless the evaluation design supports causal attribution.

### Programme partner contract

A `programme_partner` account sees only programmes with an active explicit grant. Partner impact responses combine existing programme delivery metrics with the new outcome framework. No endpoint in the partner surface returns individual participant records.

Dedicated partner identities must be provisioned explicitly. Do not convert a customer's financial account into a partner identity as a convenience shortcut.
