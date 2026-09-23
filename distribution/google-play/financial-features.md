# Google Play financial-features declaration worksheet

Status: Controlled internal release worksheet  
Updated: 23 September 2026  
Audience: release owner, Legal/Compliance and Play Console administrator

This worksheet is publication-quality for controlled release use, but it is **not** finished public copy and must not be submitted until the factual gates below are completed from approved evidence.

## Declare accurately in Play Console

- Personal loans: **Yes**, only for the submitted build/product configuration that actually exposes them.
- Loan facilitator or direct lender: select the legally correct role for the launch entity/product from approved legal evidence.
- Savings, investments, insurance, peer finance, SACCO/community capital or asset finance: declare only if enabled in the submitted build and supported by the actual regulated/provider arrangement.
- Money movement: describe CPay's role accurately; do not imply OpFin itself is a licensed payment-system operator without evidence.

## Personal-loan disclosure gates

Before submission, obtain the approved product catalogue and record:

- minimum repayment period;
- maximum repayment period;
- maximum fee-inclusive APR;
- representative example showing principal, amount received, interest, mandatory fees, total repayment, frequency and final due date;
- lender/legal provider identity and relationship to OpFin.

Do not infer or calculate an approved maximum from repository examples. The current app retrieves eligible repayment options from the API rather than maintaining hard-coded customer terms.

## Decisioning evidence

Before submission verify and retain evidence for:

- identity-verification provider and KYC process;
- licensed CRB relationship and customer consent;
- MNO/third-party scoring sources actually used in production;
- score-component provenance and expiry;
- affordability source supplying verified income/obligation information;
- approved score/limit policy;
- configured debt-service threshold;
- manual-review/referral pathway when mandatory data is unavailable.

A displayed profile credit limit is not a guarantee of loan approval.

## Evidence required before submission

- [ ] Legal entity registration documents.
- [ ] Applicable lender licence or written basis for operating under a named licensed lender.
- [ ] Signed agreement with each lender/provider represented in the app.
- [ ] Product approval confirming compliance with applicable Play repayment-period rules.
- [ ] Approved pricing table and independently checked APR examples.
- [ ] Public privacy policy matching KYC images, scoring sources, wallet/payment processing and retention.
- [ ] Production support and complaints contacts.
- [ ] Data-controller/processor, CRB and identity-processing wording reviewed for Uganda.
- [ ] Accessibility/PWD assistance path operational.
- [ ] Applicable authorised credit-reference provider/schema and contract/certification.
- [ ] Explicit electronic customer consent for positive/negative credit-information reporting.
- [ ] Complaint procedure/contact information shown with the offer.
- [ ] Default-interest/NPL controls and evidence.
- [ ] Transaction receipt/instant acknowledgement path.
- [ ] Direct rate edits blocked and required prior regulatory approval evidence retained where applicable.

Do not use screenshots or reviewer credentials that expose a prohibited product, fabricated approval, real person's KYC evidence or unlicensed provider.
