# Essentials authority and PostgreSQL verification

Status: Executed candidate evidence; financial merge approval and production acceptance not implied  
Recorded: 25 September 2026  
Language: English (United Kingdom)

## Exact source and test environment

Application candidate: `e336daf4962cb735d1ff0b2360690b1eaffab83e` on `fix/essentials-space-and-closure-20260925`, PR #118. The branch contains accepted main `b1686989a317619562a8592728a310fbcb8f9113`.

Validation deployment: `84526c00-deff-4785-8436-b69aa81528f9` in the existing Railway API build service. This downloaded the exact public source into an ephemeral directory. Test subprocesses received an allow-listed synthetic environment, not runtime database/provider credentials.

The PostgreSQL fixture used version 18, a private Unix socket, no TCP listener and only synthetic records. It was stopped in the verifier's cleanup. No Railway service, managed database, environment, bucket, replica or volume was created. The existing production PostgreSQL database was not a test target.

## Executed results

| Check | Actual result |
| --- | --- |
| Existing SQLite API suite | 358 tests passed; 2,362 assertions |
| Existing Composer dependency audit | Passed in the unchanged API build procedure |
| Catalogue-definition check | Passed; this is not full native contract coverage |
| API asset build | Passed |
| Fresh PostgreSQL 18 migrations | Completed in the synthetic fixture |
| Full PostgreSQL 18 suite | `OK (358 tests, 2362 assertions)` |
| Candidate marker | `OPFIN_POSTGRES18_VALIDATION_RESULT e336daf4962cb735d1ff0b2360690b1eaffab83e exit=0` |
| Runtime deployment from verification | Intentionally prevented; verifier exits 23 after its success marker |

Railway therefore labels the validation deployment failed because the final deliberate exit is non-zero. The actual test result is established by the command output above, not by relabelling every failed deployment as successful. The earlier PostgreSQL run genuinely failed before these fixes; it is not an intentional-success example.

Normal API configuration was restored after the validation snapshot was captured: `sh scripts/railway-build.sh`, followed by `sh railway/pre-deploy.sh`. The earlier successful main/API/worker/scheduler Developer Centre deployment is separate from this financial branch test.

## Remediation covered

The branch implements exact Financial Space authority for embedded Essentials requests, removes unrelated account/debt/decision collections from eligibility responses, retains active obligations during account closure and serialises covered Essentials mutations with closure. Tests cover the actual dependency-injection bindings, unchanged native transaction depth, same-customer contention on a separate PostgreSQL writer session, unrelated-customer independence, exception release, stale-customer denial and routed 409/403/404 handling.

The full PostgreSQL exercise also found two baseline defects hidden by SQLite. Callback nonce uniqueness errors now roll back their own transaction/savepoint before a replay is rejected, leaving an enclosing transaction usable. Regulatory reconciliation queries select grouped identifiers rather than joined `SELECT *` columns. Existing signature/replay rules and financial comparisons remain unchanged. New regression cases exercise these behaviours directly.

## What this result does not establish

A passing suite is not independent financial approval. The repository still requires an APPROVED review from a GitHub identity other than the PR author. A Codex comment or this evidence record is not that approval.

The mutex requires a direct or session-affine PostgreSQL writer connection. These tests do not certify transaction-pooling changes, cross-service failover, every possible concurrency interleaving, physical devices or live provider recovery.

This slice does not complete expected Essentials ledger events, durable canonical provider instructions, pending-collection capacity, complete repayment/reversal accounting, every exposure/mandate requirement, club-accounting user interfaces, full native OpenAPI schemas or the separately recorded credential-log incident. It does not activate a lender/provider or execute real customer money movement.

Full-repository Pint formatting, Web/Flutter builds and document-publication scripts are not asserted as passed by this evidence record. Any later code change requires fresh affected checks; a later documentation-only commit must identify that its application code is unchanged rather than claiming this exact SHA changed retroactively.

## Related sources

Read [authority and closure](../../apps/api/docs/api/ESSENTIALS_AUTHORITY_AND_CLOSURE.md), [current API contracts](../../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md), [Essentials product contract](../product/OPFIN_ESSENTIALS.md), [manual supplement](../manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) and [financial-change governance](../FINANCIAL_CHANGE_GOVERNANCE.md). Historical delivery evidence retains its original dates and results.
