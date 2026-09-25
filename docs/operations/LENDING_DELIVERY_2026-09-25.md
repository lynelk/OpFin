# Lending platform delivery evidence — 25 September 2026

Status: Implemented on `codex/lender-product-distribution`; review and release gates remain open.

## Changes

The branch implements the owner's current decisions in cash-credit and Essentials flows: actual lender identity and authority, Core Synergies affiliated credit under platform administration, delegated management, three deployment strategies, configurable country/product/channel policy, Google/Apple/Huawei channel mapping, and product definitions independent of publication availability. No licence, funded capital, provider authorisation or store approval is invented. No weekly-review scope or cadence is expanded.

See [executed roadmap](../development/2026-09-25-lending-platform-roadmap.md), [operating contract](../architecture/LENDER_ORCHESTRATION.md), [UAT scenarios](../manuals/OPFIN_UAT_MANUAL.md#lender-orchestration-uat) and [cross-border backlog](../development/CROSS_BORDER_ROADMAP.md).

## Local validation

| Gate | Observed result |
| --- | --- |
| Laravel suite | **297 tests, 2,003 assertions; zero failures/errors**, SQLite in memory with isolated mock money provider. PHP 8.5.11 emitted two existing PDO MySQL constant deprecations. |
| Changed PHP formatting | Laravel Pint `--test` passed. |
| Schema exercise | Forward migration exercised by the suite, including previously unsupported daily/fortnightly terms. PostgreSQL forward/rollback validation remains unverified. The proposed Actions job was removed following Lionel’s instruction to defer GitHub Actions. |
| Web typecheck | Passed with locked TypeScript 7 dependencies, Node 22. |
| Web lint | Passed using the repository's existing TypeScript 6 compatibility approach. No dependency versions changed. Two existing unused/redundant declarations corrected; existing missing `partner_api` type-map entries completed without adding that role to interactive mock login. |
| Web tests | 35 passed. Home now renders the existing server-provided OpFin Score; Financial Compass label matches the existing brand gate. |
| Web production build | Passed; includes `/admin/lending-platform`. |
| Web/API npm audits | Zero reported vulnerabilities. |
| API asset production build | Passed. |
| Composer audit | Attempted; Packagist security advisory endpoint timed out after ten seconds. A successful direct dependency audit remains outstanding; Actions is deferred. |
| Repository policy | Documentation/publication and security-control checks passed locally; index security and final documentation-drift checks are rerun at commit. |
| Production HTTP smoke | Local wildcard binding failed on restricted network-interface enumeration. Loopback binding served the build; the existing smoke check failed at its expected brand headline. Do not report the complete HTTP smoke as passed. |
| Flutter / Android / iOS | Not locally verified. Automatic approval review rejected Flutter after it attempted to contact a link-local cloud metadata endpoint. No workaround was used. Direct approved build-environment verification remains outstanding; GitHub Actions is deferred. |

New regressions cover short products remaining configured, channel/rule isolation and expiry, unknown channels, independent-first fallback and affiliate caps/withhold, delegated access, Core funding maker-checker and cross-lender pool rejection, non-UMRA rate requirements, immutable lender identity/quote economics, acceptance rejection without money movement, and unactivated foreign currency routes. Tests use explicitly synthetic evidence.

## Integration and release

The implementation began from main `b7472a3a193957c15cacefc319496c334b085dbd`. PR #113 contains separate financial hardening and PR #118 contains Essentials Financial Space/deletion work; both need reconciliation with this branch if still outstanding, not blind replacement. Lionel instructed that GitHub Actions be deferred for this work; no new workflow configuration is included. Independent review and actual build/migration verification remain distinct from Actions status.

The repository’s existing financial-control workflow documents independent review. A code branch or passing local suite is not production deployment or store publication. Complete direct build/migration verification, independent financial review, relevant existing hardening integration, device UAT and authorised live configuration before release; GitHub Actions is deferred by owner instruction. No new Railway infrastructure was created.

## Publication authorisation

Automatic approval review rejected pushing this implementation to the public `lynelk/OpFin` repository because the retained task authorises implementation but does not explicitly authorise public publication. No alternative publication path was used. Lionel explicitly authorised the push on 25 September 2026 at 20:40 Africa/Kampala. The branch can now be published and a draft pull request opened; GitHub Actions is deferred by Lionel’s subsequent instruction; direct verification and independent financial review remain outstanding.

## Completion statement

Coding is implemented and the listed local tests/builds have completed. Full build/release completion is not yet verified: mobile analysis/tests and Android/iOS compilation, PostgreSQL migrations, Composer audit and the existing HTTP smoke mismatch remain outstanding. Skipping GitHub Actions does not mark those checks passed. Publication uses `[skip ci]` to honour the owner’s instruction without reconfiguring repository workflows.


## Pre-merge review correction checkpoint

The 18:07 UTC automated review on PR #123 identified nine concrete defects. Corrections close unlinked pool ownership, missing lender authority, amount/purpose-specific mobile fallback, channel-filtered Essentials eligibility, compatibility product type, legacy default-interest policy selection, configured-pool substitution, pre-decision affiliated strategy checks, and duplicate controller distribution snapshots. Regression fixtures explicitly identify synthetic lender authority and pool ownership; production records are not backfilled with invented evidence.

A Railway volume backup was created and shown restorable at 21:24 Africa/Kampala on 25 September 2026 (1.13 GB). No database restore was executed and no new service, database or environment was provisioned. The documented Railway restore procedure was inspected. GitHub Actions remains deferred. The mobile execution limitation recorded above remains unresolved; source corrections are not signed app-store releases.

The corrected full API suite passed locally: 305 tests, 2,052 assertions, zero failures/errors and the same two PHP 8.5 deprecation notices. Changed-file PHP formatting and documentation/publication checks passed. Web source and dependency versions are unchanged by this correction. Mobile source was reviewed; execution remains blocked as previously documented.

## Deployment timezone correction

PR #123 merged as `1df8af61f8d8a6a956a42055aeeb3007e7b7f265`. Its first API build correctly stopped at a failing policy-expiry test under `APP_TIMEZONE=Africa/Kampala`, before running migrations. Reproducing that environment locally exposed offset-bearing API timestamps being stored as offset-free wall-clock values without conversion to the configured application timezone. Both distribution rules and platform credit strategies now normalise the submitted instant before storage. No test or deployment gate was removed.

New boundary regressions exercise future activation, inclusive start and exclusive expiry under UTC, Africa/Kampala and America/New_York, including different explicit input offsets. The full API suite passes with the production application timezone: **308 tests, 2,076 assertions**, no failures/errors, with the same two existing PHP 8.5 deprecations. Changed PHP formatting passes. Live deployment and PostgreSQL migration results remain to be recorded after the corrective release.
