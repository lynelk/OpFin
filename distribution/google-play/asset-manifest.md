# Google Play asset manifest

Updated: 18 September 2026

## Required graphics

- 512 × 512 Play Store icon: `assets/opfin-play-icon-512.png`
- 1024 × 500 feature graphic: `assets/opfin-feature-graphic-1024x500.png`

Regenerate using the repository brand-asset process where required and retain provenance/checksums.

## Production screenshot plan

Capture screenshots from the **exact signed release candidate** with authorised test data. Do not reconstruct or generate a screen and label it as production.

Recommended phone sequence:

1. **Phone verification** – simple phone/OTP onboarding.
2. **Identity verification** – guidance showing the three required photo steps, using non-personal test data.
3. **Home** – available limit and OpFin Score, or amount-due state.
4. **Loan Application** – available loan limit, amount due, amount input and eligible repayment period.
5. **Formal credit offer** – amount received, interest, fees, total repayment and repayment timing/APR where applicable.
6. **Repayment** – outstanding amount, verified wallet choice and provider-pending wording.
7. **Accessibility / More** – larger-text/reduced-motion and account/privacy controls.

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
