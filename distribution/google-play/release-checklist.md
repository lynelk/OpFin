# Google Play release checklist

Updated: 20 September 2026

## Product and compliance

- `[ ]` Approved Play product catalogue has no full-repayment term of 60 days or less.
- `[ ]` A 90-day-or-longer eligible term is available where that is the intended standard mobile route.
- `[ ]` Maximum term, fee-inclusive APR and representative example match the live catalogue.
- `[ ]` Legal lender/provider and licence evidence approved.
- `[ ]` Identity/KYC provider relationship and privacy disclosures approved.
- `[ ]` CRB/MNO/third-party scoring sources in use are documented and consented.
- `[ ]` Verified affordability source is operational for automatic approval.
- `[ ]` Financial features and Data Safety declarations match the submitted AAB.
- `[ ]` Privacy policy and deletion URL load without the app installed.

## Engineering

- `[ ]` Package name is `org.rotaryo.opfin`, matching the existing Core-Synergies Play listing.
- `[ ]` Version code exceeds every prior Play upload.
- `[ ]` Production API uses public HTTPS and ends in `/api`.
- `[ ]` Exact release SHA passes API, web, Flutter, security and deployment-contract gates. Record equivalent local results while GitHub Actions is disabled; do not skip the checks.
- `[ ]` Android manifest requests only permissions justified by the launch build: Internet and Camera.
- `[ ]` No READ_SMS, contacts, call-log, gallery/media or broad storage permission is present for lending.
- `[ ]` KYC private-storage configuration has been tested in the production environment.
- `[ ]` WhatsApp/USSD external capabilities are declared live only if their provider configuration is actually complete.
- `[ ]` Signed AAB checksum retained with release record.
- `[ ]` Play App Signing enabled; upload-key backup/recovery owners recorded.
- `[ ]` No keystore, key properties, service-account JSON or reviewer secret is committed.

## Accessibility/UAT

- `[ ]` TalkBack tested on the exact Android candidate.
- `[ ]` Large text tested on onboarding, KYC, Home, loan application, offer and repayment.
- `[ ]` Reduced motion verified.
- `[ ]` Low-literacy moderated UAT completed.
- `[ ]` PWD/assisted-KYC route tested without PIN/OTP sharing.

## Play Console

- `[ ]` Organisation developer identity verified.
- `[ ]` Listing copy and graphics uploaded.
- `[ ]` Reviewer credentials/navigation notes work.
- `[ ]` Internal test and Play pre-launch report reviewed.
- `[ ]` Closed-test/UAT sign-off recorded where required.
- `[ ]` Production rollout is staged with monitoring/support owners available.

## Go/no-go

Any missing licence evidence, prohibited short-term offer, incorrect APR disclosure, broken deletion/KYC path, unverified production storage/provider setup, failed release/security gate, unresolved High/Critical accessibility defect or unexplained Data Safety mismatch is a **no-go**.
