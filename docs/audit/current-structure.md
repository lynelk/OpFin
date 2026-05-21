# Current Backend Structure

## Top-Level Shape

- `app/Http/Controllers`: web, auth, and API controllers.
- `app/Models`: Eloquent models for users, institutions, loans, transactions, schedules, ledger-like accounts, SMS, chats, and credit scores.
- `app/Services`: external integrations and domain services.
- `app/Console/Commands`: operational/test commands for mobile money, transaction status checks, cleanup, and knowledge indexing.
- `app/Jobs`: queued SMS job.
- `app/Scopes`: institution tenant scope.
- `routes/api.php`: mobile/API routes.
- `routes/web.php`: admin portal and web auth routes.
- `routes/console.php`: scheduled transaction status check.
- `database/migrations`: base Laravel tables plus OpFin domain tables.
- `database/seeders`: seed data for users, accounts, products, loans, transactions, and institutions.
- `resources/views`: Blade admin UI.
- `resources/js`, `resources/sass`, `public/css`: frontend assets for the Laravel web portal.
- `tests`: PHPUnit feature/unit tests.

## Routes

Public API routes:

- `POST /api/register`
- `POST /api/login`
- `POST /api/reset-password`
- `POST /api/generate-otp`
- `POST /api/verify-otp`
- `POST /api/handleCallback`
- `POST /api/airtel-callback`
- `POST /api/mtn-callback`

Protected API routes under `auth:sanctum`:

- `POST /api/logout`
- `POST /api/validate-nin`
- `POST /api/credit-scores`
- `POST /api/loan-applications`
- `GET /api/loan-applications/{user}`
- `GET /api/loan-balance/{user}`
- `POST /api/loan-applications/{id}/status`
- `PATCH /api/transactions/{id}/approve`
- `POST /api/loans/{loan_id}/repay`
- `GET /api/products`
- `GET /api/institutions`
- `GET /api/product-terms/{product}`

Web routes:

- Root redirects to login.
- Laravel UI auth routes are enabled with registration disabled.
- Public privacy policy and account deletion routes exist.
- Authenticated admin portal routes cover home, loan products, applications, loans, transactions, accounts, float management, users, institutions, SMS messages, charts, and chats.

Console schedule:

- `CheckTransactionStatus` runs every minute.

## Controllers

API controllers:

- `Api\AuthController`: mobile registration, login, OTP, password reset, logout, account deletion view action.
- `Api\LoanApplicationController`: products, terms, institutions, loan applications, balances, status update, disbursement initiation.
- `Api\LoanRepaymentController`: repayment initiation through mobile money.
- `Api\NinValidationController`: NIN validation and CRB scoring fetch/update.
- `Api\TransactionController`: transaction approval and payment callbacks.

Web/admin controllers:

- `AccountsController`
- `ChatController`
- `FloatManagementController`
- `HomeController`
- `InstitutionsController`
- `LoanApplicationsController`
- `LoanProductsController`
- `LoansController`
- `SmsMessagesController`
- `TransactionsController`
- `UsersController`
- Laravel UI auth controllers under `app/Http/Controllers/Auth`

## Models

- `Account`
- `Chat`
- `ChatMessage`
- `CreditScore`
- `FloatTopup`
- `Institution`
- `JournalEntry`
- `Loan`
- `LoanApplication`
- `LoanProduct`
- `LoanProductTerm`
- `LoanSchedule`
- `Otp`
- `SmsMessage`
- `Transaction`
- `User`

Institution-scoped models include a global `InstitutionScope` pattern. This is useful for tenant isolation, but it needs a formal policy and test suite because global scopes can hide records in some flows and still allow direct ID misuse in others.

## Services and Integrations

- `AirtelService`
- `AirtelCollectionService`
- `AirtelDisbursementService`
- `MtnMomoService`
- `CitotechPaymentService`
- `LoanService`
- `SmsService`
- RAG services: `EmbeddingService`, `PromptBuilder`, `RAGService`, `VectorSearchService`

External dependency surfaces include Airtel Money, MTN MoMo, Citotech/CPay-style payment signatures, CRB/NIN services, SMS gateways, OpenAI, and Pinecone.

## Migrations

Core Laravel tables:

- users
- password reset tokens
- sessions
- cache/cache locks
- jobs/job batches/failed jobs
- personal access tokens

OpFin domain tables:

- institutions
- otps
- loan products
- loan product terms
- loan applications
- loans
- loan schedules
- loan repayments
- transactions
- accounts
- float topups
- journal entries
- SMS messages
- credit scores
- chats
- chat messages
- knowledge sources

Schema evolution includes multiple additive migrations for transactions, accounts, users, loan schedules, product terms, credit scores, soft deletes, and knowledge source URLs.

## Boilerplate Still Present

- `README.md` is still the default Laravel README, not an OpFin backend README.
- `composer.json` still identifies the project as `laravel/laravel` with skeleton description and keywords.
- `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` are default example tests.
- `resources/views/welcome.blade.php` and Laravel starter auth views are still present.
- `opfin-backend.zip` is committed in the repository root and should not live in source control.
- Some command names are clearly test/sandbox commands and should be separated from production command surfaces.

