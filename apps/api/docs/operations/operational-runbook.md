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

For Cito, CRB, MNO, third-party scoring or identity-provider outage:

- retain source status as unavailable/error/pending;
- do not substitute arbitrary scores/results;
- keep customer wording simple and recoverable;
- refer decisions where mandatory inputs are missing;
- record outage timestamps/provider references for reconciliation and incident review;
- when Cito is primary, never fire the direct-provider backup after an ambiguous request until the original request is reconciled; switch `OPFIN_EXTERNAL_SERVICE_ROUTE=direct` only as an explicit operational action after confirming duplicate-enquiry risk.

## Accessibility support

When a customer reports they cannot read, hear, see, manipulate the device or complete a camera step:

- use the customer's preferred accessible channel where available;
- explain one action at a time;
- offer larger text/screen-reader/reduced-motion guidance;
- open an assisted-verification support case when KYC capture is the blocker;
- never ask them or a helper to disclose PIN/OTP.

## Incident principles

Preserve evidence with least access, contain the affected path, rotate compromised credentials, reconcile financial state, communicate using verified facts, and record remediation. Public issue trackers must not contain customer identity/financial evidence.


## UMRA daily controls

### Credit-information exchange

Check the Admin Compliance Centre / credit-reporting register for:

- pending;
- blocked consent;
- blocked data quality;
- failed;
- overdue;
- submitted.

Never manually mark a failed/blocked item submitted. Resolve the underlying consent/data/provider issue and allow the governed process to retry or create a corrected event.

### Complaints

Every complaint has a regulatory due date. Operations should:

1. record meaningful first response;
2. maintain ownership/notes;
3. monitor cases within five days of deadline;
4. escalate unresolved cases before the deadline;
5. close only with resolution evidence.

### NPL/default interest

The scheduled control scan marks overdue schedules and records NPL control values.

When reviewing default-interest accrual, confirm:

- principal at NPL;
- initial disclosed interest;
- default-interest ceiling;
- accrued amount;
- recovery-cap evidence;
- enforcement status.

Do not use the enforcement toggle to disguise an overcharge or bypass policy. Any exception requires documented authorised reasoning and remains visible in reports.

### Receipts

Receipts are generated after provider-confirmed financial finality and commit. If a customer lacks a receipt:

1. verify provider finality;
2. verify ledger/product posting;
3. inspect transaction receipt record;
4. re-run/repair through controlled operations only if economic state is already correct.

Never issue a final receipt for a pending or rolled-back transaction.

### Credit-term changes

Do not edit interest rates directly.

Use the governed term-change register:

1. maker raises proposed change/reason;
2. independent checker reviews;
3. interest-rate change requires recorded prior UMRA approval reference/date;
4. apply to future offers only through the governed workflow;
5. existing accepted offers remain unchanged.

### Regulatory books/reports

Generate evidence packs from the Compliance Centre. Inspect validation evidence and payload/register detail before approval/submission.

The system generates evidence; the responsible regulated officer remains accountable for external filing.


## Financial Space operations — 20 September 2026

When investigating any non-credit financial record, identify the Financial Space first, then actor membership/role, capability, entitlement and eligibility. Never infer cross-Space authority from the fact that the same person participates in both Spaces. Employer/group administrators do not receive Personal Space visibility by relationship alone.

For Partner Catalogue and subscription/revenue operations, keep recommendation/financial-health logic upstream of commercial terms. Revenue Events are idempotent commercial attribution records and must reconcile to applicable provider evidence; they are not a substitute ledger.

Service Economics Events use one idempotent service/request reference and may be enriched as actual provider cost, customer/partner/platform fees, tax and settlement evidence become available. A known zero is `0`; unknown is `null`. Use the admin partner-reporting endpoints to investigate service profitability, capital/funding provenance, insurance, savings/investments and data-coverage controls.

For institutional onboarding, treat profile → KYB → regulatory evidence → products → integration → certification as progressive stages. Technical capability activation does not replace regulatory/provider approval.
