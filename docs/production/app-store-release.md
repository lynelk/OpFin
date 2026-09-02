# OpFin iOS / App Store Release Gate

Updated: 2026-09-03

## Code gates

- Customer navigation: `Home | Borrow | Save | Grow | More`.
- In-app account deletion is available under **More → Delete account** and requires re-authentication plus explicit `DELETE` confirmation.
- Immediate deletion is used when no regulated/financial obligation remains; otherwise an account-deletion support case is created and retained obligations are shown.
- Mobile credit applications identify iOS distribution as `app_store`.
- The backend must reject iOS personal-loan routes requiring full repayment in 60 days or less.
- The backend must reject an iOS credit offer whose equivalent maximum APR including fees exceeds 36%.
- App Store credit offers disclose amount received, interest, fees, total repayment, equivalent maximum APR, repayment frequency and payment-due terms before acceptance.
- Peer-borrower origination is disabled by default in iOS builds until the lender-of-record, borrower pricing, custody, complaints and regulatory model is certified. Investor marketplace access remains separately subject to licensing/partner activation.
- Production mock APIs and demo shortcuts must remain disabled.

## iOS identity and build

The source project still contains a historical bundle identifier. Before the first App Store archive, run from `opfin-frontend`:

```bash
OPFIN_IOS_BUNDLE_ID=co.opfin.app bash tool/prepare_app_store.sh
```

Register the exact same App ID in the Apple Developer portal and create the App Store Connect record with the same bundle ID. Do not change the bundle ID after the first production release unless performing an intentional app transfer/new app migration.

Build with Xcode 26 or later and an iOS 26 SDK or later. The current Flutter release version is `1.0.0+16`.

Recommended build-time configuration:

```bash
flutter pub get
flutter analyze
flutter test
flutter build ipa --release \
  --dart-define=OPFIN_API_BASE_URL=https://opfin-api-production.up.railway.app/api \
  --dart-define=OPFIN_APP_STORE_P2P_BORROWING_ENABLED=false
```

Then archive/distribute from Xcode after selecting the registered Apple Developer organization/team and distribution signing profile.

## App Store Connect metadata gates

Before submission complete:

1. Privacy Policy URL: use the public OpFin privacy-policy page.
2. App Privacy: disclose all data collected by OpFin and integrated providers, including identity, contact, financial, credit, transaction, diagnostics and usage data where applicable.
3. Age rating: complete the current App Store Connect age-rating questionnaire.
4. App Review Information: provide a dedicated reviewer account and instructions that do not depend on real customer funds.
5. Financial-services evidence: provide the legal entity, lending/investment permissions, lender-of-record and partner/licensing evidence applicable to every financial capability exposed in the submitted build.
6. Screenshots and description must match capabilities that are genuinely active; do not advertise provider-gated features as live.

## External launch gates

App Store binary readiness does not activate regulated financial services. Submission/release remains blocked until required third-party and legal gates are satisfied, including production OTP/SMS, CPay credentials/certification, lender-of-record and peer-lending legal model, and any KYC/CRB/savings/protection/investment provider approvals required by the submitted feature set.
