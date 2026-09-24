# OpFin Google Play listing pack

Status: Controlled v3 release pack; public Console publication still requires release evidence  
Updated: 24 September 2026  
Language: English (United Kingdom)

This folder is the controlled source for the next update to OpFin's existing Google Play listing for package `org.rotaryo.opfin`.

Uganda is the intended initial distribution market. Verify the current Play Console country/track configuration before publishing.

## Contents

- `listing.md` — v3 public store copy, with final contact/URL gates clearly identified;
- `financial-features.md` — controlled declaration worksheet;
- `data-safety.md` — Data Safety verification worksheet;
- `reviewer-notes.md` — confidential release worksheet without committed credentials;
- `release-automation.md` — authorised signing/build procedure;
- `release-checklist.md` — final release controls;
- `asset-manifest.md` and `assets/` — store graphics and screenshot evidence rules.

## Brand System v3

The public listing must use the OpFin Brand System v3 release-candidate direction:
- Indigo, Apricot, Warm Ivory and Periwinkle;
- retained OpFin monogram;
- Inter typography;
- **Your next step, clearer.**;
- Progress Path supporting motif;
- Financial Compass and Next Step visual language in genuine product screenshots.

The feature graphic must keep the Apricot accent below the word “clearer.” It must never behave as a strike-through.

## Product positioning

The current OpFin proposition is broader than lending: understand, manage, plan and improve money across Financial Spaces, with responsible credit and provider-gated financial services as capabilities.

The Play listing must nevertheless describe only functionality present and available in the submitted build. Do not declare an unactivated savings, investment, protection, peer-finance, SACCO/community or payment service merely because architecture/source exists.

## New-customer journey

**Phone → OTP → names → six-digit PIN → Home → progressive verification → eligible financial journey**

The Web password-compatible sign-in route is not the preferred new-customer App onboarding journey.

## Publication rule

The repository does not prove that Google Play Console has been updated. Public replacement is complete only when the listing, icon, feature graphic, screenshots and declarations are saved against the intended Play track/account and verified there.

Do not publish dummy contacts, invented APRs, unverified legal/lender status, fabricated provider activation or reviewer credentials.

Run `make publication-check` and complete `release-checklist.md` before submission.
