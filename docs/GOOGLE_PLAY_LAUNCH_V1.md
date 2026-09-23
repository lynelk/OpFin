# OpFin Google Play launch — version 1.0.0

Status: Controlled internal release handover  
Updated: 23 September 2026  
Language: English (United Kingdom)

This document coordinates the Google Play release. It is not proof of publication, licensing, provider certification, lending-capital activation or Play approval.

## Canonical release materials

Use together:

- `distribution/google-play/README.md`
- `distribution/google-play/listing.md`
- `distribution/google-play/financial-features.md`
- `distribution/google-play/data-safety.md`
- `distribution/google-play/reviewer-notes.md`
- `distribution/google-play/release-automation.md`
- `distribution/google-play/release-checklist.md`
- `docs/LAUNCH_CUSTOMER_JOURNEY.md`

## Listing identity

- App name: **OpFin**
- Category: **Finance**
- Initial territory: **Uganda**
- Listing language: English (United Kingdom)
- Source release line: **1.0.0**
- Android application ID: **org.rotaryo.opfin**

Verify the signed artefact's actual version code, package identity and signing certificate before upload.

## Customer journey represented by the release

The canonical new-customer App journey begins:

**Phone → OTP → names → six-digit PIN → Home → progressive verification → eligible financial journey.**

Credit, savings, investment, protection and partner services remain subject to their actual activation, eligibility and provider/regulatory gates.

## Personal-loan release boundary

Before submitting a build that exposes personal loans:

- confirm every distributed product satisfies applicable Google Play repayment-period requirements;
- confirm approved maximum term and fee-inclusive APR from the product catalogue;
- confirm representative pricing examples independently;
- confirm legally correct lender/facilitator role and licence/provider evidence;
- confirm affordability, KYC, consent and complaints controls;
- confirm store copy and Data Safety declarations match the exact build.

Do not infer any of these values from sample configuration.

## Privacy and permissions

The submitted build must match the declared KYC capture, camera permissions, SMS Retriever behaviour, storage/backup controls and account-deletion path.

Do not add broad contacts, call-log, SMS-reading, gallery or storage access merely for credit decisioning.

## Release evidence

The exact release candidate should have the applicable repository test/security/deployment evidence or an explicitly approved equivalent release record where a particular automation path is unavailable.

A successful source deployment is not automatically a signed mobile release or Play publication.

## External gates

The repository cannot supply legal approvals, provider contracts/credentials, final Play declarations, reviewer credentials, signed artefacts, app-store review decisions or physical-device accessibility evidence.

Record those in the release checklist and Play Console from approved source evidence.
