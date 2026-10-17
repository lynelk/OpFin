# OpFin institutional ECL: finance and engineering acceptance register

**Status: NOT YET VERIFIED FOR PRODUCTION.** This register is a gate, not evidence of completed tests or actual bank balances.  
Reference: draft PR #182, branch `feat/institutional-ecl-ownership-20261015`.  
Prepared: 17 October 2026.

## Source evidence completed in branch

| Engineering control | Source | Verification status |
| --- | --- | --- |
| Maker/checker offer-level legal risk owner prior to funds movement | `InstitutionalExposureAccountingService`, offer ownership migration, pre-acceptance hook | Committed, runtime test pending |
| Platform/partner/shared ownership classification and loan attestation | `InstitutionalExposureAccountingService::attachLoan`, immutable loan ownership | Committed, runtime test pending |
| No partner receivable or income in canonical platform GL | `ProductionLoanLedgerService`, `ProductionLedgerService`, fee/interest guards | Committed, end-to-end reconciliation pending |
| Agency-cash reservation and held-funds liability protection | `partner_ecl_cash_reservations`, row-locked partner balance check | Committed, PostgreSQL concurrency acceptance pending |
| Provider-matched funding receipts and independently approved opening partner cash | `institution_ecl_funding_receipts`, matched money-movement checks, approval/posting service | Committed, actual provider statement and financial UAT pending |
| Partner receipts, collections and provider-confirmed reversals | `ProductionLoanLedgerService` | Committed, provider/bank proof pending |
| Shared/guarantee postings blocked until contract-specific book is approved | `InstitutionalExposureAccountingService`, `ProductionLedgerService` | Fail-closed candidate; shared production functionality not ready |
| Institution-specific scenario policy, external MIS source and monthly ECL snapshot | `InstitutionalEclCalculator`, `InstitutionalEclService`, API | Committed, model validation pending |
| Source integrity, duplicate and unmatched file control totals | CSV batch service, immutable database evidence | Committed, SQLite/PostgreSQL migration tests pending |
| Versioned external MIS corrections and advisory restatements | source correction table and `restates_run_id` | Committed, finance-period acceptance pending |
| Cross-institution finance-record access | `InstitutionalEclController::requireInstitution` | Committed, security regression test pending |
| Loan/portfolio CSV reporting | controller and authenticated Web export | Committed, end-to-end export test pending |

## Executed test and source-verification evidence (17 October 2026)

All activity below was conducted against **a temporary, isolated checkout of PR #182**, never against production records or infrastructure.

| Check | Executed evidence | Outcome |
| --- | --- | --- |
| PHP syntax | Native PHP 8.4 `php -l` on 16 originally modified PHP files, plus source parse on core revised files | **16 passed, 0 errors** initially; all five edited PostgreSQL migration files separately passed native lint |
| Laravel institutional ECL (SQLite) | `php artisan test --filter=InstitutionalEclWorkflowTest` | **7 passed, 41 assertions** |
| Laravel finance sign-off (SQLite) | `php artisan test --filter=FinancialSignOffConsolidationTest` | **10 passed, 51 assertions** |
| Entire Laravel API suite (SQLite) | `php artisan test` on PHP 8.5 + GD isolated QA runtime | **620 passed, 23,264 assertions, 0 failures** |
| Web typecheck | `npm run typecheck`, Node **22.23.3** | **Passed** |
| Web lint | `npm run lint`, Node **22.23.3** | **Passed** |
| Web unit suite | `npm run test`, Node **22.23.3** | **68 passed across 11 suites** |
| Web build | `npm run build`, Node **22.23.3** | **Passed** |
| PostgreSQL 16 governance | Both institutional ECL and financial sign-off suites in one ephemeral, non-exposed PostgreSQL 16 database | **17 passed, 92 assertions**, confirmed |
| Repeat PostgreSQL schema rebuild | Two separate suite invocations against the same isolated database | **Two consecutive runs passed**, 7 tests and 41 assertions each; same PostgreSQL instance and repeat schema reset |

