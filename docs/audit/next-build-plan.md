# Next Build Plan

This plan intentionally excludes new business features until the backend is safer and easier to verify.

## Phase 1: Stabilize the Baseline

1. Replace Laravel boilerplate metadata and README with OpFin-specific setup, architecture, and operations docs.
2. Remove committed zip artifacts from source control.
3. Confirm PHP 8.2+, Composer, Node/npm, and database extensions are available in CI.
4. Run `composer install`, `php artisan test`, `npm ci`, and `npm run build` in CI.
5. Fix any failing tests or migrations before adding new features.

## Phase 2: Security Foundation

1. Add Laravel policies for User, Institution, LoanApplication, Loan, Transaction, Account, FloatTopup, LoanProduct, and Chat.
2. Replace inline role checks with policy calls.
3. Add regression tests for every cross-user and cross-institution access pattern.
4. Add route-specific rate limits for authentication, OTP, reset, NIN, credit score, loan application, repayment, and callbacks.
5. Hash OTP values, add attempt counters, bind OTPs to purpose, and consume them atomically.

## Phase 3: Payment Safety

1. Create provider-specific callback verification services for Airtel, MTN, and Citotech.
2. Store raw callback events in an append-only table with redaction rules.
3. Add idempotency keys and row locking for transaction processing.
4. Add transaction state machine tests.
5. Add reconciliation tables and exception workflow.

## Phase 4: Ledger and Audit

1. Define immutable ledger posting tables.
2. Introduce debit/credit balancing rules.
3. Generate ledger entries from loan disbursement, repayment, float topup, and adjustments.
4. Add audit logs for admin actions and financial state transitions.
5. Add reporting queries for balances, outstanding loans, repayments, arrears, and reconciliation differences.

## Phase 5: Compliance and Operations

1. Document data classification for phone, NIN, date of birth, credit score, loan, transaction, and chat data.
2. Add retention and deletion policies.
3. Add backup/restore runbook and test.
4. Add incident response runbook.
5. Add load test plan for 1,000+ users.
6. Add monitoring dashboards for API latency, errors, queue lag, provider failures, transaction status drift, and failed jobs.

## Phase 6: Feature Work Resumes

Only after phases 1-5 are green:

- Expand loan workflows.
- Add richer institution operations.
- Improve risk scoring.
- Improve user mobile experience.
- Expand reporting.

