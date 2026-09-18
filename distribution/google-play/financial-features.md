# Financial features declaration and evidence

Updated: 18 September 2026

## Declare accurately in Play Console

- Personal loans: **Yes**.
- Loan facilitator or direct lender: select the legally correct role for the launch entity/product.
- Savings, investments, insurance, peer finance, SACCO/community capital or asset finance: declare only if enabled in the submitted build and supported by the actual regulated/provider arrangement.
- Money movement: describe CPay's role accurately; do not imply OpFin itself is a licensed payment-system operator without evidence.

## Personal-loan disclosures

The store listing and in-app offer must disclose the legally required terms for the actual launch product, including:

- minimum repayment period: more than 60 days;
- standard mobile routing preference: 90 days or longer where eligible inventory exists;
- maximum repayment period: `[CONFIRM FROM APPROVED PRODUCT CATALOGUE]`;
- maximum fee-inclusive APR: `[CONFIRM FROM APPROVED PRODUCT CATALOGUE]`;
- representative example showing principal, amount received, interest, mandatory fees, total repayment, frequency and final due date; and
- lender/legal provider identity and relationship to OpFin.

The current app retrieves eligible repayment options from the API rather than maintaining hard-coded customer terms.

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

- `[ ]` Legal entity registration documents.
- `[ ]` UMRA/other applicable lender licence or written basis for operating under a named licensed lender.
- `[ ]` Signed agreement with each lender/provider represented in the app.
- `[ ]` Product approval confirming every Play-distributed loan has full repayment later than 60 days.
- `[ ]` Approved pricing table and independently checked APR examples.
- `[ ]` Public privacy policy matching KYC images, scoring sources, wallet/payment processing and retention.
- `[ ]` Production support and complaints contacts.
- `[ ]` Data-controller/processor, CRB and identity-processing wording reviewed for Uganda.
- `[ ]` Accessibility/PWD assistance path operational.

Do not use screenshots or reviewer credentials that expose a prohibited short-term product, fabricated approval, real person's KYC evidence or unlicensed provider.


## Additional digital-credit evidence

Before production submission verify:

- the applicable authorised credit-reference provider/schema and its contract/certification;
- explicit electronic customer consent for positive/negative credit-information reporting;
- complaint procedure/contact information shown with the offer;
- default-interest/NPL policy controls and evidence;
- transaction receipt/instant acknowledgement path;
- direct rate edits blocked and prior UMRA approval evidence required for interest-rate changes.
