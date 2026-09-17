# OpFin Google Play launch: version 1.0.0

Updated 17 September 2026 after reconciling the Android-release changes from PR #35 with the security and brand work in PR #36. This is a handover, not proof of publication or activation of a financial product.

## Canonical launch materials

Use `distribution/google-play/README.md` and its linked listing, financial-features, data-safety, reviewer-notes, release-automation and release-checklist documents. Keep them consistent with the final signed artifact. This document replaces the earlier parallel draft that named `org.rotaryo.opfin` and build 16.

- App name: **OpFin**
- App category: **Finance**
- Initial distribution territory: **Uganda only (UG)**
- Listing language: English (United Kingdom), en-GB
- Current source version: **1.0.0+17**; verify the actual artifact version code before upload.
- Current Android source application ID: **co.opfin.app**, introduced by the merged Android-release change. Confirm ownership and Play Console continuity before the first upload; never change an already published application's identity merely for branding.

## Security and release gates

Read `SECURITY.md` for required checks, continuous scans, operational responsibilities and remaining assurance limits. The original vulnerable `sharp 0.35.3` override has been replaced by the patched 0.35.4 lock. Both normal web checks and the deployment prebuild retain the dependency audit. Failures must be investigated, not ignored.

The aggregate `release-gate` must pass for the exact source commit, together with `security-gate` and the deployment contract. Android/iOS release-mode compilation is not proof of production signing or store acceptance. Use the protected Android signed-release process with the approved upload key and correct release identity; no signing secrets belong in source or public logs.

The Android API configuration now points to the configured production host and release configuration requires HTTPS. The upstream backend protections for minimum 61-day full-repayment terms and preference for eligible 90-day-plus routes are preserved. This does not establish approved lending prices, funding or a verified listing APR example.

## Brand consistency

Read `docs/BRAND_IMPLEMENTATION.md`. Web, mobile and store assets must use the shared `brand/opfin.tokens.json` direction, retained OpFin monogram and Inter typography. Do not use the earlier green generated mockup sheets or the older blue-field store artwork.

`bash scripts/build-play-store-assets.sh` regenerates the 512 x 512 Play icon and 1024 x 500 feature graphic from the same app assets and token colours. It updates their provenance hashes. Review regenerated artwork visually before uploading. A store feature graphic is marketing artwork, not an Android screenshot.

## Public URLs

- Website: `https://opfin-web-production.up.railway.app/`
- Privacy policy: `https://opfin-production.up.railway.app/privacy-policy`
- Account deletion: `https://opfin-web-production.up.railway.app/account/delete`

Configured domains and source routes are not evidence of a completed end-to-end deletion test. Verify logged-out access and test the deletion process only with an authorised non-personal test account. A monitored support inbox, approved public privacy contact and legal disclosures still require actual verification; no unverified `support@...` address should be published.

## Production screenshots and financial disclosures

Capture actual final-release screens with an authorised demonstration account: overview, available credit, offer costs, application status, repayment schedule and account controls. Record the package ID, commit, version, signing-certificate digest, artifact checksum, backend environment and device. Do not generate or reconstruct UI and label it as a production capture. Do not create a real loan, payment or customer deletion to make marketing screenshots.

Before publication, verify the legal lender, approved minimum and maximum repayment terms, fee-inclusive maximum APR and an accurate representative example with principal, amount received, interest, mandatory charges, dated instalments and total repayment. Confirm licensing, Data safety, app access and Financial features declarations against the actual release and operating arrangements.
