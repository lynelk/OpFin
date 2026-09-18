# OpFin Google Play launch: version 1.0.0

Updated 18 September 2026 for the unified launch borrower journey. This is a release handover, not proof of Google Play publication, provider certification or activation of lending capital.

## Canonical launch materials

Use `distribution/google-play/README.md` and its linked listing, financial-features, data-safety, reviewer-notes, release-automation and release-checklist documents together with `docs/LAUNCH_CUSTOMER_JOURNEY.md`.

- App name: **OpFin**
- Category: **Finance**
- Initial territory: **Uganda (UG)**
- Listing language: English (United Kingdom), en-GB
- Source release line: **1.0.0**
- Android application ID: **co.opfin.app**

Verify the signed artifact's actual build/version code, package identity and signing certificate before upload.

## Launch customer journey

The mobile journey is:

**Phone → OTP → names → 6-digit PIN → Home → National ID verification → credit profile/limit → loan request → formal offer → verified-wallet disbursement → repayment.**

Registration no longer asks a new customer for a long password or forces a second login. A second phone is optional.

Home prioritises amount due or available-to-borrow, then score and the next action. Launch navigation is **Home | Borrow | Activity | More**. Provider-gated savings, investments, peer lending, insurance, SACCO/community capital and asset-finance capabilities should not crowd the initial borrower experience until activated.

## KYC and Android permissions

KYC captures:

- NIN
- National ID front
- National ID back
- Photo of the customer holding the ID

Automatic verification records NIN, liveness, face-match and NIN/phone-link results. Inconclusive/provider-unavailable results stay pending/manual review.

Android requests camera access for direct KYC capture. Do not add broad photo-library/storage permissions for this journey. OTP auto-fill uses SMS Retriever/app signature rather than broad SMS-read permissions.

Production KYC evidence must use a private persistent/object storage disk configured by `KYC_FILESYSTEM_DISK`.

## Lending disclosures and store terms

The backend retains the minimum **61-day** full-repayment restriction for store-distributed personal loans and prefers eligible **90-day-plus** routes.

The customer Loan Application page uses the server-authoritative available limit and eligible terms. Before acceptance, the formal offer shows:

- amount received;
- interest;
- mandatory fees;
- total repayment;
- duration/frequency;
- equivalent APR where required;
- first and final payment timing; and
- offer expiry.

Offer acceptance uses the disclosure hash and a verified payout wallet. Pending provider disbursement is not displayed as completed.

Before publication, verify the actual legal lender/provider, approved terms, fee-inclusive maximum APR and representative example against the live operating arrangement and Play declarations.

## Accessibility

The release supports system text scaling, an additional large-text option, TalkBack semantics, reduced motion and simple wording. Assisted identity verification can be requested where disability/access needs prevent normal camera use. Assistance does not weaken KYC and neither helpers nor support staff should handle customer PINs or OTPs.

Real-device accessibility/UAT remains a required release check because compilation cannot prove a usable screen-reader or camera experience.

## Security and release gates

Read `SECURITY.md`. The exact candidate must pass `release-gate`, `security-gate` and the deployment contract. Android/iOS release compilation is not production signing or store acceptance.

No signing secret, provider credential, PIN, OTP or identity evidence belongs in source or public logs.

## Brand consistency

Read `docs/BRAND_IMPLEMENTATION.md`. Web, mobile and store assets must use the shared OpFin tokens, retained monogram and bundled typography.

Regenerate Play icon/feature assets using the repository asset script where required and visually inspect them. Marketing art is not a production screenshot.

## Public URLs

- Website: `https://opfin-web-production.up.railway.app/`
- Privacy policy: `https://opfin-production.up.railway.app/privacy-policy`
- Account deletion: `https://opfin-web-production.up.railway.app/account/delete`

Verify public accessibility and content before store submission. Do not publish an unverified support/security address.

## Production screenshots

Capture final signed-build screens using authorised test data:

1. Phone/OTP onboarding
2. Identity/KYC capture guidance
3. Home with profile/limit
4. Loan Application with available limit and amount due
5. Formal offer disclosure
6. Active-loan/repayment state
7. Accessibility/Profile controls

Record package ID, commit, version, signing-certificate digest, artifact checksum, backend environment and device. Do not create unauthorised real loans/payments merely to obtain screenshots.
