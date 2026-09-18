# OpFin iOS / App Store release gate

Updated: 18 September 2026

## Product gates

- Launch navigation: **Home | Borrow | Activity | More**.
- New customers use phone → OTP → names → six-digit PIN.
- In-app account deletion is available under More/Privacy & account and uses PIN re-authentication.
- Mobile credit applications identify iOS distribution as `app_store`.
- Full repayment within 60 days or less is rejected for store-distributed personal-loan routes.
- Store-policy APR controls remain enforced where required by current App Store policy/configuration.
- Formal offers disclose amount received, interest method/rate, fee breakdown/timing, total cost/repayment, repayment timing, default terms, complaints procedure and regulated-provider information where configured.
- Offer acceptance separately records credit-information reporting consent.
- Completed financial events expose receipts only after provider finality.
- Provider/regulator-gated products remain hidden/unavailable unless activated.
- Production mock APIs/demo shortcuts remain disabled.

## iOS identity and build

From `apps/client`:

```bash
OPFIN_IOS_BUNDLE_ID=co.opfin.app bash tool/prepare_app_store.sh
flutter pub get
flutter analyze
flutter test
flutter build ipa --release \
  --dart-define=OPFIN_API_BASE_URL=https://opfin-production.up.railway.app/api \
  --dart-define=OPFIN_APP_STORE_P2P_BORROWING_ENABLED=false
```

Use the exact registered App ID/team/profile. The CI release-compile gate uses Xcode 26+ where required.

## App Store Connect gates

Verify:

1. live Privacy Policy URL;
2. App Privacy disclosure for identity/contact/financial/credit/transaction/diagnostic data and external sharing;
3. current age-rating questionnaire;
4. reviewer account/instructions using authorised test data;
5. legal lender/provider/licensing evidence for every enabled financial capability;
6. screenshots/descriptions that match the actual candidate;
7. account-deletion URL and in-app path;
8. credit-information reporting disclosure/consent and complaints path where applicable.

## External launch gates

Binary readiness does not activate regulated finance. Release remains blocked until the legal/lender identity, KYC/CRB/credit-reporting/affordability/payment providers, complaint contacts and any other enabled capability approvals are verified.
