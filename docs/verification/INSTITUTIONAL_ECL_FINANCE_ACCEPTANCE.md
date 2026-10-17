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
| Partner receipts, collections and provider-confirmed reversals | `ProductionLoanLedgerService` | Committed, provider/bank proof pending |
| Shared/guarantee postings blocked until contract-specific book is approved | `InstitutionalExposureAccountingService`, `ProductionLedgerService` | Fail-closed candidate; shared production functionality not ready |
| Institution-specific scenario policy, external MIS source and monthly ECL snapshot | `InstitutionalEclCalculator`, `InstitutionalEclService`, API | Committed, model validation pending |
| Source integrity, duplicate and unmatched file control totals | CSV batch service, immutable database evidence | Committed, SQLite/PostgreSQL migration tests pending |
| Versioned external MIS corrections and advisory restatements | source correction table and `restates_run_id` | Committed, finance-period acceptance pending |
| Cross-institution finance-record access | `InstitutionalEclController::requireInstitution` | Committed, security regression test pending |
| Loan/portfolio CSV reporting | controller and authenticated Web export | Committed, end-to-end export test pending |

## Actual results available now

- **Static repository review:** 14 PHP files were fetched from the PR branch; the source brackets and PHP opening delimiters were structurally balanced.
- **Route wiring:** all 18 institutional ECL controller routes had a matching controller method in the branch.
- **PHP interpreter / Laravel tests:** **not run**. Source-only reviews are not PHP lint, PHPUnit or transaction acceptance.
- **PostgreSQL and SQLite migrations:** **not run**.
- **Web scripts:** package declares `npm run typecheck`, `npm run lint`, `npm run test`, `npm run build`; **not run**.
- **GitHub Actions / CI:** deferred under the repository owner's existing instruction; **not activated**.
- **Real financing partners, custodian bank funds, tax, fee-sharing agreements and institution auditor sign-off:** **not available for validation**.
- **Deployments:** no staging/production deployment performed, no new Railway resources provisioned.

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

1. Independent custodian/provider **actual cash receipt posting** for partner prefunds must be integrated and evidenced. The code intentionally blocks partner payouts without a credit balance; it cannot create money from a mandate.
2. Shared participations and financial guarantee recognition must be implemented after signed legal and Finance classification. They currently remain advisory/blocked.
3. An actual PostgreSQL and application test run, independent test results, finance model calibration, lender-ledger reconciliation and approval are indispensable.
4. Corrective journalling for earlier misclassified partner-funded principal remains specific to executed agreements and confirmed historical GL. Automatic guessing is prohibited.

**Sign-off record (not yet completed):** Engineering QA — pending. Security — pending. Treasury — pending. Credit Risk — pending. Finance/IFRS — pending. Compliance — pending. Production release — pending.

Do not turn this register into a claim of certification without attaching signed source evidence and executed results.
