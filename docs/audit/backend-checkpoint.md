# Backend Checkpoint

## Scope

This checkpoint reviews the backend after the current auth, role, audit, KYC/NIN, credit application, investor-demo consent, mock affordability/decisioning, demo loan offers, loan account, repayment schedule, transaction, account, journal, and mobile money adapter work visible in this checkout.

No new product features were added. Fixes were limited to validation, role enforcement, transaction/idempotency safety, test coverage, and documentation.

The repository now includes an investor-demo vertical slice for consent, affordability, and loan offers. These are explicitly demo-scoped and mock-labelled, not production-grade consent, underwriting, or offer modules.

## Verification Environment

Local PHP and Composer are not available on PATH in this workspace:

- `php --version` failed with `php : The term 'php' is not recognized`.
- `composer --version` failed with `composer : The term 'composer' is not recognized`.

Because of that, tests and fresh migrations could not be executed locally in this pass. The repository now includes additional regression tests, but they still need to be run in an environment with PHP 8.2 and Composer installed.

## Code Structure

Findings:

- API controllers exist under `app/Http/Controllers/Api`.
- Domain logic is split inconsistently between controllers, models, and `LoanService`.
- Foundation services exist for audit logging, SMS, loan processing, mobile money, and RAG/OpenAI support.
- The mobile money adapter layer is separated under `App\Services\MobileMoney` with a provider contract and mock/placeholder adapters.
- `InvestorDemoService` centralizes the demo consent, application, decision, offer, acceptance, loan creation, ledger, schedule, and sandbox disbursement flow.
- There are no dedicated `FormRequest`, API resource, policy, production consent, production affordability, production loan offer, or formal ledger service modules.
- Loan schedule generation is triggered from the `Loan` model `created` event, which hides financial side effects behind model persistence.
- `LoanApplicationController` still performs validation, eligibility checks, transaction creation, and gateway triggering in one controller.
- `LoanRepaymentController` creates repayment transactions and invokes payment gateways directly.
- Legacy web controllers and Blade views remain alongside API code.

Fixes made:

- Updated financial admin action checks to recognize current OpFin roles: `platform_admin` and `operations`.
- Moved loan application status validation before opening a manual database transaction.
- Attached sensitive-action audit middleware to loan application status updates and transaction approvals.
- Added focused checkpoint regression tests for current role enforcement and duplicate disbursement processing.
- Added mobile money adapter validation so malformed payment commands are rejected before persistence or provider dispatch.
- Added demo checkpoint tests for consent gates, consent revocation, admin snapshot access, offer acceptance replay protection, loan creation, schedules, ledger entries, sandbox mobile money, and demo audit logging.
- Fixed demo offer acceptance to lock the offer row before state transition, blocking duplicate acceptance races.
- Added explicit demo audit events for repayment schedule generation and ledger entry creation.

Remaining risks:

- Extract financial workflows into explicit services/actions before adding more product modules.
- Add policies, form requests, and resources for API consistency.
- Keep controller methods thin and side-effect-free outside application services.

## Database and Migrations

Findings:

- Migrations cover users, institutions, loan products, loan terms, applications, loans, schedules, repayments, transactions, accounts, journal entries, credit scores, audit logs, demo consent, demo decisions, demo offers, chat/RAG tables, jobs, cache, and sessions.
- Foreign keys are present on many financial tables.
- Several money columns still violate the OpFin integer minor-unit standard:
  - `loan_applications.amount` is `string`.
  - `transactions.amount` is `decimal`.
  - `loan_repayments.amount` is `decimal`.
  - `loan_schedules.principal`, `interest`, and `balance` are `decimal`.
  - `accounts.balance` is `decimal`.
  - `journal_entries.amount`, `previous_balance`, and `current_balance` are `decimal`.
- `mobile_money_transactions.amount_minor` correctly uses an integer minor-unit column.
- Demo decision and offer tables use integer minor-unit fields for requested, approved, principal, repayment, income, and obligation amounts.
- Some financial timestamp columns are stored as strings, including loan application and loan status timestamps.
- Transaction references are not currently unique in the migration history.
- The mobile money adapter table has an idempotency key for adapter commands. Legacy disbursement and repayment flows still do not have a general idempotency-key table or unique command key.
- Fresh migration execution could not be verified locally because PHP is unavailable.

Remaining risks:

- Create a dedicated migration plan to convert all money columns to integer minor units.
- Add unique indexes for financial references/idempotency keys.
- Convert financial status timestamps to nullable timestamps.
- Add indexes for high-use lookups such as user/status, loan/status, transaction/reference/status, and audit actor/subject/event.

## Financial Integrity

Findings:

- Loan creation previously guarded the `Loan::create` call with a transaction, but account/journal updates happened after that transaction.
- Successful disbursement processing could be called more than once and create duplicate loans/schedules for the same application.
- Repayment processing applies payments to schedules and creates journal entries, but ledger balancing is not enforced by a tested double-entry invariant.
- Pending duplicate repayments are blocked by checking for an existing pending repayment transaction.
- Callback handlers skip already successful transactions, but there is no general idempotency-key table.
- Mobile money adapter commands are idempotent by `idempotency_key`, duplicate webhooks are deduplicated by `webhook_event_id`, and adapter webhook processing does not create journal entries directly.
- Demo offer acceptance now uses `lockForUpdate()` and rejects replay once an offer is no longer `pending_acceptance`.

Fixes made:

