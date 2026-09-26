# Club recovery and export implementation evidence

Date: 26 September 2026  
Status: Code candidate; no merge, deployment or release acceptance asserted

## Implemented in this change

Server-persisted encrypted request envelopes; independent inspect/submit/acknowledge/cancel operations; original-key replay and one unresolved slot per user/book/purpose; Web and App transport integration; cross-device recovery screens; retained former-member navigation; native authenticated CSV/HTML save/share/print; and the missing reversal-source field in the existing guided schema.

The original Space list and core club workspace files are retained as content modules. The prior treasury code is not replaced. Financial calculations, approval, statement integrity and authorisation remain on the existing backend services.

## Actually executed locally

- 25 mocked saved-request transport assertions passed against the authored Web server-action source.
- 16 native export-policy assertions passed using the Kotlin compiler and JVM.
- PHP syntax validation passed for the new recovery service, model, controller, routes, provider, migration, schema extension and test source.
- Swift parsing passed for the Runner source with the native export bridge.
- Android manifest and restricted FileProvider paths parsed successfully as XML.

These checks do not execute Laravel, PostgreSQL, Next.js, Flutter or the mobile operating-system UI. Twelve new Laravel recovery tests are authored for the complete application suite but have not been executed in this environment. Earlier parent-branch test totals are historical and are not certification for this candidate.

## Required acceptance still outstanding

Dependency-aware API and Web builds, both database suites and populated migration tests, Flutter analysis/tests, Android and iOS release compilation, browser/device/accessibility checks, independent approved financial review and the exact merged deployment. No production service was used as a test runner and no new paid resource or provider activation was performed.

Read [the API and workflow contract](../../apps/api/docs/api/CLUB_CLIENT_RECOVERY.md) for operational details and limitations. The broader Essentials lifecycle work and the separate credential-log incident are not closed by this club change.
