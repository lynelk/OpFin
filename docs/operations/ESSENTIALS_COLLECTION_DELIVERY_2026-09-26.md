# Essentials collection implementation and release evidence

Date: 26 September 2026  
Language: English (United Kingdom)  
Status: Draft implementation branch, not a production release

## Source and dependency

Work is on `completion/essentials-execution-20260926`, based on the reviewed authority/closure work at `af4af405355cffbf5c44941ced66092b3da24f8e` in PR #118. This is not a direct change to main. Integration with main must preserve that parent branch and the Developer Centre.

## Code now present

Durable repayment instructions bind caller, advance, wallet, payer, beneficiary, amount, currency, route, environment and allocation version. Capacity remains reserved after an ambiguous response. One in-flight collection per advance is retained and enforced by a partial database index. Provider observations are stored before accounting application, and failed application can resume without resending money.

The new service is wired into the current Essentials service binding. Application/reversal persists exact schedule allocations and immutable balanced lender-servicing movement journals alongside state, obligation and mandate updates. The accepted interest-first allocation order is preserved. CPay redirects and unsafe capability paths are rejected.

Profile/summary synchronisation separates reserved finance from due debt, avoids counting a production schedule again as a legacy schedule, and only caps existing approved headroom. Recovery endpoints expose owned instruction status and permit cancellation only before submission. Unresolved collections or unapplied reversals block destructive account closure. Retained financial reconciliation can handle late reversals without reopening login access.

See [the collection API and operator contract](../../apps/api/docs/api/ESSENTIALS_DURABLE_COLLECTIONS.md).

## Executed local evidence

The exact Git blob `9348997b6e94c69d9215e466e1fd32f9b374c829` for AccountingRules was reproduced locally and its Git object hash verified. The arithmetic/state harness passed 28,830 assertions. Three additional explicit allocation-policy compatibility assertions also passed, for 28,833 assertions in total.

Syntax checks passed on the locally authored PHP files. This is not a claim that the complete remote framework application, migrations or feature tests passed. Thirteen Laravel collection tests are included but have not run in the available environment.

The local release preflight returned exit 2. The working environment lacks a full executable checkout, Composer/vendor dependencies, SQLite/PostgreSQL PHP drivers, required PHP extensions, Next.js dependencies, Flutter/Dart and PostgreSQL tools. Repository and Composer downloads failed from that environment. No production test-runner configuration or blocked deployment operation was used as a workaround.

Historical 358/380-test results belong to earlier parent candidates and do not validate this change.

## Required tests before acceptance

Run existing and new API tests on both SQLite and isolated PostgreSQL; exercise competing writers, repeated clean migrations and populated upgrades. Run changed PHP formatting, dependency audit, documentation/publication checks and the API contract compatibility review. In particular, review the draft stricter request-key constraint and the older-repayment adoption path before enabling the new binding in production.

Test preparation, lost response, duplicate/reused keys, changed wallets/routes, pending capacity, contradictory finality, accounting rollback, exact reversal, expired lender lines, retained-wallet evidence and closure after settlement. Independent approved financial review is still required.

## Not closed by this slice

The separate durable lender-drawdown, biller-settlement and lender-release lifecycle is not yet replaced. Complete activation/opening servicing accounting, adoption of older live records, approved-mandate origination compatibility and customer refunds after service reversal remain implementation/acceptance work. A repayment movement journal is not a complete origination ledger or a financial-launch certificate.

No provider activation, real collection, new infrastructure, GitHub Actions enablement, merge to main or production deployment was performed by this work. The prior credential-log incident is not marked resolved.