- `LoanService::createLoanFromApplication` now runs loan creation, transaction association, and disbursement ledger processing inside one database transaction.
- `LoanService::createLoanFromApplication` now returns an existing loan for the same application instead of creating a duplicate.
- `LoanService::processSuccessfulTransaction` now wraps processing in a database transaction.
- Successful disbursement processing now skips work if the transaction already has a loan or if the application already has a loan.
- Successful repayment processing now skips duplicate ledger application when journal entries already exist for the transaction reference.
- Mobile money adapter requests now require a positive integer `amount_minor` and a non-empty phone number.
- Demo loan offer acceptance creates the loan account, repayment schedule, ledger entries, and mock mobile money record inside an application-level transaction.
- Demo financial state changes are audit logged for offer acceptance, loan account creation, repayment schedule generation, ledger entry creation, and disbursement recording.

Remaining risks:

- Ledger entries need a formal balanced journal model.
- Repayment idempotency should rely on a unique idempotency key, not only existing journal references.
- External gateway calls should be separated from database transactions by a command/outbox pattern before production load.

## Security and Access Control

Findings:

- Sanctum is installed and used for protected API routes.
- Core roles are defined on `User`: platform admin, operations, customer, employer admin, and support.
- `admin/foundation-check` already uses role middleware.
- Some older financial endpoints still had legacy role checks (`Admin`, `Institution Admin`) before this checkpoint.
- Callback endpoints now require `PAYMENT_CALLBACK_SECRET`.
- No live credentials were found hardcoded in config; integration config reads from environment variables.
- Some console testing commands for Airtel/MTN remain and should be restricted from production environments.

Fixes made:

- Platform admin and operations roles can now manage loan status updates and transaction approvals.
- Customer/non-admin users remain blocked from transaction approval.
- Callback shared-secret settings are documented in README.

Remaining risks:

- Move all authorization into policies or dedicated middleware.
- Extend audit logging to every admin financial state change beyond the status update and transaction approval routes covered in this pass.
- Add tests proving customers cannot access every admin/operations route.
- Restrict or remove live gateway test commands in production.

## KYC and Consent Gates

Findings:

- Loan application creation checks `nin_status === VALID`.
- NIN validation stores fields directly on the `users` table.
- Credit score retrieval currently sends `client_consented: Yes` to the CRB provider.
- Investor-demo credit application processing requires demo-scoped `credit_processing` consent.
- Investor-demo consent revocation blocks future demo credit processing.
- Investor-demo affordability and loan offers exist as mock-labelled demo records.
- No production consent model/service, production affordability module, or production loan offer lifecycle exists yet.

Remaining risks:

- Implement first-party consent records before CRB, mobile money, or employer-data processing.
- Block credit scoring and affordability checks unless a valid consent record exists.
- Make consent revocation affect future processing.
- Add dedicated affordability and loan offer modules before production credit decisions.
- Audit all KYC and consent changes.

## Tests

Existing tests:

- `FoundationApiTest` covers login, profile access, role middleware, audit creation, and health.
- `ApiSecurityTest` covers password reset OTP, token revocation, cross-user loan application access, transaction approval denial, and callback secret enforcement.

Added tests:

- `BackendCheckpointTest::test_platform_admin_can_update_credit_application_status`
- `BackendCheckpointTest::test_operations_user_can_approve_pending_transaction`
- `BackendCheckpointTest::test_successful_disbursement_processing_is_idempotent`
- `BackendCheckpointTest::test_customer_cannot_submit_credit_application_without_verified_kyc`
- `MobileMoneyAdapterTest::test_mobile_money_requires_positive_integer_minor_units`
- `MobileMoneyAdapterTest::test_mobile_money_requires_phone_number`
- `InvestorDemoSliceTest::test_customer_can_complete_investor_demo_credit_flow`
- `InvestorDemoSliceTest::test_credit_application_requires_demo_consent`
- `InvestorDemoSliceTest::test_consent_revocation_blocks_future_demo_credit_processing`
- `InvestorDemoSliceTest::test_customer_cannot_access_demo_admin_snapshot`
- `InvestorDemoSliceTest::test_demo_offer_acceptance_cannot_be_replayed`
- `InvestorDemoSliceTest::test_admin_can_view_complete_investor_demo_snapshot`

Unable to run locally:

- `php artisan test`
- `php artisan migrate:fresh --seed`
- `./vendor/bin/pint`

Reason: PHP and Composer are not installed or not on PATH in this environment.

## Fix Summary

- Corrected financial admin authorization to use current OpFin role constants.
- Prevented duplicate loan creation on repeated successful disbursement processing.
- Wrapped successful transaction processing in database transactions.
- Added checkpoint regression tests for RBAC and disbursement idempotency.
- Added checkpoint regression tests for the KYC gate and mobile money request validation.
- Added investor-demo checkpoint tests and row-lock replay protection for offer acceptance.
- Added explicit demo audit logs for schedule generation and ledger entry creation.
- Replaced Laravel boilerplate README with OpFin setup and module status.
- Added current API endpoint documentation.

## Next Steps

1. Run tests and fresh migrations in a PHP 8.2 environment.
2. Normalize legacy API response shapes.
3. Convert all financial money columns to integer minor units.
4. Replace demo consent with a production consent module and enforce consent gates before credit scoring.
5. Replace mock affordability with governed affordability policy checks.
6. Replace demo loan offers with a production offer lifecycle before binding applications to loan accounts.
7. Introduce idempotency-key persistence and unique constraints.
8. Replace model-side financial side effects with explicit application services.
9. Add policy/request/resource layers.
10. Add audit logging for all sensitive admin and financial state changes.
