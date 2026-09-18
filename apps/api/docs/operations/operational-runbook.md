# Operational runbook

Updated: 18 September 2026

## Customer-state rule

When investigating a borrower report, start from the server-authoritative profile and provider state. Do not infer a limit, amount due or payment completion from screenshots alone.

Key records:

- customer phone numbers and verified wallets;
- KYC case/check status;
- active consent;
- score components + credit profile;
- application/decision/offer;
- mobile-money transaction;
- loan/schedule;
- ledger and reconciliation state.

## KYC

### Evidence

Production KYC evidence must use the configured private persistent/object-storage disk. Never copy identity images into public storage, tickets, chat transcripts or local staff devices.

### Pending identity

Check NIN, liveness, face-match and NIN/phone-link outcomes individually. Provider unavailable/error is not the same as customer failure and must not be converted to verified.

### Assisted/PWD verification

Support cases with category `accessibility` and subject `Assisted identity verification` require an accessible interaction plan. A helper may position a device or help enter non-secret information. Staff/helpers must never request the customer's PIN or OTP. Identity assurance remains the same as the standard path.

## Credit profile

A profile limit is the maximum risk-based exposure signal. It is not a guarantee that every request will be approved.

Before automatic approval confirm:

- verified KYC;
- active credit consent;
- current clear/non-adverse CRB state;
- request within current available profile limit;
- current verified affordability input;
- debt-service ratio within configured policy.

If verified affordability data is unavailable, the system refers the request. Do not manually invent income merely to clear the queue.

## Wallets

A customer can have more than one verified wallet, but only one profile credit limit. Linking wallets/phones never multiplies exposure.

If a customer says money went to the wrong number, inspect the accepted offer's wallet selection and mobile-money transaction before taking action.

## Disbursement

Offer acceptance may create a pending provider transaction. Do not tell the customer funds were received until provider success is confirmed and the loan/ledger state is posted.

For failure/reversal:

1. inspect provider reference/status;
2. inspect offer/loan state;
3. inspect ledger reference;
4. run/inspect reconciliation;
5. escalate an exception rather than creating compensating rows manually.

## Repayment

Each repayment initiation has an idempotency key. If a customer retries, do not create a second collection merely because the first UI request timed out.

HTTP acceptance/pending is not repayment finality. Provider success must drive economic posting.

## WhatsApp

- Verify webhook signature failures before investigating message content.
- Sessions expire and require START/verification.
- PIN must never be requested in WhatsApp.
- WhatsApp KYC media is copied into private KYC storage and then processed under the normal identity controls.
- LIMIT/PROFILE reads the same profile as the app.
- BORROW/REPAY are safe hand-offs; regulated/high-impact confirmation remains authenticated.

## USSD

USSD uses the same borrower state but cannot capture identity photos. Confirm callback authentication and aggregator/session behaviour. Short-code provisioning is an external operational dependency.

## Provider/source outage

For CRB, MNO, third-party scoring or identity provider outage:

- retain source status as unavailable/error/pending;
- do not substitute arbitrary scores/results;
- keep customer wording simple and recoverable;
- refer decisions where mandatory inputs are missing;
- record outage timestamps/provider references for reconciliation and incident review.

## Accessibility support

When a customer reports they cannot read, hear, see, manipulate the device or complete a camera step:

- use the customer's preferred accessible channel where available;
- explain one action at a time;
- offer larger text/screen-reader/reduced-motion guidance;
- open an assisted-verification support case when KYC capture is the blocker;
- never ask them or a helper to disclose PIN/OTP.

## Incident principles

Preserve evidence with least access, contain the affected path, rotate compromised credentials, reconcile financial state, communicate using verified facts, and record remediation. Public issue trackers must not contain customer identity/financial evidence.


## UMRA credit-information reporting

Use the **UMRA Control Desk** before treating outbound CRB reporting as healthy.

1. Review pending, failed, submitted, positive and negative counts.
2. Open failed records and distinguish incomplete borrower/account data from provider transport failures.
3. Never edit a payload to make it pass silently. Correct source-of-truth customer/loan data and stage a new evidence-hashed submission.
4. Confirm active `credit_reporting` consent exists before retrying.
5. Retain provider reference, reporting date, payload hash and retry history.
6. The hourly reporting command also stages a current portfolio snapshot; event-driven disbursement, repayment, clearance, reversal and NPL records remain independently idempotent.

## UMRA NPL recovery controls

When a loan becomes overdue past its contractual due date:

1. Run/inspect the NPL control.
2. Confirm `principal_at_npl_minor` is the snapshot from NPL onset.
3. Review default-penalty, recoverable-interest and total-recovery ceilings.
4. Do not override a blocked collection merely to clear an operations queue.
5. If Compliance determines a regulator-approved interpretation differs from the configured formula, change the policy through reviewed source/configuration and preserve the old evidence, rather than editing historical controls.
6. The NPL control must remain reconcilable to schedule and successful provider collections.

## UMRA transaction receipts

- A receipt means the underlying provider transaction is successful.
- The in-app receipt is immediate evidence after finality.
- SMS is an acknowledgement channel, not the accounting source of truth.
- A queued SMS must not be described as delivered.
- Receipt payload hash and provider reference should be used when investigating customer disputes.

## UMRA complaints

All customer support cases currently enter the UMRA consumer-complaint register.

- SLA due date is 30 days from receipt.
- Operations should prioritise cases approaching the SLA.
- Resolve/close only with a customer-facing resolution summary.
- If a case is still open after SLA, do not alter the original received/due timestamps; record the actual later resolution and investigate the breach.
- Complaint outcomes feed the UMRA books-and-records report.

## Guarantor verification

- Never ask the borrower for contact-list permission.
- Customer deliberately types the guarantor number.
- OpFin sends a guarantor-specific consent message.
- The guarantor should share the one-time code only if they agree.
- Maximum two guarantor contacts per application.
- Decisioning remains paused if the configured required count is not electronically verified.
- If a guarantor disputes consent, preserve the verification evidence and open a complaint/investigation case.

## Credit-term changes

Accepted loan offers are immutable.

- A proposed change is recorded as a separate variation.
- Customer consent is required for all variations.
- Interest-rate variations additionally require prior UMRA approval evidence.
- The generic Admin route deliberately refuses to mutate active loan economics even after consent. Any future executor must version the schedule/contract, preserve the original and receive separate compliance review.
- Existing product-catalogue rate edits also require prior UMRA approval reference/evidence hash.

## UMRA books and records

Use **Admin → Compliance reports** to generate the requested evidence period.

Recommended sequence:

1. Generate the applicable UMRA profile.
2. Resolve validation failures from source data.
3. Review the evidence hash.
4. Have a different authorised officer perform maker-checker approval where the report is for submission.
5. Export JSON for complete machine-readable evidence or CSV for inspection/reconciliation.
6. Retain the report ID, period, regulator, payload hash, generated/approved officers and external submission reference outside the mutable user interface.

The internal evidence pack is not a substitute for any specific UMRA return template or submission channel prescribed separately.
