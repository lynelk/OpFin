# Financial Integrity Hardening — 2026-09-01

This note records the second production hardening pass over OpFin money movement, credit accounting, affordability and reconciliation.

## Double-entry ledger

Production ledger postings use integer minor units only. Every posting must:

- contain at least one debit and one credit;
- have strictly positive integer entry amounts;
- balance exactly by integer arithmetic;
- use active ledger accounts;
- use a transaction, entry and account currency that agree;
- fail on arithmetic overflow rather than silently wrapping.

A balanced journal is necessary but not sufficient. The financial-integrity scanner also compares expected economic account/direction/amount tuples against actual credit-disbursement journals.

## Credit disbursement accounting

### Deducted fees

For principal `P`, deducted fees `F`, and cash payout `C`:

`C + F = P`

Posting:

- Dr Loan receivable `P`
- Cr Provider disbursement cash `C`
- Cr Deferred credit-fee clearing `F`

### Financed fees

For principal `P` and financed fees `F`, the customer receives the full principal in cash and owes the financed fee separately.

Posting:

- Dr Loan receivable `P`
- Dr Credit-fee receivable `F`
- Cr Provider disbursement cash `P`
- Cr Deferred credit-fee clearing `F`

Customer repayments allocated to financed fees credit the credit-fee receivable. They do not create a second credit to fee clearing.

Provider amount, currency, direction and credit-offer identity are validated before an economic posting can be created.

## Payment idempotency

Every new money instruction requires an idempotency key. The key is bound to a canonical SHA-256 instruction fingerprint covering material provider, direction, amount, currency, party and source identifiers. Replays with a changed economic instruction are rejected.

Database uniqueness on the idempotency key and internal reference remains the final concurrency invariant.

## CPay finality and reversals

Allowed CPay transitions are explicit:

- processing/pending → processing, pending, successful, failed or reversed;
- successful → successful or reversed;
- failed → failed;
- reversed → reversed.

A provider-confirmed reversal after success is therefore valid. A failed status cannot overwrite a successful or reversed finality state.

Outbound CPay reversal remains fail-closed until a certified reversal/refund request contract is configured. Attempting an unsupported outbound reversal raises an error and does not mutate the original successful payment.

Webhook provider and merchant references are resolved independently. If they identify different OpFin transactions, the callback is rejected rather than accepted ambiguously.

## Reconciliation

Provider finality and internal economic settlement are separate states.

A successful credit disbursement is marked `matched` only after the loan, exact repayment schedule and immutable ledger posting have been completed. A clean disbursement reversal becomes `matched` only after the append-only reversal posting and schedule voiding have completed.

A successful repayment becomes `matched` after exact schedule allocation and ledger posting. A provider reversal after a repayment has already been economically posted is placed into explicit reconciliation exception because the current data model does not yet persist enough per-instalment reversal provenance to rewrite the schedule safely.

Participatory funding and asset deposits are reconciled after settlement. Late provider reversals reverse the corresponding internal settled state under row locks rather than leaving false settlement behind.

## Participatory funding

Listing rows remain locked while reservations and settlement capacity are checked. Settled commitment reversals decrement the listing funded amount exactly, and a reversal is rejected if it would make funded capital negative.

The integrity scanner requires listing funded amount to equal settled commitments and prohibits settled plus reserved funding above the target.

## Asset finance

Asset finance continues to enforce:

`0 <= deposit < asset price`

and, at approval:

`0 < approved finance <= asset price - deposit`

A reversed provider collection restores a settled asset deposit to the approved state so the governed collection can be retried instead of remaining falsely settled.

## Credit affordability

The operator-supplied obligation is no longer the authoritative minimum. OpFin computes a server-side 30-day obligation floor:

`system minimum = existing 30-day debt service + proposed 30-day debt service`

`effective obligation = max(declared obligation, system minimum)`

`DSR = effective obligation / monthly income × 100`

The production DSR threshold remains configuration-controlled. Current schedule debt from production and legacy books is separated so production loans are not double counted.

## Legacy loan formulae

Legacy origination remains production-disabled by default. Compatibility calculations now additionally require:

- positive instalment counts;
- finite integer minor-unit amounts;
- repayment amount to reconcile to the configured loan formula;
- exact integer principal allocation;
- exact total amortization after rounding adjustment;
- due dates not to exceed the contractual term end.

## Financial integrity scanner

The scanner now detects, among other findings:

- debit/credit imbalance;
- invalid direction or non-positive ledger entry;
- transaction/entry/account currency disagreement;
- postings to inactive accounts;
- missing expected credit posting or reversal;
- provider amount/currency mismatch against the immutable offer;
- balanced but economically incorrect credit-disbursement journals;
- duplicate provider references scoped to one provider;
- unreconciled successful or reversed payments;
- false long-range settlement;
- participatory funding mismatches and over-reservation;
- invalid asset-finance economics.

Corrections remain append-only where economic history has already been posted. The scanner must surface an exception rather than manufacture a balancing entry solely to make a control report green.
