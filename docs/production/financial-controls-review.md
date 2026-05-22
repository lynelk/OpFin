# Financial Controls Review

Date: 2026-05-22

## Summary

OpFin now has a production ledger foundation and successful legacy loan disbursement/repayment processing posts balanced production ledger transactions. The system is still not replacement-ready because some legacy balance and journal mutation paths remain active.

## Current Controls

- `ProductionLedgerService` rejects unbalanced ledger transactions.
- Ledger entries use integer `amount_minor`.
- `ProductionLoanLedgerService` mirrors successful legacy disbursement and repayment flows into deterministic production ledger transactions:
  - `loan.disbursement:{legacy_transaction_reference}`
  - `loan.repayment:{legacy_transaction_reference}`
- Duplicate legacy callbacks/approvals do not duplicate production ledger postings when the same deterministic reference already exists.
- Financial posting paths are wrapped in database transactions in the loan service.
- Mobile money adapter layer supports idempotency keys, normalized responses, failure states, reconciliation status, and audit logging.

## Fixed in this pass

- Authentication and callback endpoints now have explicit throttling to reduce brute-force and callback flood risk.
- Exception handling now avoids exposing stack traces through API responses.
- Production environment defaults now discourage debug logging and unencrypted sessions.

## Critical / High-Risk Gaps

| Area | Current risk | Required action |
| --- | --- | --- |
| Money fields | Legacy tables still contain decimal/string money fields. | Migrate all money storage and calculations to integer minor units. |
| Direct balance mutation | Legacy `Account` balances and `JournalEntry` rows are still mutated outside the production ledger. | Retire direct balance mutation after migration and reconciliation. |
| Reversals | Adapter structures exist, but full operational reversal workflow is incomplete. | Implement reversal approvals, ledger corrections, provider status lookup, and audit trail. |
| Failed payments | Failure states exist, but end-to-end failed payment operations are not production complete. | Add failure queues, alerts, customer/support messaging, and reconciliation handling. |
| Reconciliation | Reconciliation records exist, but provider statement matching and finance sign-off are not complete. | Implement provider file/status import and matched/exception reporting. |
| Atomicity coverage | Key loan flows use transactions, but all legacy paths have not been proven. | Audit every money-changing route and enforce transaction boundaries. |
| Idempotency coverage | New mobile money layer has idempotency; some legacy flows still rely on reference checks. | Add explicit idempotency records for all payment/disbursement/offer acceptance operations. |

## Cutover Requirement

Before production replacement:

1. Active loan balances must reconcile to the live system.
2. Opening ledger balances must reconcile to finance-approved source balances.
3. All new money-moving actions must post only through the production ledger service.
4. Every disbursement, collection, reversal, write-off, and correction must be auditable and idempotent.
5. Provider settlement reconciliation must be signed off by finance.
