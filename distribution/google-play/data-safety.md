# Google Play Data Safety worksheet

Status: Controlled internal Google Play verification worksheet  
Updated: 24 September 2026
Language: English (United Kingdom)

This is a verification worksheet, not a completed legal declaration. The release owner must reconcile it against the exact production AAB, API, SDK inventory, privacy policy and provider contracts.

| Data category | Launch purpose | Handling to verify |
|---|---|---|
| Name and phone | Account creation, authentication, support | Encrypted in transit; retention/deletion documented |
| Optional second phone | Profile strengthening, wallet choice, recovery/fraud controls | Explicitly optional; ownership OTP-verified |
| NIN / National ID | Identity verification, fraud prevention, legal compliance | Restricted access; private persistent storage; lawful retention/sharing disclosed |
| National ID front/back images | KYC evidence | Camera capture; private storage; provider access/retention verified |
| Photo holding National ID | Liveness/face/identity evidence | Camera capture; private storage; assisted-access alternative available |
| Credit/CRB data | Eligibility, credit profile, pricing/decisioning/reporting | Explicit credit-processing consent; licensed relationship documented |
| MNO/mobile activity data | Optional scoring component where configured | Consent, provenance, purpose and provider contract documented |
| Approved third-party financial/income data | Affordability/eligibility where configured | Provenance and lawful basis documented |
| Loan/repayment/transaction records | Service delivery, accounting, reconciliation, compliance | Regulated retention separated from active account state |
| Device/security diagnostics | Security, fraud prevention, reliability | SDK/log payloads inventoried and minimised |
| Support/accessibility requests | Customer support and assisted identity verification | Restricted access and retention documented |
| Approximate device location | Optional foreground service discovery or customer-selected location context | Purpose-specific consent; never a credit-decision input; no background capture |
| Selected place / manually entered address | Customer-selected group, asset, risk or claim context | Review exact coordinates/address collection, retention and provider sharing separately from device-permission scope |

## Android permission posture

The submitted launch source requests:

- `INTERNET` – API/provider connectivity.
- `CAMERA` – direct National ID and selfie-with-ID capture.
- `ACCESS_COARSE_LOCATION` – optional approximate foreground device location.

The Android app must **not** request broad access to contacts, precise device location, call logs, SMS contents, photo/video libraries or external storage. This restriction applies across the app, including non-lending asset/protection screens. Place search and manual address entry do not grant access to precise device location; their collected data still require accurate disclosure.

Source policy: https://support.google.com/googleplay/android-developer/answer/9876821 (reviewed 24 September 2026).

OTP auto-fill uses Android SMS Retriever/app-signature support and therefore does not require `READ_SMS`.

KYC capture uses the camera directly. Do not add `READ_MEDIA_IMAGES` or `READ_EXTERNAL_STORAGE` merely to let borrowers select identity images from their gallery.

## Biometric/sensitive processing to verify in legal disclosures

The identity journey may send ID/selfie evidence to the configured identity-verification provider for liveness/face matching and NIN/phone validation. Confirm the privacy notice, processor/controller roles, retention, cross-border processing and customer rights before launch.

## Account deletion

- In app: `More` → `Privacy & account` → `Delete account`.
- External resource: verified account-deletion URL in Play Console.
- Active regulated obligations may require a pending closure case.
- Legally retained financial/KYC/audit records must be disclosed and limited to the applicable retention purpose.


## Credit-information reporting and receipts

Credit/loan performance data may be shared with the applicable authorised credit-reference mechanism only after the separate electronic `credit_information_reporting` consent is recorded and the borrower identity/data-quality gate passes.

The Data Safety declaration and privacy notice must accurately describe this sharing, its purpose and the applicable provider relationship.

Completed disbursement/repayment events generate transaction receipts containing financial references/status, but customer-facing receipts mask unnecessary phone data and do not expose raw provider/KYC payloads.
