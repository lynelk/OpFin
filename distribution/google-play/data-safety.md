# Google Play Data Safety worksheet

Updated: 18 September 2026

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

## Android permission posture

The submitted launch source requests:

- `INTERNET` – API/provider connectivity.
- `CAMERA` – direct National ID and selfie-with-ID capture.

The launch app must **not** request broad access to contacts, precise location, call logs, SMS contents, photo/video libraries or external storage for personal-loan decisioning.

OTP auto-fill uses Android SMS Retriever/app-signature support and therefore does not require `READ_SMS`.

KYC capture uses the camera directly. Do not add `READ_MEDIA_IMAGES` or `READ_EXTERNAL_STORAGE` merely to let borrowers select identity images from their gallery.

## Biometric/sensitive processing to verify in legal disclosures

The identity journey may send ID/selfie evidence to the configured identity-verification provider for liveness/face matching and NIN/phone validation. Confirm the privacy notice, processor/controller roles, retention, cross-border processing and customer rights before launch.

## Account deletion

- In app: `More` → `Privacy & account` → `Delete account`.
- External resource: verified account-deletion URL in Play Console.
- Active regulated obligations may require a pending closure case.
- Legally retained financial/KYC/audit records must be disclosed and limited to the applicable retention purpose.
