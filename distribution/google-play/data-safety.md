# Google Play Data Safety worksheet

This is a verification worksheet, not a completed legal declaration. The release owner must reconcile it against the production Android build, API, SDK inventory, privacy policy and provider contracts.

| Data category | Expected purpose | Handling to verify |
|---|---|---|
| Name, phone and email | Account, authentication and support | Encrypted in transit; retention and deletion documented |
| National identity/KYC data | Identity verification, fraud prevention and legal compliance | Restricted access; lawful retention basis; provider sharing disclosed |
| Selfie/verification evidence | KYC where enabled | Collection mechanism, storage and deletion verified |
| Employment and income | Affordability and eligibility | Consent, provenance and decision use disclosed |
| Credit/CRB information | Eligibility, pricing and reporting | Explicit consent and licensed CRB/lender relationship documented |
| Financial transactions and loan records | Service delivery, accounting, reconciliation and compliance | Legal retention separated from active account state |
| Device/security diagnostics | Security, fraud prevention and reliability | SDK and log payloads inventoried |
| Support communications | Customer support and dispute resolution | Retention and access controls documented |

## Current Android permission posture

The source manifest requests internet access only. Personal-loan functionality must not add access to contacts, precise location, phone numbers, call logs, SMS, photos, videos or broad file storage without a new policy review and a necessary permitted use.

## Account deletion

- In app: `More` → `Privacy & account` → `Delete account`.
- External resource: the verified account-deletion URL entered in Play Console.
- Active regulated obligations may require a pending closure case, but the account must not remain falsely active after deletion is completed.
- Legally retained financial/KYC/audit records must be disclosed and limited to the applicable retention purpose.

