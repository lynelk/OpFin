# OpFin mobile application

Status: Controlled external developer/product reference  
Updated: 23 September 2026  
Language: English (United Kingdom)

The Flutter application is OpFin's primary customer mobile experience. It presents a mobile-complete financial journey while the API remains authoritative for identity, permissions, eligibility, money, credit, provider finality, programme state, ledger and reconciliation.

## Product position

The customer proposition is to understand, manage, plan and improve money. Borrowing is one capability rather than the application boundary.

One person may access authorised Personal, Household, Savings Group and organisation Financial Spaces without creating separate identities.

## New-customer journey

`Phone → OTP → names → six-digit PIN → authenticated Home`

- Android OTP auto-fill uses SMS Retriever/app-signature support where available; manual entry remains possible.
- Legacy password compatibility is a migration concern, not the preferred mobile UX.
- A second phone is optional.

## Financial Spaces and everyday money

Individuals and Savings Groups should be able to complete normal everyday journeys in the App without Web being a hidden prerequisite.

The App supports server-authoritative Space context and financial-life features such as money/accounts, budgets, goals, assets, liabilities/receivables, net position and supported financial-health guidance.

## Identity verification

Where a selected service requires identity evidence, the App follows the configured KYC contract, including NIN and required document/selfie evidence.

The App must not request broad SMS/gallery/storage permissions merely to support personal-loan decisioning.

Customers who cannot complete normal capture because of an accessibility need can request assisted verification. Assistance never means sharing a PIN or OTP or lowering identity assurance.

## Responsible credit

The App reads server-authoritative credit profile, available-to-borrow amount, amount due/total outstanding, next due date, next action, eligible terms, formal offer disclosures and verified wallet choices.

A profile limit is not guaranteed approval. Pending provider requests are not completed payments or disbursements. Completed provider-finality-backed events may create auditable receipts under **Activity**.

## Financial resilience and inclusive finance

The App supports financial-health guidance, a financial-reputation stage that is not a second credit score, optional programme-measurement consent, due programme check-ins, reviewed translations with explicit English fallback, voluntary programme exit, alternative credit-support evidence and optional recorded-data health enrichment.

Programme/protected attributes remain outside underwriting. Programme outcomes are not causal claims by default.

Stolets remains a separate SME automation and commerce product; approved external evidence must arrive through explicit governed integration.

## Accessibility

Supported design requirements include TalkBack/VoiceOver semantics, operating-system text scaling, optional larger-text/simple-language preferences, reduced motion, high contrast, practical touch targets and assisted verification paths.

Certification-level accessibility claims require physical-device acceptance evidence.

## Android compatibility and identity

The Android build currently requires compile SDK 37, targets SDK 36 and retains Flutter's configured minimum Android version.

Camera features must remain optional for installation so devices without a rear camera are not filtered merely because the App requests camera permission.

The existing Play application ID is `org.rotaryo.opfin`. The Kotlin namespace remains `co.opfin.app`. Distribution builds must use the registered upload key.

## Development

```bash
flutter pub get
flutter analyze
flutter test
flutter run
```

Run the Android release-contract guard before distribution:

```bash
python3 scripts/verify-android-release-contract.py
```

Never place provider secrets in Flutter.

## Documentation

Start with `../../docs/CURRENT_STATE.md`, `../../docs/product/OPFIN_PRODUCT_BLUEPRINT.md`, `../../docs/manuals/OPFIN_USER_MANUAL.md`, `../../docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md`, `../api/docs/api/API_QUICK_REFERENCE.md` and `../../SECURITY.md`.

Run `make publication-check` before externally publishing product/developer documentation.