**Found and repaired during actual testing:**
- An invalid PHP namespace in an ECL test, corrected and native-linted.
- An invalid TypeScript helper call in the provisioning form, corrected.
- An incompatible frontend tooling combination (TypeScript 7 versus supported eslint parser), pinned to TypeScript 6.0.2 and regenerated the npm lockfile.
- Financial test fixtures incorrectly required a live external payment provider. They now explicitly use the test-only mock adapter.
- The portable PHP QA image lacked GD (used by a pre-existing image upload test). An isolated container was built with GD; the full application suite passed without modifying the test.
- The PostgreSQL test reset uncovered reusable PL/pgSQL audit functions created with `CREATE FUNCTION`. Five migrations now use `CREATE OR REPLACE FUNCTION` to preserve the protections while allowing schema reconstruction. The combined PostgreSQL suite passes after this correction.
- Treasury review found an opportunity to double-allocate a single provider receipt and to reserve lender funds despite insufficient cash on the *selected* payout provider. Fixed with unique provider references, provider-specific ring-fenced accounts and reservations, and dedicated regression tests.

**Scope and evidential limitations:** SQLite tests and serial PostgreSQL tests do **not** establish simultaneous-transaction concurrency, source-to-bank reconciliation or external entity financial statement correctness. Tests use synthetic accounts and money movements. No real institutional source statement, verified external provider prefunding, customer consent dossier or executed risk-participation contract was supplied. This is engineering regression evidence, not independent audit or IFRS 9 financial sign-off.

**Safety of the QA environment:** The checkout was under `/tmp`; test payment providers were mock-only; PostgreSQL ran on an internal Docker network with no published host ports and tmpfs storage, with automatic cleanup. GitHub Actions was not enabled and nothing was merged or deployed.

## Extended institutional financial-assurance engineering (17 October 2026)

The follow-on draft PR implementation now includes `InstitutionalFinanceAssuranceService`, the `/admin/institutional-assurance` restricted API and a finance evidence dashboard.

**Actual source records:** Google Drive access did not return usable institution-specific current bank, loan or contract files. Historic general-ledger correspondence for a different reporting period cannot substitute for current institution-specific source statements. **No real bank-to-lender reconciliation, independent model approval or human Finance/Risk/Treasury sign-off has been completed.** The interface records source declarations and human attestations when authenticated originals become available. A user-typed reference or file digest is not a bank-verified statement.

**Accounting treatment:** A documented legal form and proposed IFRS 9 treatment can be independently reviewed as direct joint-creditor lending, funded participation, financial guarantee or first-loss exposure. The application still refuses all live shared-risk originations. Contract review cannot authorise the full share of receivables, interest, funding, fees, ECL or write-offs without separate executable finance journals and signed testing.

**Concurrency evidence:** A purpose-built PostgreSQL 16 harness executed 20 distinct institution funding rounds with two independent, overlapping withdrawal processes per round (40 simultaneous competing withdrawal attempts overall). Exactly one 80,000-minor-unit debit posted against each original 100,000-minor-unit fiduciary balance; the other was rejected. **20 of 20 rounds passed with no negative liquidity or funds-held liability.** The harness is guarded to fail outside a database explicitly named `opfin_qa`, testing environment and `OPFIN_QA_ALLOW_CONCURRENCY=1`. It does not claim multi-provider callback or customer offer reservation concurrency equivalence. No production provider was used.

**Source validation controls added:** comparison of each provider's bank balance to the matching institution/provider ring-fenced cash asset as-of the specified cut-off, comparison of external lender payable to partner liability, and comparison of lender loan count/principal to a finalised external ECL source population. Every mismatch is retained and independently reviewed; a checker cannot approve mismatched source. Reviews require distinct human IDs, per-capacity evidence references and signed statements; source facts are append-only in SQLite and PostgreSQL.

