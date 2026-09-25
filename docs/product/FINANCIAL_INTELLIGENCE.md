# OpFin Financial Intelligence

Status: Partial implementation candidate; not complete, build-accepted or deployed
Work record: 26 September 2026
Language: English (United Kingdom)
Repository branch: feat/financial-intelligence-20260926
Pull request: #132 (draft)
Source baseline: 3924a26913f85067a3ac900c78fa80125ca589fc

## Product boundary

Institutional intelligence complements an existing core banking system or MIS. Institution-supplied loan observations, payment allocations and source control totals produce analytical snapshots, not new customer accounts, repayment postings, accounting balances or credit decisions. Every observation retains its source, population, reporting date, currency, schema, engine version and fingerprint. Personal statement analysis is separately authorised; institutional subscriptions confer no authority over Personal Spaces.

## Source implemented in this candidate

The API implements exact-money portfolio normalisation, complete-schedule reconciliation, DPD, PAR0/7/30/90, separately labelled management and source-reported NPL, unknown-evidence handling, concentrations, observed origination cohorts, roll matrices and a PAR30 movement bridge. Currency totals remain separate. Source-referenced accounting summaries support contribution and deterministic liquidity sensitivities only when inputs exist. Distinct-borrower counts, rather than loan counts, govern external small-cohort suppression.

Named sources carry a lawful-processing reference. CSV and JSON imports require source counts and currency control totals. Staged evidence is encrypted and cannot become the current management snapshot until reviewed by a different authorised person. Corrections publish new evidence without rewriting the original import. Publication creates evidence-backed action cases. Assignment, notes, monitoring, closure and reopening have optimistic versions, idempotency and append-only history. Staff reports of recovery do not post or certify payments.

Financial Spaces require active membership and an active financial_intelligence entitlement. Administrator membership roles and separately expiring analyst/reviewer/collections/board/auditor grants determine access. There is no platform-administrator bypass to institution records. Collections users see assigned cases only and can enter via a role-aware context endpoint without overview rights. Board reports exclude borrower, guarantor and officer references. Frozen reports preserve presentation and fingerprints. Explicitly authorised network recipients receive unexpired, privacy-filtered report aggregates, not originating customer records.

The statement vault stores encrypted originals, validates eligible issuer versions for the Space jurisdiction and statement period, records authority expiry and allows withdrawal of analysis permission. CSV arithmetic and categorisation remain unconfirmed evidence. PDFs remain quarantined: no PDF extraction, malware-cleared processing or issuer authentication is claimed. Issuer eligibility is proposed and independently approved; no real licence, provider or customer data is seeded. Statements are never automatically credit-decision eligible.

Web source adds a permission-aware workspace for overview, imports/review, cases, statements, reports, access, network sharing, comparisons and scenarios. Actions use server-held authentication, explicit relative paths, bounded inputs, no-store responses and protected exports. The existing Space journeys are preserved. The additional Space entry is hidden unless the Web feature flag is enabled. The larger Server Action request limit is also conditional on that flag. The default remains 1 MB; reviewed activation permits 26 MB transport while API/action handlers enforce their own smaller limits.

## Execution evidence, not acceptance

Local PHP 8.4.23 executed 21 standalone deterministic-engine tests with 590 assertions and zero failures. A compiled dependency-free Web presentation helper executed nine Node tests with 28 assertions and zero failures. PHP syntax checks and a limited new-source TypeScript check also passed. The latter used explicit framework-boundary stubs and is not a Next.js application typecheck or build.

Fifteen Laravel Feature tests and nine Vitest tests are written. They were not executed in their actual frameworks. The local workspace is a source overlay without the full installed Laravel/Next.js dependencies, PostgreSQL PDO driver or Flutter SDK. The requested connected Codex execution was refused because the account had reached its usage limit. No task is running from that request. The build prerequisite script returns exit code 2, not success.

## Explicit remaining code and acceptance work

This is not the complete institutional product described in the commercial discussion. Outstanding scope includes: the secure PDF/scan/parser and source-authentication pipeline; native mobile journeys and device acceptance; retention/legal-hold/purge execution; advanced statement cross-account intelligence and governed cash-flow underwriting; calibrated predictive models and the institutional question-answering assistant; product-specific statutory/ECL/reporting packs; certified direct core/provider adapters and scheduling; complete commercial catalogue, usage economics and billing acceptance; richer outcome measurement; broad scale/security/accessibility validation; and alignment of all existing manuals and endpoint indexes.

The Web CSV form currently imports the portfolio and schedule; source-referenced institutional accounting summaries can be supplied through the JSON API, not through a completed guided Web accounting importer. Spreadsheet and scanned-document adapters are not represented as implemented. Closing a case is not automatically proof of cure or cash recovery.

## Activation and governance

OPFIN_FINANCIAL_INTELLIGENCE_ENABLED defaults to false in API configuration and must remain disabled on unaccepted deployments. The Web environment uses the same service-scoped flag for navigation and request-size activation. An entitlement does not replace membership, consent, issuer eligibility or credit policy. No active commercial prices, subscription charges, licensed issuers or provider credentials are created by this candidate.

GitHub Actions remains disabled at the owner's direction. No new infrastructure was provisioned, and no main-branch merge or production deployment was performed for this candidate. Equivalent candidate-specific validation is still mandatory. Do not use production data or services to bypass a missing disposable test environment.

## Required acceptance

Run actual Laravel feature/full suites, Pint and dependency audit; PostgreSQL migration, trigger, replay and concurrency checks; full Web dependency audit/typecheck/lint/tests/build; authenticated browser tests; and affected Flutter analysis/tests/release compiles with real supported-device evidence. Independently review source reconciliation, currency units, statutory definitions, cross-Space denial, removed-member denial, report sharing, authority revocation and source-data retention. A passing standalone engine is not proof that the application or migration works.

See the API contract in apps/api/docs/api/FINANCIAL_INTELLIGENCE_CONTRACT.md and the controlled runbook in docs/operations/FINANCIAL_INTELLIGENCE_RUNBOOK.md.
