# Google Play release checklist

Status: Controlled internal release checklist  
Updated: 24 September 2026  
Language: English (United Kingdom)

Complete against the exact signed candidate. An unchecked item is not silently waived.

## Brand System v3

- [ ] Brand token source is `3.0.0-rc.1` or the formally frozen `3.0.0`.
- [ ] v3 vector monogram/lock-ups are the approved sources.
- [ ] Feature graphic uses the v3 Progress Path and does not strike through “clearer.”
- [ ] Exact-candidate screenshots satisfy `brand/v3/SCREENSHOT_CAPTURE_STANDARD.md`.
- [ ] Representative visual/accessibility QA satisfies `brand/v3/VISUAL_QA_CHECKLIST.md`.
- [ ] Trademark/name status has a documented legal disposition; a preliminary Web search is not treated as clearance.
- [ ] Play Console listing/icon/feature graphic/screenshots have actually been replaced and verified.

## Product and compliance

- [ ] Submitted build exposes only financial features supported by approved legal/provider arrangements.
- [ ] Personal-loan catalogue satisfies applicable Google Play repayment-period requirements.
- [ ] Maximum term, fee-inclusive APR and representative example match the approved live catalogue.
- [ ] Legal lender/facilitator/provider role and licence basis are approved.
- [ ] Identity/KYC provider relationship and privacy disclosures are approved.
- [ ] CRB/MNO/third-party sources actually used are documented and consented.
- [ ] Verified affordability source is operational where automatic approval requires it.
- [ ] Financial Features and Data Safety declarations match the submitted AAB.
- [ ] Privacy-policy and account-deletion URLs load without the App installed.

## Publication fields

- [ ] Approved developer/legal entity inserted in Play Console.
- [ ] Primary support email verified.
- [ ] Support telephone verified.
- [ ] Public website verified.
- [ ] Public privacy-policy URL verified.
- [ ] Account-deletion URL verified.
- [ ] No dummy/placeholder values remain in submitted public fields.

## Engineering

- [ ] Package name is `org.rotaryo.opfin`.
- [ ] Version code exceeds every prior Play upload.
- [ ] Registered upload key signs the candidate.
- [ ] Production API base uses public HTTPS and ends in `/api`.
- [ ] Mock/demo shortcuts are disabled.
- [ ] Android release-contract guard passes.
- [ ] Flutter analysis/tests pass.
- [ ] Exact candidate has the applicable API/Web/client/security/deployment evidence recorded.
- [ ] Required camera features remain optional for installation.
- [ ] Sensitive backup/cleartext controls pass.
- [ ] No provider secret is embedded in the AAB.

## Customer journey

- [ ] Phone → OTP → names → PIN works on representative physical Android devices.
- [ ] Home and Financial Space context are understandable.
- [ ] Regulated services request progressive verification only when required.
- [ ] Credit limit is not presented as guaranteed approval.
- [ ] Formal offer disclosures match backend truth.
- [ ] Pending payout/collection is not presented as complete.
- [ ] Receipts appear only after confirmed financial finality.
- [ ] Programme/protected attributes remain outside underwriting.
- [ ] Account deletion works in-app and through the public Web resource.

## Accessibility and device support

- [ ] Text scaling and large-text mode tested.
- [ ] TalkBack tested on key journeys.
- [ ] Reduced-motion/high-contrast behaviour tested.
- [ ] Front-camera-only/no-rear-camera installation compatibility reviewed.
- [ ] Assisted identity-verification path tested.
- [ ] Play supported-device counts reviewed after upload.

## Reviewer environment

- [ ] Dedicated reviewer account prepared.
- [ ] Reviewer PIN supplied only in Play Console.
- [ ] Reviewer-safe OTP/test-number path documented.
- [ ] Reviewer journey cannot create an unauthorised real financial obligation.
- [ ] Test identity/financial data are authorised.

## Release decision

Do not promote the candidate with unresolved Critical/High defects or missing mandatory legal/provider/store evidence.

Record final version code, signing certificate fingerprint, source commit, AAB checksum, Play track, submission date and reviewer-release evidence in the controlled release record.
