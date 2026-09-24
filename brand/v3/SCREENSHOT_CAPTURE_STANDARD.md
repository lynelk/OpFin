# OpFin Release-Candidate Screenshot Standard

Status: External-evidence gate  
Updated: 24 September 2026

## Non-negotiable rule

A store screenshot is evidence of the submitted product. Capture it from the **exact signed release candidate**. Do not recreate, retouch or generate a screen and describe it as production.

## Required Android sequence

1. Phone registration / verification.
2. Progressive identity verification.
3. Home with the Financial Compass / Next Step treatment.
4. Responsible-credit eligibility or application screen, where activated for the submitted build.
5. Formal offer with principal, fees, interest/APR where applicable, tenure and total repayment.
6. Repayment / transaction state showing pending versus confirmed clearly.
7. More / privacy / accessibility / account controls.

If a provider-gated feature is not activated in the submitted build, do not create a screenshot implying that it is.

## Capture conditions

For each set record:
- source commit SHA;
- package ID;
- version name and version code;
- AAB SHA-256;
- signing-certificate fingerprint;
- API environment;
- device make/model;
- Android version;
- logical and physical screen size;
- test account/reference;
- capture date/time;
- tester/reviewer.

Use authorised test data only. No real customer's name, phone, NIN, National ID image, financial obligation, wallet details or private programme data may appear.

## Visual requirements

- no debug banner;
- no notification containing personal information;
- no clipped disclosures;
- no keyboard covering the primary task unless the keyboard is the point of the image;
- realistic but non-sensitive amounts;
- enlarged text must remain usable;
- status words accompany status colour;
- brand mark and colours match v3;
- do not add fake device chrome, fake balances or fake product states.

## Evidence directory

The final evidence package should use this structure:

`distribution/google-play/screenshots/<version-code>/<device>/01-phone-verification.png`  
… through …  
`07-more-accessibility.png`

Add an adjacent `evidence.json` with the capture metadata above.

## Acceptance

The screenshot family is complete only after a reviewer confirms that every image came from the exact candidate and that the sequence accurately represents the available product.
