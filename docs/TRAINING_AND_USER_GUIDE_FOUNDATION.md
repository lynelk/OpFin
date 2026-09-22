# OpFin training and user-guide foundation

Updated: 21 September 2026

This document defines the reusable source material for staff training, customer guides, FAQs and onboarding content. It is not a script that must be copied word-for-word. Training material should use plain British English and preserve the product/control meaning.

## Training principles

- Explain one task at a time.
- Use familiar words before technical terms.
- Show the customer what happens next.
- Never teach staff to ask for a customer's PIN or OTP.
- Treat accessibility as part of the normal service, not a separate product.
- Explain pending versus completed money movement clearly.
- Use the same labels the live application uses.
- Do not promise approval, a particular limit or instant provider success.

## Customer essentials

### Create an account

1. Enter phone number.
2. Confirm the OTP.
3. Enter first name, optional other name and last name.
4. Create a six-digit PIN.
5. Enter Home.

A customer should not be told to create a long password for the current mobile journey.

### Verify identity

Customer provides:

- NIN;
- National ID front;
- National ID back;
- photo holding the National ID.

A customer who cannot complete the normal camera step because of disability/access need can request assisted verification. Assistance does not mean sharing PIN/OTP or lowering identity controls.

### Understand the credit profile

Explain:

- **OpFin Score** — the customer-friendly composite score;
- **Available loan limit** — maximum currently available under the profile, not a guarantee of approval;
- **Amount due** — money currently due on an active loan;
- **Score details** — CRB/mobile/approved partner/internal components where available.

Do not explain internal probability-of-default telemetry as if it were a customer score.

### Apply for a loan

The application shows:

- available limit;
- amount due;
- amount requested;
- eligible repayment period;
- purpose.

The formal offer then shows the exact money/cost terms before acceptance.

### Review an offer

Train customers/staff to check:

- principal;
- amount received;
- interest amount/rate/method;
- fees and when they apply;
- total cost of credit;
- total repayment;
- repayment dates/frequency;
- default-interest/penalty terms;
- complaints procedure;
- regulated provider identity;
- credit-information reporting consent.

### Payments and receipts

A payment request is not the same as a completed payment.

After provider-confirmed completion, OpFin creates an e-receipt and may send an SMS acknowledgement. Customers can view receipts under Activity.

### Complaints

A complaint is recorded as a support case with a regulatory resolution target. Staff should:

1. give the case/reference;
2. explain the expected next step;
3. record first response and meaningful notes;
4. monitor the regulatory due date;
5. escalate before the deadline when resolution needs specialist review.

### Guarantors

Where a product requires guarantors:

- borrower manually enters no more than two contacts;
- OpFin does not scrape contacts;
- each guarantor independently confirms/rejects;
- a borrower should not collect the guarantor's confirmation code.

## Staff modules

Recommended training modules:

1. OpFin product overview and customer journey.
2. Identity/KYC and accessible assistance.
3. Credit profile, affordability and responsible explanations.
4. Formal offer/disclosure interpretation.
5. Wallets, disbursement, repayments and receipts.
6. Complaints and support SLA.
7. Credit-information reporting and customer consent.
8. Guarantor handling.
9. NPL/default-interest and collections boundaries.
10. Admin compliance centre and UMRA evidence packs.
11. Security, fraud, PIN/OTP protection and incident escalation.

## User-guide source hierarchy

When producing a guide, use:

1. current application labels;
2. `docs/LAUNCH_CUSTOMER_JOURNEY.md`;
3. `docs/UMRA_DIGITAL_LENDING_CONTROLS.md`;
4. `apps/api/docs/api/current-endpoints.md`;
5. relevant operational/UAT documentation.

Do not use dated audit/demo/checkpoint documents as current instructions.


## Canonical whole-product training baseline — 20 September 2026

The four maintained manuals under `docs/manuals/` now supersede this file as the primary task-level guidance: `OPFIN_USER_MANUAL.md`, `OPFIN_TRAINING_MANUAL.md`, `OPFIN_OPERATIONAL_MANUAL.md` and `OPFIN_UAT_MANUAL.md`. This file remains a reusable foundation for credit/regulatory teaching.

Training must now introduce **one identity, many Financial Spaces** before product-specific services. A learner should understand My Money/Personal Space, Savings Group and authorised organisation contexts; that membership does not expose Personal Space data; and that additional verification appears progressively when the activity requires it. Individuals and Savings Groups should be trained to complete normal journeys in the App without depending on Web.

Whole-product modules should cover everyday money, budgeting/goals, assets, debt/receivables, net position/safe-to-spend, savings groups, partner products and support before or alongside the existing responsible-credit modules below.


## Inclusive-finance and resilience training — 21 September 2026

Training must now cover **Build financial resilience** as a normal OpFin journey.

Customers should understand:

- financial reputation is a progress pathway based on verified identity and actual financial behaviour; it is not a second credit score;
- capability guidance is practical education and does not override affordability or product eligibility;
- programme-measurement participation is optional;
- voluntary inclusion details can be updated or withdrawn and are kept outside credit-risk decisioning;
- some programmes may have participation criteria; missing voluntary information is not silently inferred;
- alternative credit-support evidence such as salary undertakings, guarantees, receivables or warehouse receipts must be independently verified and recognised by the product policy before it can affect a financial product;
- a verified support instrument is not the same thing as an approved loan.

Staff must not tell customers that gender, disability status, refugee/displacement status or other programme-measurement attributes improve a credit score or guarantee approval.

Accessibility training should demonstrate large text, simple wording, reduced motion, high contrast and device screen-reader use. Physical-device UAT remains required before claiming support for a specific assistive technology or device.

## Impact and programme measurement

### Customer guidance

Financial-health check-ins are optional personal-finance tools. Explain the status using the displayed reasons and never describe it as a credit score, approval indicator or loan-limit signal.

Where a check-in is linked to an inclusive-finance programme:

1. confirm programme measurement consent is active;
2. confirm the customer is actively enrolled;
3. explain what will be measured and why;
4. capture only the information required for the configured programme;
5. remind the customer that programme measurement does not automatically change credit eligibility, pricing or limits.

Livelihood, dignified-work, agency/empowerment and community-finance questions must not be inserted into the standard borrowing journey merely because the platform can store them.

### Operator guidance

Programme operators configure:

- theory of change;
- indicator registry entries;
- programme indicator assignments and targets;
- authorised observations;
- partner reporting access.

Use programme-specific language as configuration. Do not hard-code external partner terminology into OpFin's core credit or personal-finance domain.

### Programme-partner guidance

Dedicated programme-partner users receive aggregate reporting only. They may review programme delivery, outcome coverage and configured indicators for programmes explicitly assigned to them. Small participant cohorts are suppressed and individual customer records are not exposed.
