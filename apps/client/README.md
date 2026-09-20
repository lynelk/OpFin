# OpFin mobile application

Updated: 18 September 2026

The Flutter application is OpFin's primary customer mobile experience. It deliberately presents a simple borrower journey while the API keeps identity, scoring, affordability, accounting, reconciliation and regulatory complexity behind the interface.

## Launch navigation

**Home | Borrow | Activity | More**

Provider/regulator-gated products may exist in the platform architecture but must not crowd the launch journey until genuinely activated.

## Account journey

`Phone → OTP → First/Other/Last names → 6-digit PIN → authenticated Home`

- Android OTP auto-fill uses SMS Retriever/app-signature support; manual entry remains available.
- New customers use a six-digit PIN. Legacy password compatibility is a migration concern, not the current UX.
- A second phone is optional.

## KYC

Required identity evidence:

- NIN;
- National ID front;
- National ID back;
- photo of the customer holding the ID.

The app uses direct camera capture and does not require broad SMS/gallery/storage permissions for personal-loan decisioning.

Customers who cannot complete normal camera capture because of a disability/access need can request assisted identity verification. Helpers/support must never ask for or handle the customer's PIN or OTP.

## Credit and borrowing

The app reads server-authoritative state from the API:

- OpFin Composite Score and understandable component detail;
- available-to-borrow amount;
- amount due and total outstanding;
- next payment date;
- next customer action.

The Loan Application screen uses the live server limit and eligible terms. It does not maintain a hard-coded loan ceiling or short-term product rules.

The formal offer displays the exact disclosure snapshot, including interest calculation, fee breakdown/timing, total cost, repayment timing, default terms, complaints procedure and regulated-provider information where configured.

Offer acceptance separately records consent for complete positive/negative credit-information reporting.

## Wallets, repayments and receipts

Only verified customer wallets can be selected for payout/repayment.

A payment request is not a completed payment. Customer balances change only after provider-confirmed finality.

Completed disbursement/repayment events create auditable e-receipts. Customers can view receipts under **Activity**.

## Accessibility

The app supports:

- TalkBack/VoiceOver semantics;
- operating-system text scaling;
- optional larger-text mode;
- reduced motion;
- simple wording;
- practical touch targets;
- assisted KYC support.

## Development

```bash
flutter pub get
flutter analyze
flutter test
flutter run
```

Production release checks also compile Android APK/AAB and iOS release targets.

API base URL is supplied through the established build/environment configuration. Never place provider secrets in Flutter.

## Documentation

Start with:

- `../../docs/LAUNCH_CUSTOMER_JOURNEY.md`
- `../../docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md`
- `../api/docs/api/API_QUICK_REFERENCE.md` via `apps/api/docs/api/API_QUICK_REFERENCE.md`
- `../../SECURITY.md`

Search all documentation from repository root:

```bash
python3 scripts/search-docs.py "repayment"
python3 scripts/search-api.py "receipts"
```


## 20 September 2026 product-surface update

Financial Spaces are now part of the canonical OpFin experience. One person may access Personal, Household, Savings Group and authorised organisation contexts without creating separate identities. Individuals and Savings Groups remain mobile-complete; Web provides enhanced analysis and institutional workspace capabilities. The customer proposition is to understand, manage, plan and improve money, with borrowing as one capability rather than the product boundary.
