# OpFin mobile application

Status: Controlled external developer/product reference  
Updated: 24 September 2026  
Language: English (United Kingdom)

The Flutter application is OpFin's primary customer mobile experience. It presents a mobile-complete financial journey while the API remains authoritative for identity, permissions, eligibility, money, credit, provider finality, programme state, ledger and reconciliation.

## Product position

The customer proposition is to understand, manage, plan, protect and improve money. Borrowing is one capability rather than the application boundary. The signed-in customer lands in the Personal context; Financial Spaces extend that financial life rather than replacing it.

One person may access authorised Personal, Household, Savings Group, Investment Club, SACCO and organisation Financial Spaces without creating separate identities.

## New-customer journey

`Phone → OTP → names → six-digit PIN → authenticated Home`

- Android OTP auto-fill uses SMS Retriever/app-signature support where available; manual entry remains possible.
- Legacy password compatibility is a migration concern, not the preferred mobile UX.
- A second phone is optional.

## Personal Home and everyday money

Home is personal-finance first. It uses the server-authoritative Financial Compass to show recorded available money/safe-to-spend, savings, debt, upcoming obligations, cash-flow context and one useful next action before presenting product choices.

**My Money** supports current cash/mobile-money/bank balances and personal debt planning. A debt recorded in the person's Personal Space contributes to the Financial Compass and, when it has a due date, the upcoming commitment view. OpFin-originated loan schedules remain server-derived; customers do not have to re-enter them manually.

Protection is available as a normal personal-finance destination. The App distinguishes premium initiation, partner settlement, insurer issuance and active cover, and routes claim decisions to the disclosed insurer/underwriter.

## Financial Spaces and everyday money

Individuals and Savings Groups should be able to complete normal everyday journeys in the App without Web being a hidden prerequisite. Investment Club members use the same App and identity. Group/SACCO administrators can use the Web Workspace for deeper administration, but member participation is not split into another consumer app.

The App supports server-authoritative Space context and financial-life features such as money/accounts, budgets, goals, assets, liabilities/receivables, net position and supported financial-health guidance. Savings Groups, Investment Clubs and SACCOs can attach external government/regulator identifiers to their existing Space record as those schemes become applicable. Group protection catalogues are visible only for approved group-capable products; group enrolment and premium collection remain fail-closed until separately activated.

## Lightweight location context

Location is optional and task-driven.

The App uses native Android/iOS foreground location rather than embedding a full Google Maps SDK. Approximate location is the default for service discovery. Fine/precise permission is requested only for a location-dependent asset, insured risk or claim task.

Customers can also search for a place through the server-side Google Maps adapter or enter a place manually. Small Static Map previews are loaded through the authenticated OpFin API so the Google server API key is never embedded in the client.

Current App entry points include:

- participating services near me;
- Saving Group / Investment Club / SACCO operating area;
- group meeting place;
- physical asset/project location;
- insured-risk location; and
- claim incident location.

Android requests ACCESS_COARSE_LOCATION first and ACCESS_FINE_LOCATION only for precise tasks. iOS requests When In Use access. Background location is not configured.

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
