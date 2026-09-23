# OpFin Google Play listing pack

Status: Controlled internal release pack  
Updated: 23 September 2026  
Language: English (United Kingdom)

This folder is the controlled source for the next update to OpFin's existing Google Play listing for package `org.rotaryo.opfin`.

Uganda is the intended initial distribution market. Verify the current Play Console country/track configuration before publishing.

## Contents

- `listing.md` — public store copy, with final contact/URL gates clearly identified;
- `financial-features.md` — controlled declaration worksheet;
- `data-safety.md` — Data Safety verification worksheet;
- `reviewer-notes.md` — confidential release worksheet without committed credentials;
- `release-automation.md` — authorised signing/build procedure;
- `release-checklist.md` — final release controls;
- `asset-manifest.md` and `assets/` — store graphics evidence.

## Product positioning

The current OpFin proposition is broader than lending: understand, manage, plan and improve money across Financial Spaces, with responsible credit and provider-gated financial services as capabilities.

The Play listing must nevertheless describe only functionality present and available in the submitted build. Do not declare an unactivated savings, investment, protection, peer-finance, SACCO/community or payment service merely because architecture/source exists.

## New-customer journey

**Phone → OTP → names → six-digit PIN → Home → progressive verification → eligible financial journey**

The Web password-compatible sign-in route is not the preferred new-customer App onboarding journey.

## Publication rule

Do not publish dummy contacts, invented APRs, unverified legal/lender status, fabricated provider activation or reviewer credentials.

Run `make publication-check` and complete `release-checklist.md` before submission.
