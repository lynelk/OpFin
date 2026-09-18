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
