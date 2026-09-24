# Google Play asset manifest

Updated: 24 September 2026  
Brand system: 3.0.0-rc.1

## Required graphics

- 512 × 512 Play Store icon: `assets/opfin-play-icon-512.png`
- 1024 × 500 feature graphic: `assets/opfin-feature-graphic-1024x500.png`
- v3 feature-graphic vector source: `../../brand/v3/assets/opfin-feature-graphic-master.svg`

The v3 feature graphic uses the Progress Path as a supporting device and places the Apricot accent below the word **clearer.** so it cannot read as a strike-through.

Regenerate using the repository brand-asset process where required and retain provenance/checksums.

**Release-candidate gate:** the currently committed PNG exports must be regenerated from the v3 vector/token sources and their new checksums recorded before Play submission. The redesigned SVG source and build rule are not, by themselves, evidence that the binary submitted to Play carries the v3 artwork.

## Production screenshot plan

Capture screenshots from the **exact signed release candidate** with authorised test data. Do not reconstruct or generate a screen and label it as production.

Recommended phone sequence:

1. **Phone verification** – simple phone/OTP onboarding.
2. **Identity verification** – progressive guidance using non-personal test data.
3. **Home** – the v3 Next Step treatment and available financial-position context.
4. **Responsible credit** – available limit/application state only where activated in the submitted build.
5. **Formal credit offer** – amount received, interest, fees, total repayment and repayment timing/APR where applicable.
6. **Repayment** – outstanding amount, verified wallet choice and provider-pending wording.
7. **Accessibility / More** – larger-text/reduced-motion and account/privacy controls.

See `../../brand/v3/SCREENSHOT_CAPTURE_STANDARD.md` for the evidence contract.

## Screenshot evidence record

For every screenshot set record:

- Git commit;
- Android package ID;
- app version/build code;
- signing certificate digest;
- AAB checksum;
- API environment;
- physical/emulated device model and Android version;
- test account/reference used;
- date captured.

Screenshots must not expose a real customer's NIN, National ID image, phone, financial obligation or other personal data.
