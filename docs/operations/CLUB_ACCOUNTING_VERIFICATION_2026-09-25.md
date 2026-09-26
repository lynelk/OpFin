# Investment-club accounting: integrated verification

Status: Tested backend candidate; not a full channel or financial-launch acceptance  
Recorded: 25 September 2026  
Language: English (United Kingdom)

## Source identity

Application candidate: `1f82f821163ac7058f0e2f2b44672a45a754a166` on `completion/club-accounting-20260925`, PR #126.

The branch contains accepted main `b1686989a317619562a8592728a310fbcb8f9113`, including the Developer Centre, identity work, lender routing and governance documents. Mechanical PR #131 integrated main into this working branch; it did not release club accounting into main.

Three exact PostgreSQL baseline-fix files were copied from Essentials verification candidate `e336daf4962cb735d1ff0b2360690b1eaffab83e`: callback nonce transaction safety, grouped financial-reconciliation queries and their regressions. This narrow integration does not include all of the separate PR #118 authority/closure implementation.

## Executed verification

Validation deployment: `cc15bb1f-097d-4b85-9020-a4e133276692` in the existing Railway API build service.

| Check | Actual result |
| --- | --- |
| Existing SQLite API build | 380 tests passed; 21,852 assertions |
| Composer dependency audit | Passed |
| Developer catalogue-definition check | Passed; incomplete native contract coverage remains visible |
| API asset compilation | Passed |
| PostgreSQL version | 18 in a private synthetic fixture |
| Repeated fresh-schema migration | Two explicit passes completed before PHPUnit's own fixture setup |
| Full PostgreSQL 18 suite | `OK (380 tests, 21852 assertions)` |
| Candidate marker | `OPFIN_POSTGRES18_VALIDATION_RESULT 1f82f821163ac7058f0e2f2b44672a45a754a166 exit=0` |
| Runtime rollout from validation | Intentionally prevented after `OPFIN_DUAL_DB_PASSED_NO_RUNTIME_DEPLOYMENT` |

The test counts include the existing application suite and many integer-allocation assertions; they are not a count of newly delivered features. The same suite ran on two databases. Do not add the counts together and call them distinct tests.

The verifier used an allow-listed synthetic environment and a private Unix-socket PostgreSQL fixture with no TCP listener. It did not use production data or credentials, provision another managed Railway resource, activate a provider or execute real financial transactions. It stops the fixture and exits 23 after success, so Railway's validation-deployment status is not itself a production success signal.

## Corrections established during integration

The integration retains both the Developer Centre and club-accounting providers rather than dropping one side of the registration conflict.

The club migrations now use `CREATE OR REPLACE FUNCTION` for their two standalone PostgreSQL trigger functions. Table-only fresh resets can leave those functions in place; reinstallation must preserve the identical protections rather than fail or disable them. Journal immutability, distribution-entitlement identity and balance checks remain enforced. Repeated migration plus the full PostgreSQL suite passed after this correction.

The shared baseline fixes keep rejected nonce replay from leaving a surrounding PostgreSQL transaction unusable, and keep grouped financial-audit queries compatible with PostgreSQL without altering their financial comparisons.

## Backend capabilities exercised

The implementation covers separate club/currency books; explicit opening balances and ownership; distinct maker/checker instructions; exact integer unit/capital allocations; contributions, redemptions and ownership transfers; investment acquisitions, weighted-average disposals, valuations and splits; record-date distributions and payments; contribution calls and schedules; append-only journal correction; period closure; historical reporting; immutable issued statements; and scoped access including a former member's own financial history.

These are club-owned bookkeeping records, not OpFin corporate assets or income. The workflows record approved evidence and do not themselves execute an external payment. A balanced cashbook is not issuer-confirmed statement authenticity or custody confirmation.

See [the backend/API guide](../../apps/api/docs/api/CLUB_ACCOUNTING.md) for implemented routes and safe developer exercises.

## Remaining acceptance and implementation

PR #126 remains a working financial branch. An actual independent APPROVED review is required by [financial-change governance](../FINANCIAL_CHANGE_GOVERNANCE.md). The current build evidence is not that review.

Complete Web Workspace and Flutter accounting workflows are not established by this backend test result. Existing treasury screens and HTML/CSV statement rendering are not equivalent to complete contribution, investment, approval and distribution interfaces. A new signed App build, full browser/device acceptance, accessibility verification and end-to-end partner/provider operation are not claimed here.

Full-repository Pint formatting, final publication checks, bank/provider feed certification, operational restore/recovery and independent financial/accounting review remain separate acceptance work. Broader Essentials accounting, durable provider instructions, pending-collection/reversal controls and the credential-log incident are not closed by this club branch.

Any later application-code change requires renewed affected verification. A later documentation-only commit must retain this exact tested application identity instead of retroactively changing its test record.
