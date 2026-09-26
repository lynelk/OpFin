# OpFin delivery evidence: 24 September 2026

Status: Dated engineering and management evidence  
Audience: engineering, release, Finance, Risk, operations and reviewers  
Language: English (United Kingdom)

This record distinguishes inspected source, executed build results, deployment status and unresolved review findings. It is not a release certificate. Later corrections must append their exact candidate and results rather than changing these historical observations.

## Source baseline

Repository: `lynelk/OpFin`. Branch: `main`. Inspected commit: `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`. Source tree: `fb0f10c22985cb085127870c51e827661f867d2f`.

This commit merged [PR #105](https://github.com/lynelk/OpFin/pull/105). Its merge message explicitly retained unresolved financial-control findings and did not claim CI, financial-control or production-activation sign-off.

The documentation branch starts from this exact baseline. Documentation corrections do not themselves repair the financial findings below.

## Production deployment observations

The existing production environment is `f86c5872-e57e-424f-97ce-0a09ab081d1d` in project `d7e188c2-3a87-4d0a-8ccb-cad4f32f44c2`.

| Service | Observed candidate deployment | Result | Interpretation |
| --- | --- | --- | --- |
| API | `89d4d62f-92ed-4039-b65d-79311a6f5c98` | Failed | Existing API test gate stopped the build |
| Web | `356c2df7-d62b-4bb4-823b-a1684b41d70b` | Failed | Compilation completed, then TypeScript checking stopped the build |
| Worker | `370dd377-19f3-49eb-bf59-2bf34018e121` | Success | Worker success does not establish API/Web parity |
| Scheduler | `51c66b6d-caa7-4386-ba2a-dfef54cb0624` | Success | Scheduler success does not establish complete financial release acceptance |

The latest successful API deployment returned by the production query was `8bde713b-2cf6-45ba-b33e-0f9215366a91`, created at 16:10:48 UTC on 24 September 2026 and reporting commit `aa19a53481b2094530616340b8f54aaaabfc6172` (PR #109, location authorisation handling). This is earlier-version deployment evidence, not a fresh live-health or integrity certification.

No successful Web record was returned by the narrowly filtered query. That must not be interpreted as proof that no earlier Web deployment ran: replaced records may have another status.

## Executed API build results

The API build log reports **263 tests: 257 passed and six failed**, with **1,763 assertions**. These are results from that build, not from the later documentation candidate.

Five failures are in `FinancialSpaceStatementsTest`. Affected transaction/import/statement workflows receive opening-balance-baseline validation errors instead of the expected successful responses. A sixth failure in `FoundationApiTest::test_foundation_roles_and_permissions_are_defined` expects six roles while the implementation includes `partner_api`.

Do not remove baseline checks or stop testing historical records merely to pass. The first statement test explicitly creates a 1 September opening baseline, then records 5 and 10 September movements. Investigate how the baseline is retained after balance refreshes and whether historical statements use the correct period.

Observed passing tests cover established programme delivery/privacy, provider-independence/economics, savings/protection lifecycle, scheduled-command registration and several UMRA controls. They do not certify unrelated Essentials transitions.

## Executed Web build results

The inspected Web build reported no npm audit vulnerabilities, compiled Next.js, then failed TypeScript checking in `apps/web/src/app/actions.ts`.

`loginAction` constructs a conditional object preserving `next` and optional `context`. TypeScript infers a union with optional `context: undefined`, incompatible with `Record<string, string>` accepted by `redirectWith` when spread into the error parameters.

A correction must preserve safe internal paths, role routing, secure cookies and error handling. Do not disable type checking or use an unvalidated broad cast.

## Current Essentials financial-control review findings

The latest inspected PR #105 review evaluated `7d39bbbcadf9cb8c909e873004221a62da2534f4`, a parent of the inspected merge. These are unresolved review findings, not newly executed exploit tests.

| Finding | Required acceptance evidence |
| --- | --- |
| Activation and repayment lack the required immutable Essentials accounting events | Expected balanced, idempotent postings inside controlled transitions; missing-event detection and reconciliation |
| Open Essentials advances are missing from account-deletion obligations | Pending, active, overdue, reversed and settled scenarios retain lawful records and repayment access correctly |
| Optional Financial Space context can accept a partner grant for another Space | Exact target-Space resolution and negative tests for omitted, changed and unauthorised IDs |
| Concurrent repayment requests can both create pending collections | Advance locking, a database backstop and concurrent/overcollection tests with distinct keys |
| Approved capital mandates are not consistently usable by Essentials | Approved-mandate fixtures exercise eligibility, reservation, deployment, release and reversal |
| Lender funding/reversal pending states are omitted from a profile exposure calculation | Every reservation-bearing state retains exposure during ambiguity and cannot reopen headroom |

The [Essentials specification](../product/OPFIN_ESSENTIALS.md) describes intended behaviour; it is not proof that these controls already pass. Older comments require separate revalidation before being called open or resolved.

## Verification and infrastructure constraints

GitHub Actions remains disabled under the owner's direction. Do not re-enable it simply to produce a check result. Equivalent candidate-specific test, security, build and operational evidence remains necessary, including independent review where financial-change policy requires it.

No new service, database, environment, volume, bucket or replica is authorised by this review. Use existing approved infrastructure. Do not use Railway Agent or remove build tests/audits to force deployment.

Before declaring production aligned, record every running service's source, forward-migration result, live/readiness response, fresh worker/scheduler heartbeats and applicable reconciliation. A merge or queued deployment is not completion.
