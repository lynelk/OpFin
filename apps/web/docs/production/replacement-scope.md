# Web replacement/cutover scope

Updated: 18 September 2026

## Customer scope

The production web/customer experience must preserve:

- account access/recovery;
- borrower dashboard state;
- KYC and consent;
- credit profile/limit/due state;
- application/referral/decision messaging;
- formal offer/disclosure/reporting consent;
- loan account/schedule/repayment status;
- transaction receipt history;
- support/complaint entry;
- privacy/account deletion;
- accessibility support.

## Admin/operations scope

- credit review/manual referral;
- customer/loan/payment review;
- reconciliation/ledger;
- support/complaint SLA;
- credit-information exchange;
- NPL/default-interest controls;
- term-change/guarantor registers;
- regulator reports/books and records;
- audit/governance/security.

## Capability-gated areas

Savings, protection/insurance, investments, employer, community/SACCO, asset finance and participatory finance remain outside the focused launch surface unless their provider/regulatory arrangements are activated.

## Cutover acceptance

- exact candidate passes CI/security/deployment contract;
- production API contract is deployed first/with compatible web;
- mock/demo flags are disabled;
- external provider/legal gates are verified;
- core customer/admin UAT passes;
- accessibility/PWD UAT passes;
- rollback/incident/support ownership is ready.
