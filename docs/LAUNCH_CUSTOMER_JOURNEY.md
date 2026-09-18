# OpFin launch customer journey

Updated: 18 September 2026

This document is the current product contract for the launch borrower journey across the mobile app, WhatsApp and USSD. It favours comprehension over feature density. The customer should see a simple next step; the platform keeps scoring, provider orchestration, compliance and accounting complexity behind the interface.

## 1. Experience principles

1. **One clear next action.** Home is state-aware rather than a catalogue.
2. **Plain language first.** Use short sentences, familiar terms and icon-plus-text actions.
3. **Progressive disclosure.** Show score components, disclosures and detailed history when the customer asks for them, not all at once.
4. **No invented data.** An unavailable CRB, MNO or partner source remains unavailable/pending.
5. **Channel continuity.** App, WhatsApp and USSD read the same server-authoritative identity, profile, limit, loan and repayment state.
6. **Accessible by default.** Text scaling, VoiceOver/TalkBack, reduced motion, large touch targets and assisted KYC are part of the main journey.
7. **Secrets stay secret.** PINs and OTPs are never requested in WhatsApp or USSD support conversations.
8. **Financial finality is explicit.** A collection/disbursement request is pending until provider success is confirmed and reconciled.

## 2. Mobile app sign-up

### Step 1: phone

Customer enters a Ugandan mobile number and accepts the Terms and Privacy Notice. Credit-processing consent is not bundled into general account creation.

### Step 2: OTP

OpFin sends a six-digit OTP. Android may populate it through SMS Retriever without broad SMS-reading permission. Manual entry remains available.

### Step 3: names

Collect:

- First name
- Other name, optional
- Last name

Names should match the National ID. Verified identity data is authoritative for regulated identity fields.

### Step 4: 6-digit PIN

The customer creates and confirms a six-digit PIN. Predictable repeated/sequential PINs are rejected. Login attempts are rate-limited. Existing legacy password accounts remain compatible during migration.

Successful registration returns an authenticated session and lands directly on Home. Do not force a second login.

## 3. Home before identity verification

Show a simple setup state such as:

- Primary phone: verified
- Identity: not yet verified
- Second phone: optional

Primary action: **Verify your National ID**.

The second phone must never block baseline identity or scoring. It can improve confidence and add another verified wallet.

## 4. Identity verification

Required evidence:

1. NIN
2. National ID front
3. National ID back
4. Photo of customer holding the National ID

The app uses camera capture rather than broad gallery/storage access. Evidence is stored privately. The configured identity provider can return:

- NIN validation
- Liveness status
- Face-match status
- NIN/phone-link status
- Provider reference

All required checks must be valid for automatic verification. Provider unavailability or inconclusive results go to review rather than being promoted to verified.

### Assisted/PWD flow

A customer may request assisted identity verification through support if disability or another access need prevents normal camera capture. A trusted helper may position the device or enter non-secret information. The customer must keep PINs and OTPs private.

Assistance changes the interaction method, not the identity-assurance standard.

## 5. Credit-processing consent

Before external credit checks, record explicit versioned consent for credit processing. Customers may revoke it. Revocation prevents new credit processing until consent is granted again.

## 6. Credit profile and score

The customer credit profile may include:

- CRB score/history
- MNO/mobile-activity score
- approved third-party score
- internal OpFin behaviour score
- current exposure
- amount due
- total outstanding
- next due date
- profile-level credit limit
- available-to-borrow amount

Default composite weights are configuration, not UI promises:

- CRB: 40%
- MNO: 25%
- approved third party: 15%
- internal behaviour: 20%

Only available, current source components contribute to the calculated composite. Coverage is recorded explicitly. Minimum coverage is required before a positive automated limit is assigned.

Risk-based profile limit is a maximum exposure signal, not a promise of approval. Automatic approval also requires the configured verified affordability input and debt-service test; otherwise the request is referred for controlled review.

The customer sees:

- **OpFin Score: X / 100**
- a plain-language band
- available loan limit
- simple explanations and optional component detail

Do not show probability-of-default as a customer-facing metric.

## 7. Home after scoring

If an amount is due, repayment becomes the primary state. Otherwise show available-to-borrow.

Typical hierarchy:

- Amount due, when greater than zero
- Available to borrow
- OpFin Score and band
- Total outstanding
- Next payment date
- One primary action

Optional profile-strengthening tasks, including a second phone, remain secondary. If policy permits only one active loan, available-to-borrow is shown as zero while that loan remains active so the UI never invites a request the backend will reject.

## 8. Loan application

The Loan Application page must display:

- Available loan limit
- Amount due
- Requested amount, capped by server limit
- Eligible repayment periods returned by the API
- Simple purpose category

Store channels must not present prohibited short terms. Legacy hard-coded amount/term pickers are compatibility routes only and feed this same authoritative screen.

Submitting a request does not move money.

## 9. Formal offer

Before acceptance show, at minimum:

- Amount customer receives
- Interest
- Fees
- Total repayment
- Duration
- Repayment frequency
- Equivalent APR where required
- First payment timing
- Final repayment timing
- Offer expiry

Acceptance is tied to the immutable disclosure hash. Customer selects a verified payout wallet. Disbursement remains pending until provider success.

## 10. Repayment

Customer can enter a permitted full or partial repayment amount and select a verified repayment wallet.

