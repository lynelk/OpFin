# OpFin Google Play listing pack

Updated: 20 September 2026

This folder is the controlled source for the next update to OpFin's existing Google Play listing, package `org.rotaryo.opfin`, version line `1.0.0`, and the production Android channel `play_store`. Play Console confirmed this identity on 20 September 2026 under the **Core-Synergies** developer account. Uganda is the intended launch market; review the existing track's country selection before publishing.

GitHub Actions must remain disabled at the owner's instruction. Use the local signing procedure in `release-automation.md`. An approved privacy-policy or Data safety change is not a new Android build or a production rollout.

The pack does not assert that a licence, provider approval, funding line, production KYC provider or payment capability exists merely because source code supports it. Verify every operational dependency before submission.

## Launch journey represented by this pack

**Phone → OTP → names → 6-digit PIN → Home → identity verification → credit profile/limit → loan request → formal offer → verified-wallet disbursement → repayment**

A second phone is optional. Identity verification captures NIN, National ID front/back and a photo of the customer holding the ID. The Android build requests camera access for this purpose and does not require broad SMS, contacts, call-log, gallery or storage access.

Primary launch navigation is **Home | Borrow | Activity | More**. Provider/regulator-gated savings, investments, peer finance, insurance, SACCO/community capital and asset-finance capabilities must not be represented as live in the listing unless they are actually enabled in the submitted build and operating arrangement.

## Contents

- `listing.md` – English (Uganda) store copy.
- `financial-features.md` – finance declaration/evidence checklist.
- `data-safety.md` – data inventory and permission worksheet.
- `reviewer-notes.md` – reviewer access/test path.
- `asset-manifest.md` – artwork and screenshot plan.
- `release-checklist.md` – accountable submission sequence.
- `release-automation.md` – protected signing/release setup.
- `assets/` – reproducible icon and feature graphic.

## Lending term rule

Do not upload an AAB until the production product catalogue contains an eligible term of at least 61 days for full repayment. The mobile route prefers a 90-day-or-longer term when one is available and rejects any selected store term under 61 days.

## Source of truth

Read `docs/LAUNCH_CUSTOMER_JOURNEY.md`, `docs/GOOGLE_PLAY_LAUNCH_V1.md`, `SECURITY.md` and the exact signed-candidate UAT evidence before submission.
