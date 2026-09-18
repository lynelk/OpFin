# OpFin security

Security is maintained through layered controls, verified changes and timely response. A successful scan is not a certification that a system is free of vulnerabilities. Do not describe this repository or a release as permanently secure.

## Reporting

Do not publish passwords, PINs, OTPs, identity documents, account numbers, access tokens, production logs or exploitable customer records in a public issue. Report concerns privately to the repository owner or authorised OpFin security contact. A dedicated security mailbox and private vulnerability-reporting configuration must be confirmed before publication. Never invent a support or security address.

## Required release checks

The exact release commit must pass:

- **OpFin Monorepo CI**: layout, current-index secret scan, API tests/audit, API asset audit/build, web audit/typecheck/lint/tests/production build/HTTP smoke, Android and iOS release compile checks, and aggregate `release-gate`.
- **OpFin security monitoring**: locked web/API asset/PHP/Pub dependency checks, security and brand controls, and aggregate `security-gate`.
- **Deployment contract** for the Railway service boundaries.

Failed, cancelled, missing or incomplete checks are not a pass. Do not weaken an audit or bypass a failing gate merely to ship.

## Customer authentication

- New mobile customers verify their phone by OTP, then create a six-digit PIN.
- OTPs are hashed at rest, expire, have attempt limits and are consumed/invalidated by the relevant flow.
- Android may use SMS Retriever/app-signature formatting for OTP auto-fill; OpFin does not require broad SMS-reading permission.
- Weak repeated/sequential PINs are rejected and login attempts are rate-limited.
- Existing legacy password authentication remains a migration compatibility path; it is not the new-customer UX.
- Sessions remain bearer-token based and sensitive session values remain in secure platform storage.
- PINs and OTPs must never be requested through WhatsApp, USSD support text or by staff.

## Identity and KYC evidence

KYC requires NIN, National ID front/back and a customer photo holding the ID. Automatic verification records NIN validation, liveness, face match and NIN/phone linkage separately.

- Image evidence is private and must use persistent private/object storage in production through `KYC_FILESYSTEM_DISK`.
- Do not expose private evidence paths or raw provider payloads to customers.
- Provider unavailability or inconclusive checks stays pending/manual-review; never fabricate a successful identity result.
- The mobile app uses camera access for KYC and must not add broad photo-library/storage permissions merely for convenience.
- Assisted/PWD verification may change how evidence is captured, but not the required assurance. Helpers must not handle PINs or OTPs.

## Credit profile and external data

- Credit-processing consent is explicit, versioned and revocable.
- CRB, MNO, third-party and internal components remain attributable and decomposable.
- Missing source data is recorded unavailable/error, not silently replaced.
- Credit limit is profile-level; linking multiple phones/wallets must not multiply exposure.
- Customer UI may explain the composite score and components but should not expose unnecessary internal probability-of-default or raw provider payloads.
- Production provider credentials must be environment-scoped and least-privilege.

## Money movement and provider finality

- CPay remains the production money-movement boundary unless architecture is deliberately changed and revalidated.
- Only verified customer wallets can be explicitly selected for payout/repayment; ownership is enforced server-side.
- Client repayment initiation uses idempotency keys.
- Offer acceptance is tied to an immutable disclosure hash.
- A provider acknowledgement or pending collection/disbursement is not accounting finality. Customer balances change only through the existing provider-success, ledger and reconciliation controls.
- Callbacks remain authenticated, replay-safe and auditable.

## Web/runtime protections

- Production web uses nonce-based script controls, anti-framing, MIME sniffing prevention, constrained referrers, HTTPS enforcement and private/no-store HTML.
- Android disables cleartext traffic and excludes application data from cloud/device-to-device backups. Release API configuration requires HTTPS.
- API authorisation, tenant isolation, consent, financial finality, CPay-only money movement and regulated account deletion remain backend responsibilities.

## Accessibility and security

Accessibility must not create secret-sharing or lower-assurance shortcuts. Screen readers, text scaling, reduced motion, plain language and assisted device positioning are compatible with the same authentication/KYC controls. Support agents may guide customers but must not ask for PINs or OTPs.

## Continuing controls

Security workflows and dependency monitoring run on pull requests/default-branch schedules as configured in `.github`. Registry/advisory failures fail closed. Security findings require investigation, not suppression.

## Operational controls requiring owner verification

Code cannot prove these settings are active. Verify separately:

1. Protect `main` with required release/security/deployment checks, reviewed pull requests, no force pushes and controlled administrator bypass.
2. Enable vulnerability alerts, private vulnerability reporting, secret scanning and push protection where available; rotate any historically exposed credentials.
3. Use least-privilege, environment-scoped provider/deployment credentials and strong authentication for privileged operational accounts.
4. Use persistent private/object storage for production KYC evidence, define retention/deletion rules and verify access logging.
5. Test backup restoration, alert delivery and incident response; confirm logs exclude secrets and unnecessary identity/financial data.
6. Verify actual deployed commit, headers, session behaviour, KYC/provider configuration, permissions and critical financial journeys after deployment without creating unauthorised real customer obligations.
7. Complete real-device accessibility testing with TalkBack/VoiceOver, large text and representative PWD-assisted KYC scenarios.
8. Arrange independent security testing before general availability and after substantial identity, authorisation, payment or lending changes.

## Incident response

Preserve restricted evidence, contain the affected path, revoke/rotate compromised credentials, assess customer and regulatory impact, fix and test, deploy through the protected process, and record cause and follow-up actions. Do not disclose customer evidence in public pull requests.
