# OpFin Google Play launch: version 1.0.0

Prepared 17 September 2026. Publication remains blocked until the evidence gates below pass. This document is launch material, not proof of a published app or enabled financial product.

## Console values

- App name: **OpFin**
- Application type: App
- Category: **Finance**
- Initial distribution: **Uganda only (UG)**
- Default listing language: English (United Kingdom), en-GB
- Version name: **1.0.0**
- Current source version: `1.0.0+16`; verify final artifact versionCode before upload.
- Source Android application ID: `org.rotaryo.opfin`; do not change it just to change the brand.

## Short description (71 characters)

Apply for credit, review loan costs and track your repayments in Uganda

## Full-description core

OpFin helps you explore credit options and keep track of your borrowing in Uganda.

APPLY WITH A CLEARER PICTURE
Browse available credit products, review the application requirements and submit your application from your phone. Follow your application's progress in one place.

UNDERSTAND YOUR OFFER
Review the amount, interest, fees, repayment period and total repayment before deciding whether to accept a loan. Choose only an offer you understand and can afford.

STAY ON TOP OF REPAYMENTS
View your loan details, check your repayment schedule and keep track of repayment information in your account.

MANAGE YOUR ACCOUNT
Review your profile, find help and access privacy and account-deletion information. You can request account deletion through the app or the account-deletion website. Some records may need to be retained to meet legal or financial obligations, as explained in the privacy policy.

BORROW RESPONSIBLY
Credit is subject to eligibility, identity verification, affordability assessment and approval. Creating an account or submitting an application does not guarantee a loan. Available amounts and terms depend on the product and your assessment. Review the agreement before accepting an offer.

The core must NOT be uploaded without the approved disclosure block below. Check each feature claim against the final signed release. Do not add savings deposits, insurance, investments, payment-provider names, guaranteed approval or instant disbursement claims unless available and verified for this release.

## Mandatory disclosure block: approval required

Record the actual legal lender; minimum AND maximum contractual repayment days; maximum fee-inclusive APR; and a representative offered product with principal, net amount received, disbursement date, every mandatory fee/tax, interest, total cost of credit, instalment dates/amounts and total amount payable. Use the actual cash flows to verify the example APR. Do not annualise a monthly rate by simple multiplication where that omits fees or timing. The approved Android product must not require repayment in full in 60 days or less. The earlier 61/90-day-plus direction is not a verified maximum term or approved price.

Do not invent rates, licence numbers, a lender identity, or a support inbox to remove a placeholder. Compliance/product ownership must approve the real numbers and licence evidence before publication.

## Public endpoints and support

- Website: `https://opfin-web-production.up.railway.app/`
- Privacy-policy candidate on the configured API domain: `https://opfin-production.up.railway.app/privacy-policy`
- Documented account-deletion URL: `https://opfin-web-production.up.railway.app/account/delete`
- Support email: **unverified; a monitored, tested mailbox is required**.

The Railway domain configuration and source routes are established; logged-out live HTTP/content verification is still required. `privacy@opfin.rotaryo.org` is a source fallback, not proof of a functioning support mailbox. No `opfin.app` URL or support address is verified by this work.

This branch aligns the Android privacy text with the configured API domain and makes it selectable. It does not claim a verified clickable browser-launch flow or a successful live policy check. Verify in-app access before submission.

## Artwork and screenshots

Keep the refined existing OpFin monogram and the current Indigo/Apricot direction. Do not use the earlier generated green leaf/bar-chart mockup sheets or invented phone UI.

Required exports:
- Icon: 512 x 512, 32-bit PNG with alpha channel, <= 1,024 KB. Do not bake in rounded corners or an external shadow.
- Feature graphic: 1024 x 500, RGB PNG without alpha.
- Phone captures: actual final release UI; target 1080 x 1920 and six captures, at least two publishable images. Preserve UI proportions. Do not use web previews as Android screenshots.

Capture sequence: home/overview; available credit; offer costs and term; application status; repayment schedule; help/privacy/account controls. Use only an authorised non-personal demonstration account. Do not take a real loan, move money, or delete a real account for marketing captures.

Attach APK/AAB checksum, signing-certificate digest, commit, versionName/versionCode, package ID, build mode, backend environment, device/Android version and capture time. Independently confirm that the installed capture APK corresponds to the submitted production release. An arbitrary ADB capture alone does not prove this.

## Release notes (draft until release acceptance)

Welcome to OpFin.
- Explore available credit products and review loan terms.
- Apply for credit and follow your application.
- View your loan details and repayment information.
- Manage your profile and access help, privacy and account-deletion information.

## Build corrections and validation

The main-branch Android job in CI run 34918488899 failed at `lib/services/user_session.dart:13` because `encryptedSharedPreferences` is not a defined option in the selected secure-storage package. This branch uses supported Android options with migration backup protection and preserves the existing secure-storage APIs and iOS unlocked accessibility.

Added regression tests cover sensitive-field storage, logout clearing with onboarding preference preservation, and the production privacy address/deletion entry. These mocks test application routing to secure storage; they do not certify on-device encryption or migration. CI and a real Android upgrade/migration test remain mandatory.

Release gates:
1. Flutter analyze and tests pass; Android and iOS compile gates pass.
2. Produce the actual production-signed Android release. The current Gradle CI fallback signs with the debug key when production signing properties are absent; that output is not a distributable production release.
3. Confirm the approved Android loan terms and the same terms at application/offer acceptance on the backend.
4. Approve lender/pricing/licence disclosures and a monitored support inbox.
5. Confirm homepage/privacy/deletion GET pages logged out; verify account deletion on a controlled test account, including active-obligation handling.
6. Capture real release screenshots and review every visible product and data claim.
7. Complete Play Console app access, Data safety, Financial features, audience/rating and licence declarations. Set Finance and Uganda only, then upload the verified signed AAB.

## Primary references

- https://support.google.com/googleplay/android-developer/answer/9866151
- https://support.google.com/googleplay/android-developer/answer/9876821
- https://support.google.com/googleplay/android-developer/answer/13327111
- https://pub.dev/packages/flutter_secure_storage/versions/11.0.0

No publication, production deployment, loan, payment, customer-account deletion, mailbox provisioning or Play Console change is performed by this document or branch.
