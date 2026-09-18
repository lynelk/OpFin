# Security and compliance architecture

This document complements the root `SECURITY.md`.

## Identity and authentication

- Phone ownership is established by OTP.
- New mobile customers use a six-digit PIN after phone verification.
- OTPs and PINs are secrets; never request them through WhatsApp, USSD support or staff-assisted KYC.
- Authentication attempts are throttled/rate-limited.
- Session tokens remain in secure client storage.

## Identity evidence

KYC requires NIN, National ID front/back and selfie-with-ID. Automatic identity verification records independent liveness, face-match and NIN/phone-link outcomes.

Production KYC evidence must use private persistent/object storage. Paths/evidence do not belong in normal customer responses/logs.

Assisted accessibility routes change the interaction, not the assurance requirement.

## Consent and scoring

Credit-processing consent is explicit, versioned and revocable. The credit profile records CRB, MNO, third-party and internal components separately.

No source may be silently substituted when unavailable. Composite coverage is visible to decisioning. Positive automated limits require configured minimum coverage and required core components.

## Lending and affordability

Customer requests are bounded by server-authoritative available limit. Mobile-store term restrictions remain enforced server-side.

Automatic decisioning only approves within a current eligible profile and current CRB/KYC/consent gates. Other cases refer/decline rather than bypass control.

Formal offer disclosures are snapshotted and accepted by hash before disbursement.

## Money movement

Verified-wallet ownership is checked server-side. Repayment initiation is idempotent. Provider acknowledgement is not accounting finality; successful provider state, ledger posting and reconciliation control economic state.

## Channel security

### WhatsApp

- production webhook requires a valid Meta signature;
- session is OTP-backed and short lived;
- inbound provider message IDs are deduplicated;
- identity media is downloaded from provider APIs into private KYC storage;
- PIN is never accepted/requested in chat;
- regulated/high-impact actions use secure hand-offs.

### USSD

- production should configure callback authentication/shared secret or provider-native equivalent;
- no KYC photos or PIN capture;
- core menus read the same customer profile state;
- financial commitment moves to authenticated confirmation.

## Accessibility

Security UX must remain understandable with TalkBack/VoiceOver, text scaling, simple wording and reduced motion. A PWD or low-literacy customer must not be forced to disclose credentials to obtain assistance.

## Data minimisation

Only collect evidence required for the stated purpose. Customer screens should expose understandable outcome/reason information, not raw provider payloads or internal probability-of-default telemetry.


## Credit-information reporting

Outbound positive/negative credit information is controlled independently from credit scoring.

Before external submission:

- customer identity data must be complete and verified;
- a specific active `credit_information_reporting` consent must exist;
- payload is canonicalised and SHA-256 hashed;
- event identity is idempotent;
- provider reference, attempts, due date and failure reason are retained.

Missing consent produces `blocked_consent`; incomplete verified identity produces `blocked_data_quality`. Neither is silently overridden.

## Complaints and consumer protection

Support/complaint cases retain a regulatory due date, first-response time, resolution state and SLA-breach indicator. Complaint procedure/contact information is snapshotted so the customer disclosure can be evidenced later.

## NPL/default-interest controls

Loans record NPL date, principal at NPL, initial interest, accrued default interest, default-interest ceiling, recovery-cap evidence and whether cap enforcement is active.

Disabling enforcement is an explicit operational state, not permission to conceal a breach. Reports continue to surface cap exceptions.

## Guarantors

OpFin accepts at most two manually entered guarantor contacts for products that require them. It does not scrape contact lists. Confirmation/rejection is independent and evidenced electronically.

## Credit-term governance

Accepted offer snapshots are immutable. Direct interest-rate mutations are rejected unless prior UMRA approval evidence is present. Normal rate changes use the maker-checker credit-term change register and do not rewrite existing accepted offers.

## Receipts and regulatory evidence

Transaction receipts are created only after provider-confirmed financial finality and database commit. Regulatory report packs are generated from system-of-record tables, hashed, validated and officer approval controlled.
