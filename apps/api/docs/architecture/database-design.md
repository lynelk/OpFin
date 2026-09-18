# Database Design

## Design Principles

- The database is the source of truth for financial state.
- Financial records should be append-only where possible.
- Use integer minor units for new money columns.
- Add indexes for operational lookup paths before production scale.
- Preserve auditability over convenience.

## Existing Core Areas

Current tables cover:

- Users, sessions, password reset tokens, and Sanctum personal access tokens.
- Institutions.
- OTPs.
- Loan products and terms.
- Loan applications.
- Loans.
- Loan schedules.
- Loan repayments.
- Transactions.
- Accounts.
- Float topups.
- Journal entries.
- SMS messages.
- Credit scores.
- Chats, chat messages, and knowledge sources.
- Cache and queue tables.

## Money Columns

New money columns must be integers:

- `amount_minor`
- `balance_minor`
- `fee_minor`
- `interest_minor`
- `premium_minor`
- `limit_minor`

Currency should be explicit:

- `currency_code`, for example `UGX`.

For Uganda shillings, the minor unit may be equal to the shilling amount, but the integer-only rule still applies.

## Keys and Indexes

Required lookup indexes for new financial tables:

- `user_id`
- `institution_id`
- `status`
- `created_at`
- `updated_at`
- internal reference
- provider reference
- idempotency key
- product/term ID where applicable

References should be unique where business rules require uniqueness:

- Internal transaction reference.
- Provider transaction reference per provider.
- Idempotency key per actor/action.

## State Transitions

Avoid loose status strings in new code. Prefer:

- Enum-like constants.
- Valid transition maps.
- Transition timestamps.
- Actor and reason metadata.

Financial state changes should happen in a database transaction and create an audit event.

## Ledger Model

Target ledger design:

- `ledger_accounts`
- `ledger_entries`
- `ledger_postings`
- `ledger_reversals`
- `ledger_balance_snapshots`

Rules:

- Entries are immutable.
- Postings must balance.
- Reversals point to original entries.
- Do not delete posted entries.
- Reporting reads from ledger postings, not provider callbacks.

## Audit Tables

Target audit design:

- `audit_events`
- `audit_event_changes`
- `consent_events`
- `provider_callback_events`
- `admin_action_events`

Audit records should capture actor, subject, action, correlation ID, IP/user agent where available, safe before/after values, reason, and timestamp.

## Migration Rules

- Add new migrations instead of editing deployed migrations.
- Backfill in batches for large tables.
- Avoid long locks.
- Make destructive changes reversible or provide a documented rollback.
- Add constraints after data is cleaned.
- Keep schema changes and data migrations separate for high-risk changes.

## Data Retention

Financial, KYC, consent, and audit data require explicit retention rules. Deletion requests should use pseudonymization where financial retention prevents full deletion.



## Current regulated credit-control tables

Key current tables include:

- `credit_profiles` / `credit_score_components` — composite profile and attributable scoring;
- `kyc_cases` / `consent_records` — identity and versioned consent evidence;
- `credit_offers` / `credit_repayment_schedule_items` — immutable offers and exact schedules;
- `customer_phone_numbers` / `customer_wallets` — verified contact/wallet ownership;
- `credit_information_reports` — outbound positive/negative reporting register;
- `transaction_receipts` — finality-backed receipt register;
- `guarantor_contacts` — maximum-two manual contacts and independent confirmation evidence;
- `credit_term_change_requests` — maker-checker pricing/term governance;
- `support_cases` — complaint procedure, regulatory due date and SLA evidence;
- `regulatory_report_runs` — hashed/validated regulator evidence packs.

Production loans also carry NPL/default-interest control fields such as principal-at-NPL, initial interest, accrued/cap amounts, recovery cap and enforcement state.

## Regulatory data constraints

- Do not externally submit a credit report with incomplete/unverified borrower identity.
- Do not delete regulated financial/reporting evidence merely because an account is closed; follow retention policy.
- Accepted-offer pricing remains snapshotted even if future product terms change.
- Receipt/reporting side effects occur after the authoritative economic transaction commits.
