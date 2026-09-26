# Financial Intelligence operational and acceptance runbook

Status: Draft candidate; not authorised for production activation

## Release controls

Keep OPFIN_FINANCIAL_INTELLIGENCE_ENABLED false in API and Web until the complete source scope and all quality gates have been accepted. Do not create a Railway resource, enable GitHub Actions, change provider routing, assign a real subscription charge or publish an issuer licence merely to make a demonstration succeed. Existing signed-off services and source-core accounting remain authoritative.

The initial candidate is PR #132 on feat/financial-intelligence-20260926. Main and unrelated delivery branches are not changed. The local package is a source overlay, not a complete repository checkout or deployable release artifact.

## Prerequisites and observed execution

Run bash scripts/financial-intelligence-preflight.sh. It is read-only and exits 2 when prerequisites are missing; it neither installs tools nor certifies a successful build. A full checkout, locked dependencies, disposable PostgreSQL, browser test tooling and supported Flutter build environment are needed for the repository gates.

Observed local checks: PHP 8.4.23 ran 21 standalone engine tests / 590 assertions; Node 22.16.0 ran nine presentation-helper tests / 28 assertions. Both had zero failures. PHP syntax checks passed. A limited TypeScript source check used deliberately stubbed external framework boundaries and cannot be substituted for the repository's actual Next.js types or build.

The connected Codex request on PR #132 returned an account usage-limit refusal. It did not execute work and is not running. The local environment has no installed Composer/framework dependencies, PostgreSQL PDO driver, Next.js/Vitest dependencies or Flutter SDK. Full build preflight therefore failed with exit code 2. Do not turn this into a pass by skipping required gates.

## Deterministic checks

From a source checkout, run php scripts/test-financial-intelligence.php. Compile only apps/web/src/lib/financial-intelligence/presentation.ts with an installed TypeScript compiler to a temporary CommonJS output directory, then pass its presentation.js path to node scripts/test-financial-intelligence-web.mjs. These tests exercise pure arithmetic, schema handling and presentation boundaries only.

Run actual API tests, Pint, dependency audits, Web lint/typecheck/tests/production build and affected Flutter tests/compiles separately using the repository's locked versions. Test migration forward/rollback on an empty disposable database and protected-source triggers on populated test data. Never use a production database for destructive tests. Populated evidence prevents production rollback; use a reviewed forward migration instead.

## Required end-to-end exercise

Use explicitly synthetic institutions and at least two active authorised members. Register a source and its exact population. Import mismatched controls and verify that nothing is staged. Import valid controls, verify encrypted storage, then demonstrate that the maker cannot publish. Publish as the independent reviewer and inspect source totals, unknown schedules, cases, report history and user-visible freshness.

Repeat the same import and event keys; confirm no duplicated publication or case events. Change the payload under the same key and verify conflict. Attempt cross-Space paths, removed/expired members, expired entitlements and grants, unassigned collections users and platform administrators without membership. Verify denial at API and export routes, not only hidden controls.

Share a report to one recipient with expiry, revoke it, and verify access stops. Test eight loans belonging to one borrower: external aggregates must remain suppressed. Confirm source report and filtered shared report hashes are separately labelled. Attempt to mutate source facts and frozen evidence directly in the test database; verify rejection.

Use synthetic CSV statements with balance differences, duplicate/conflicting references, missing opening balances, reversals and possible salary descriptions. None may become issuer-authenticated or credit-eligible. Unknown/stale/revoked issuers must fail eligibility without accusing the customer of fraud. Upload a test PDF and verify quarantine, no parsing claims and no public original-file URL. Withdraw or expire analysis authority and verify denial.

## Still required before any launch

Implement and accept document scanning/parsing, account/source-verification evidence, governed retention/legal holds, supported customer/mobile journeys, production-like security/performance/accessibility and real integration contracts. Resolve outstanding commercial, reporting and analytical scope in the product register. Update existing manuals and endpoint indexes without deleting their prior requirements.

An absent data source, unknown financial amount, quarantined document or unexecuted test must remain visibly unknown. No mock institution, fabricated statement, seeded licence, causal NPL claim or invented profitability measure belongs in production evidence.
