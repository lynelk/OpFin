# Club client workflow delivery and release evidence

Date: 26 September 2026  
Language: English (United Kingdom)  
Status: Working client implementation; not merged or released

## Branch and preserved foundation

`completion/club-client-workflows-20260926` extends the club backend at `fff925e909d5db1240aa0ddf17768d51c94a54a3` in PR #126. The parent was independently recorded as passing 380 tests on SQLite and PostgreSQL, but those historical results do not validate these new client files.

Both earlier treasury implementations are preserved byte-for-byte: Web blob `bf5030689e6cb069594d07139a289ffd83ac935b` is now `treasury-content.tsx`; Flutter blob `532de1ee05a9ac5654529bf38a313deecf55e51a` is now `financial_space_treasury_screen.dart`. Thin navigation wrappers expose the new accounting workspace without discarding the treasury journey.

## Implemented client workflows

Guided book creation and schema-driven forms cover the 21 backend instruction types. Web and Flutter include instruction queues, inspect/preview, independent exact-hash approval, rejection/cancellation, member-only reporting, officer reports, journal/integrity views and statement issue/read. Web includes protected statement downloads. Flutter uses the existing secure-storage dependency for user/Space/book-bound interrupted request recovery.

The clients do not implement their own financial calculator. The server remains authoritative for money, ownership, permissions, evidence, period closure and finality. No bank, mobile-money or model-provider transaction is executed by these new forms themselves.

## Executed verification

The standalone TypeScript contract module was checked with strict compilation and **38 assertions passed**. It covers exact input bounds, real calendar dates, required fields, unsupported fields/types, nested arrays and unchanged request identity. The locally tested updated contract blob is `5258cfcfde80abd4f7cc122a0474b918b2e9d6520`.

The new TypeScript/TSX files also passed local transpilation/syntax checks. That does not establish complete Next.js type compatibility, routing, browser behaviour, server-action correctness or production compilation.

Nine Flutter contract/gateway tests and two Flutter widget tests are included. They have **not run** because Flutter/Dart and dependencies are unavailable in the current execution environment. No signed App or App Bundle has been produced. No full Next.js build, browser/device review or new API acceptance suite is claimed.

## Required release commands

Run the repository's existing Web/API/client checks in a complete isolated checkout. Additionally:

```bash
node scripts/test-club-contracts.cjs
cd apps/client
flutter test test/club_accounting_contracts_test.dart test/club_accounting_review_test.dart
```

These targeted commands supplement rather than replace full type checks, analysis, tests, dependency/security audit, production builds and device acceptance. Preserve GitHub Actions' deferred status; do not create infrastructure or disable gates to produce a pass.

## Explicit remaining work

Web uncertain-request recovery currently lasts only while the page remains open. Reload-safe recovery still requires implementation. Native statement file export/print/share and dedicated former-member own-history navigation are not yet implemented. Dependency-aware builds, end-to-end interruption tests, accessibility, independent financial review and the exact integrated release remain outstanding.

The broader Essentials lender funding, biller settlement, activation accounting and post-fulfilment refunds are separate from these client changes. The prior credential-log incident is not resolved by this work.

Use [the client workflow guide](../manuals/CLUB_ACCOUNTING_CLIENT_WORKFLOWS.md) for training/UAT exercises and [the club API contract](../../apps/api/docs/api/CLUB_ACCOUNTING.md) for server semantics. No main merge, provider activation or production deployment has been performed for this client branch.
