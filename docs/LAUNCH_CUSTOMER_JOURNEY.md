# OpFin launch customer journey

Updated: 21 September 2026

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

### Financial resilience entry point

Home may also expose **Build financial resilience** as a secondary, non-blocking destination. It can show financial-capability guidance, the non-score financial-reputation pathway, optional programme participation and alternative credit-support evidence.

This surface must not become another mandatory onboarding funnel. Voluntary inclusion attributes require explicit programme-measurement consent, remain outside credit-risk inputs and are cleared when that consent is withdrawn. Recording a warehouse receipt, guarantee or other support instrument is evidence submission only; it is not credit approval.

OpFin does not absorb Stolets merchant operations through this surface. POS, inventory, purchasing and day-to-day SME operations remain Stolets product responsibilities.

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

- licensed/regulated provider identity and business address where configured;
- principal and amount customer receives;
- interest amount, rate, method/cycle and calculation explanation;
- individual fees, when/how they apply and fee treatment;
- total cost of credit and total repayment;
- duration and repayment frequency;
- equivalent APR where required;
- first and final payment timing;
- default-interest/penalty terms and applicable regulatory cap explanation;
- complaints procedure and contact route;
- offer expiry.

Acceptance is tied to the immutable disclosure hash. Customer selects a verified payout wallet.

Credit-information reporting consent is **separate and explicit** at offer acceptance. It covers complete/accurate positive and negative credit information for the facility. Missing consent prevents external bureau submission.

Disbursement remains pending until provider success.

## 10. Repayment and receipts

Customer can enter a permitted full or partial repayment amount and select a verified repayment wallet.

Each client initiation carries an idempotency key. The UI says **payment request sent** while the provider status is pending. Only a confirmed provider success updates the economic repayment state.

After provider-confirmed finality and committed financial posting, OpFin creates an auditable e-receipt. Receipt history is available under Activity. No final receipt should be created for a pending or rolled-back event.

## 10.5 Complaints

Customers can raise support/complaint cases through the supported channels. A complaint receives:

- case reference;
- recorded procedure/contact information;
- regulatory due date;
- first-response/resolution evidence;
- SLA-breach tracking.

The operating target is the configured 30-day regulatory resolution period.

## 10.6 Guarantors, where a product requires them

A borrower may manually enter no more than two guarantor contacts.

OpFin does not scrape the customer's address book. Each guarantor independently receives a confirmation request and may confirm or reject. The borrower should never collect or enter the guarantor's confirmation code.

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
- High-contrast mode may be offered as an additional preference, but production claims about VoiceOver/TalkBack or assistive-device compatibility still require supported-device UAT evidence.

## 14. Launch navigation

Primary mobile navigation:

**Home | Borrow | Activity | More**

Savings, investments, peer lending, SACCO/community capital, insurance and asset finance may remain implemented behind capabilities, but should not crowd the initial borrower journey until the relevant regulated/provider arrangements and operational journeys are activated.

## 15. Source-of-truth APIs

Key current endpoints are documented in `apps/api/docs/api/current-endpoints.md`. Customer clients must use API-returned profile, options, offers, wallets and payment state rather than maintaining independent lending rules.

## Financial resilience and health check-in

**Financial resilience** remains an extended journey rather than a new primary navigation destination.

The experience now combines:

1. financial-reputation progress;
2. current credit position;
3. a short optional financial-health check-in;
4. practical capability guidance;
5. voluntary inclusion/programme consent;
6. eligible programme participation;
7. alternative credit-support evidence.

The financial-health check-in is deliberately separate from underwriting. It returns a transparent wellbeing status and reasons, and the customer is told that it is not a credit score.

Richer livelihood, dignified-work, empowerment and community-finance observations are programme capabilities rather than compulsory launch onboarding questions. They should only appear in a customer journey when an authorised programme genuinely requires them and programme measurement consent is active.

## Programme check-ins and recorded-data enrichment

An enrolled customer with active programme-measurement consent may receive short due check-ins through App/Web, verified WhatsApp, USSD or authorised assisted capture. The questions are configured centrally and remain outside credit decisioning.

WhatsApp adds **CHECKIN** and USSD adds **Programme check-in**. These channels use the same response model as App/Web rather than maintaining separate programme logic.

Financial resilience may also offer **Use recorded data**, which builds a transparent wellbeing snapshot from financial-life information already recorded by OpFin. Missing external evidence is not guessed. The resulting status is not an underwriting score.