See the accounting classification and acceptance matrix: `docs/finance/INSTITUTIONAL_RECONCILIATION_AND_SHARED_EXPOSURE_DECISION.md`.

## Required execution evidence

### 1. API and database

In an isolated source checkout, respecting the repository's documented environment:

```bash
cd apps/api
composer install --no-interaction
php -l app/Services/InstitutionalExposureAccountingService.php
php -l app/Services/InstitutionalEclService.php
php -l app/Services/ProductionLedgerService.php
php -l app/Services/ProductionLoanLedgerService.php
php -l database/migrations/2026_10_15_000100_create_institutional_ecl_controls.php
php artisan test --filter=InstitutionalEclWorkflowTest
php artisan test --filter=FinancialSignOffConsolidationTest
php artisan test
```

PHPUnit is configured with SQLite in-memory by default. Run a separate isolated PostgreSQL migration and behavioural suite with real transactional locks (SQLite cannot prove PostgreSQL row-lock serialisation). Confirm DB foreign keys, index uniqueness, immutability triggers, maker/checker concurrency and rollback behaviour.

### 2. Web, API and browser

```bash
cd apps/web
npm ci
npm run typecheck
npm run lint
npm run test
npm run build
```

Requires Node 22, matching the declared package manager. Verify authorised institution selection, offer owner approval, partner cash-rejection messages, source uploads, multi-batch controls, ECL report approval, CSV downloads, formula injection protection, correct data classifications, mobile layout and 403/404 isolation for all endpoints.

### 3. Finance reconciliation and lender-by-lender sign-off

For each legal reporting entity, evidence:

- Executed funding/credit-risk contract with lender-of-record and booked entity; split funded loans, servicing agency, guarantees and participations.
- Opening bank/wallet fiduciary receipts by currency **matched to bank/provider statements**. Partner funds liability must reconcile before any payout.
- Cut-off gross principal, accrued interest and fees, partner cash, partner payable, disbursement and collection references, third-party lender ledger, and OpFin subledger/GL.
- Opening ECL + P&L movement + derecognitions/write-offs + recoveries + other movement = closing ECL for each institution/currency.
- No platform income from partner contractual interest or fee flows without a separate legally earned servicing fee.
- Historic platform loan-receivable contamination reviewed, and correction posted as immutable separate journals with an independently signed accounting memo.
- Source revision and restatement evidence, including explicit review of previous accounting-period close; no silent backdated entries.
- IFRS 9 / institution accounting basis: EIR cash-flow timing, EAD, SICR, stage 3 net interest, calibrations, scenarios, security/recoveries, and external auditor methodology validation. Simplified ECL numbers are not automatically accounting-compliant. See IFRS 9 paragraphs 5.5/B5.5 and 2026 IMF technical note on expected credit losses.

### 4. Explicit release blockers

1. Institutional prefunding ingestion now has code that accepts only successful, reconciled, statement-matched and not-yet-posted provider transactions specifically designated for institutional funding. Its actual Cito/provider feed, independently verified legal sender/source, test posting, funding reconciliation and live acceptance are **not yet proven**. Unverified money remains blocked.
2. Shared participations and financial guarantee recognition must be implemented after signed legal and Finance classification. They currently remain advisory/blocked.
3. SQLite, isolated PostgreSQL and Web application test runs have been performed and passed as above. **Still outstanding** are independently executed PostgreSQL concurrency stress checks, finance model calibration, lender-ledger reconciliation, provider evidence acceptance and the authorising sign-offs.
4. Corrective journalling for earlier misclassified partner-funded principal remains specific to executed agreements and confirmed historical GL. Automatic guessing is prohibited.

**Sign-off record (not yet completed):** Engineering QA — pending. Security — pending. Treasury — pending. Credit Risk — pending. Finance/IFRS — pending. Compliance — pending. Production release — pending.

Do not turn this register into a claim of certification without attaching signed source evidence and executed results.
