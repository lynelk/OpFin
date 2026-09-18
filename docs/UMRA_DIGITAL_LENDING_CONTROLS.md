# UMRA digital-lending controls

Updated: 18 September 2026

This document maps the OpFin product/system controls implemented against the January 2024 Uganda Microfinance Regulatory Authority Digital Lending Guidelines. It does not claim that source code alone proves licensing or regulatory approval.

## Credit information exchange

OpFin maintains an outbound credit-information register in `credit_information_reports`.

Events include:

- origination;
- repayment;
- closure;
- delinquency/non-performing state;
- correction/reversal; and
- write-off where introduced.

Controls:

- positive and negative classifications;
- complete borrower/facility payload;
- verified-identity data-quality gate;
- separate electronic credit-reporting consent;
- payload SHA-256 hash;
- idempotent event key;
- submission due date;
- provider reference;
- retry/failure history;
- blocked-consent and blocked-data-quality states.

The adapter is provider-neutral and configured through `CREDIT_REFERENCE_REPORTING_*`. Production must configure the applicable authorised credit-reference mechanism and validate its exact schema/certification requirements before activation.

## Loan disclosures

The immutable offer disclosure now records:

- licensed entity/trading name, UMRA licence reference and business address when configured;
- principal;
- amount actually received;
- interest amount;
- interest rate, cycle and method;
- interest calculation explanation;
- access/disbursement fee breakdown;
- fee treatment and timing;
- total cost of credit;
- total repayment;
- repayment frequency;
- first/final payment timing;
- default interest rate/cycle;
- default-interest ceiling;
- complaint procedure;
- credit-information reporting disclosure;
- term-variation controls.

The disclosure hash binds customer acceptance to that exact snapshot.

## Complaints

Every customer support/complaint case records:

- case number;
- complaint category;
- creation timestamp;
- regulatory due date;
- first response;
- status/resolution evidence;
- SLA breach indicator;
- complaint-procedure snapshot.

The default regulatory resolution target is 30 days. The scheduled UMRA control processor marks overdue cases and the operations autopilot surfaces cases approaching or beyond the deadline.

## NPL/default-interest controls

Production loans track:

- non-performing date;
- principal outstanding when NPL is established;
- original disclosed interest;
- accrued default interest;
- default-interest ceiling;
- NPL recovery cap;
- whether enforcement is enabled;
- last policy evaluation time.

The default-interest ceiling is calculated as one-half of initial disclosed interest. The NPL recovery ceiling tracks principal at NPL plus recoverable interest/default-interest subject to the configured statutory cap logic.

Enforcement defaults to enabled. An authorised admin may disable enforcement for a specific loan only as an explicit operational setting; breach monitoring remains visible. Disabling the guard is not a regulatory exemption.

## Transaction receipts

Provider-confirmed disbursements and repayments create immutable `transaction_receipts` with:

- receipt number;
- type;
- amount/currency;
- loan/payment reference;
- provider/internal reference;
- status;
- evidence hash;
- issue time.

Receipt generation occurs after database commit/provider finality and queues an SMS acknowledgement. Customers can view their receipt history through `GET /api/receipts`.

## Guarantors

Guarantors are optional and only used where the product requires them.

Controls:

- borrower manually enters a maximum of two contacts;
- OpFin does not read the customer's address book;
- each contact receives an independent electronic confirmation challenge;
- confirmation/rejection and evidence reference are retained;
- pending/unconfirmed contacts do not silently become guarantees.

## Credit-term changes

Direct updates of loan-term pricing are disabled.

The governed process is:

1. authorised officer raises a `credit_term_change_request`;
2. second officer performs maker-checker approval;
3. if interest rate changes, prior UMRA approval reference/date is mandatory;
4. approved change applies only through the governed workflow;
5. existing accepted offer snapshots remain immutable;
6. customer consent remains required before modifying any already-contracted credit terms.

`LoanProductTerm` also rejects direct interest/default-interest rate mutation unless UMRA approval evidence is recorded.

## Admin UMRA reporting and books

The Compliance Centre can generate and validate:

- `umra_digital_credit_supervision`;
- `umra_credit_information_exchange`;
- `umra_books_and_records`;
- `umra_npl_interest_controls`;
- `umra_transaction_receipts`;
- `umra_term_and_guarantor_controls`;
- `consumer_protection_complaints`.

Evidence packs are:

- generated from system-of-record tables;
- validation-scored;
- SHA-256 hashed;
- retained in the regulatory-report register;
- reviewable in the Admin portal;
- maker-checker controlled before external submission.

## Scheduled controls

`opfin:umra-credit-controls` runs hourly and:

- evaluates NPL/default-interest controls;
- flags complaint SLA breaches;
- submits pending eligible credit-information reports.

`opfin:regulatory-reports` continues to generate scheduled regulator evidence packs.

## External production gates

Before describing these controls as operationally live, configure/verify:

- licensed entity legal name;
- registered/trading name;
- UMRA licence number;
- business address;
- official complaints contacts;
- applicable credit-reference reporting endpoint, token and provider;
- bureau/reporting schema certification;
- actual regulatory approval references for any future interest-rate change.

Missing configuration is intentionally not fabricated.
