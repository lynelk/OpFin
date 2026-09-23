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

## Provider finality and reversals

Allowed governed-provider transitions are explicit:

- processing/pending → processing, pending, successful, failed or reversed;
- successful → successful or reversed;
- failed → failed;
- reversed → reversed.

A provider-confirmed reversal after success is therefore valid. A failed status cannot overwrite a successful or reversed finality state.

Outbound reversal remains fail-closed until the selected provider adapter has a certified reversal/refund request contract. Attempting an unsupported outbound reversal raises an error and does not mutate the original successful payment.

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


## 22 September 2026 financial-control remediation

The production control model now separates three independent states for external money movement:

1. **provider finality** — what CPay/provider says happened;
2. **accounting finality** — whether the expected immutable economic posting exists;
3. **statement reconciliation** — whether independent provider-statement evidence matches.

Only the statement reconciliation service can set external reconciliation to matched. Provider callbacks and product services may set accounting state but not manufacture statement evidence.

### Canonical credit economics

`CreditEconomicsService` is the single pricing/schedule calculation used for exact offer economics and affordability. The accepted offer stores its canonical schedule snapshot; servicing creates schedule rows from that snapshot instead of recalculating with a second formula.

The engine reads an approved effective-dated `regulatory_pricing` policy. Rate ceilings, fee caps, interest basis, day-count conventions, default-interest rules and early-settlement treatment are policy data, not statutory constants embedded in PHP.

### Default interest

Default interest is deterministic: outstanding principal × governed contractual rate × elapsed time, subject to effective-dated policy caps. Accrual creates a receivable and income posting. Disabling enforcement requires a current maker-checker override.

### Fee recognition and early settlement

Credit fees remain deferred until an effective-dated accounting policy releases them to income. Early settlement freezes principal, earned interest, eligible fees, default interest, policy-authorised settlement charges and rebates, then collects exactly that amount. Future unearned schedule amounts are voided only after successful provider finality.

### Platform revenue and tax

Commercial revenue events use unique occurrence identities and canonical ledger postings. Paid subscriptions activate entitlements only after a frozen invoice is successfully collected. OpFin income, partner share and tax are posted separately.

Tax calculation and EFRIS requirements are also effective-dated policy rules. EFRIS submission fails closed when enabled without configured production credentials.

### Savings and protection reversals

Post-success provider reversals now receive append-only economic reversals. Where funds have already been settled to a savings partner or insurer, the reversal creates a partner recovery receivable instead of pretending the cash is still held by OpFin.
