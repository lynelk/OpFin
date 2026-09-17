# Google Play release checklist

## Product and compliance

- `[ ]` Approved Play product catalogue has no term below 61 days.
- `[ ]` A 90-day-or-longer standard term is available for the reviewer account.
- `[ ]` Maximum term, APR and representative example match the live catalogue.
- `[ ]` Legal lender/provider and licence evidence have been approved.
- `[ ]` Financial features and Data Safety declarations match the submitted AAB.
- `[ ]` Privacy policy and deletion URL load without the app installed.

## Engineering

- `[ ]` Package name is `co.opfin.app` and has not previously been claimed incorrectly.
- `[ ]` Version code exceeds every prior Play upload.
- `[ ]` Production API uses public HTTPS and ends in `/api`.
- `[ ]` CI, Flutter analysis/tests, API tests and signed AAB build pass at the release SHA.
- `[ ]` AAB checksum is retained with the release record.
- `[ ]` Play App Signing is enabled; upload key backup and recovery owners are recorded.
- `[ ]` No keystore, key properties, service-account JSON or reviewer password is committed.

## Play Console

- `[ ]` Organisation developer identity is verified.
- `[ ]` Listing copy and graphics are uploaded.
- `[ ]` Reviewer credentials and navigation notes work.
- `[ ]` Internal test and Play pre-launch report are clear.
- `[ ]` Closed test/UAT sign-off is recorded if required for the account or risk policy.
- `[ ]` Production rollout starts staged and monitoring/support owners are on duty.

## Go/no-go

Any missing licence evidence, short-term offer, incorrect APR disclosure, broken deletion path, real-money reviewer path, failed release gate or unexplained Data Safety mismatch is a **no-go**.