Each client initiation carries an idempotency key. The UI says **payment request sent** while the provider status is pending. Only a confirmed provider success updates the economic repayment state.

## 11. WhatsApp

Secure WhatsApp commands include:

- STATUS
- PROFILE
- LIMIT
- KYC
- BORROW <amount>
- REPAY
- CONSENTS
- SUPPORT <message>
- LOGOUT

WhatsApp can explain state and create secure hand-offs. It must not collect the customer's OpFin PIN or complete high-impact commitments solely from untrusted free text.

## 12. USSD

Core menu:

1. My limit
2. Borrow
3. Repay
4. My loan
5. Complete profile
6. Help

USSD cannot capture KYC photos. It directs the customer to an authenticated app/secure assisted channel. A production USSD aggregator must authenticate callbacks with the configured shared secret or an equivalent approved provider-native mechanism.

## 13. Accessibility and low-literacy acceptance criteria

- Support system text scaling; OpFin can apply an additional large-text preference.
- Provide VoiceOver/TalkBack semantics for controls and status.
- Respect reduced-motion settings.
- Use 48 logical-pixel minimum interactive targets where practical.
- Never use colour as the only status cue.
- Use icon plus text for major actions.
- Avoid dense financial jargon on primary screens.
- Detailed score components and financial disclosures remain available on demand.
- Errors tell the customer what to do next.
- Journeys can be resumed after network/session interruption without duplicate financial actions.
- PWD assistance routes are available without creating a lower-assurance customer class.

## 14. Launch navigation

Primary mobile navigation:

**Home | Borrow | Activity | More**

Savings, investments, peer lending, SACCO/community capital, insurance and asset finance may remain implemented behind capabilities, but should not crowd the initial borrower journey until the relevant regulated/provider arrangements and operational journeys are activated.

## 15. Source-of-truth APIs

Key current endpoints are documented in `apps/api/docs/api/current-endpoints.md`. Customer clients must use API-returned profile, options, offers, wallets and payment state rather than maintaining independent lending rules.


## 16. UMRA credit-information exchange

Accepting a loan offer records a separate, versioned **credit-reporting consent** tied to the exact offer/disclosure hash. Credit-processing/scoring consent alone is not used as evidence for outbound reporting.

After disbursement, repayment, clearance, reversal and non-performing events, OpFin stages positive or negative credit information in the outbound reporting register. The register validates required customer/account information, stores an evidence hash and idempotency key, records provider references and retries failed submissions without fabricating success.

A scheduled portfolio snapshot supplements event-driven updates. Production activation still requires the certified applicable credit-reference reporting endpoint/schema.

## 17. Guarantor-backed products

A product term may require **0, 1 or 2** guarantors.

- OpFin never reads the borrower's contact list.
- The borrower deliberately enters a guarantor phone number.
- The guarantor receives a loan-specific consent message.
- Sharing the one-time code is electronic confirmation of the request.
- A credit application that requires guarantors pauses at **Awaiting Guarantors**.
- Decisioning resumes automatically only after the configured number of verified contacts exists.
- A third guarantor contact is rejected.

## 18. Provider-confirmed transaction receipts

Successful disbursements and repayments produce one immutable in-app receipt containing:

- OpFin receipt reference;
- transaction type;
- amount and currency;
- provider/provider reference;
- loan reference where applicable;
- provider-confirmed completion time; and
- SHA-256 evidence hash.

An SMS acknowledgement is also queued where a phone is available. **Queued SMS is not recorded as delivered** unless delivery evidence exists. Pending payment/disbursement requests do not receive a successful receipt.

## 19. Complaints

Every customer support/complaint case receives:

- a case number;
- regulatory category;
- **30-day SLA due date**;
- complete case history; and
- mandatory customer-facing resolution summary before resolve/close.

The complaint procedure and target resolution time are part of the pre-acceptance loan disclosure.

## 20. NPL/default-interest recovery controls

When a loan first becomes non-performing, OpFin snapshots the principal owing and tracks:

- initial contractual interest;
- configured default-penalty ceiling;
- recoverable-interest ceiling;
- total recoverable cap; and
- recoveries made after NPL classification.

The Uganda control can operate in **track** or **enforce** mode; launch default is **enforce**. In enforce mode collection initiation above the remaining tracked cap is rejected. Hourly evaluation also stages negative credit-information updates.

The exact calculation implemented from the January 2024 guideline wording remains subject to compliance/legal confirmation if UMRA issues further interpretation.

## 21. Credit-term variations

The accepted offer and disclosure hash are immutable.

A proposed variation:

1. is recorded separately with exact proposed changes and reason;
2. requires explicit customer consent;
3. if it changes the interest rate, requires a prior UMRA approval reference and SHA-256 evidence hash before the customer can accept; and
4. cannot be silently written over the original loan.

Generic automatic mutation of live-loan economics is deliberately disabled. An actual economic amendment requires a separately controlled, versioned amendment executor. Product-catalogue interest-rate changes also require prior UMRA approval evidence.

## 22. UMRA books and records

The Admin portal provides an **UMRA Control Desk** and dedicated reports for:

- digital-credit supervision;
- books and records;
- positive/negative credit-information exchange;
- NPL recovery controls;
- transaction receipts;
- credit-term variations;
- guarantor controls; and
- consumer complaints.

Generated reports are validation checked, evidence hashed, maker-checker controlled and exportable as JSON or CSV.
