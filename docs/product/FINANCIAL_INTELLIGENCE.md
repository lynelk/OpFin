# OpFin Financial Intelligence

Status: Implementation candidate, not accepted for production
Date: 26 September 2026
Language: English (United Kingdom)

## Product boundary

Institutional intelligence complements an existing core banking system or MIS. Institution-supplied loan observations, payment allocations and source control totals produce analytical snapshots, not new customer accounts, repayment postings, accounting balances or credit decisions. Every observation retains its source, population, reporting date, currency, schema, engine version and fingerprint. Personal statement analysis is separately authorised; institutional subscriptions confer no authority over Personal Spaces.

## Candidate implementation

The API implements exact-money portfolio normalisation, schedule reconciliation, DPD, PAR0/7/30/90, separately labelled management and source-reported NPL, unknown-evidence handling, concentrations, observed origination cohorts, roll matrices and a PAR30 movement bridge. Currency totals remain separate. Source-referenced accounting summaries support contribution and deterministic liquidity sensitivities only when inputs exist.

Named sources have a lawful-processing reference. CSV and JSON imports require source count and currency control totals. Staged evidence is encrypted and cannot become the current management snapshot until reviewed by a different authorised person. Corrections publish new evidence without rewriting the original import. Publication creates evidence-backed action cases. Assignment, notes, monitoring, closure and reopening have optimistic versions, idempotency and append-only history. Staff reports of recovery do not post or certify payments.

Financial Spaces require active membership and an active financial_intelligence entitlement. Administrator membership roles and separately expiring analyst/reviewer/collections/board/auditor grants determine access. There is no platform-administrator bypass to institution records. Collection agents see assigned cases only. Board reports exclude borrower, guarantor and officer references. Frozen reports retain their presentation and fingerprint after renaming. Network recipients receive only explicitly shared, unexpired report aggregates, not the originating portfolio or customer records.

The statement vault stores encrypted originals, validates eligible issuer versions for the Space jurisdiction and statement period, records authority expiry and allows withdrawal of analysis permission. CSV arithmetic and categorisation remain unconfirmed evidence. PDFs are quarantined pending an approved parser; they are not analysed or authenticated by this candidate. Issuer eligibility is proposed and separately approved; no real licence, provider or customer data is seeded. No statement is automatically credit-decision eligible.

## Acceptance and remaining implementation

Local pure-engine tests and PHP syntax checks are not Laravel, PostgreSQL, Web, Flutter or deployment acceptance. The initial candidate has not run the full framework suites. Full client workflows, document-parser/antimalware integration, calibrated predictive models, product-specific provider certification, advanced statutory/ECL reporting, retention execution and production-like performance/security/device evidence remain explicit delivery work. Do not relabel an unavailable integration or quarantined PDF as a completed user journey.

The feature flag OPFIN_FINANCIAL_INTELLIGENCE_ENABLED defaults to false. GitHub Actions remains disabled. No new infrastructure, live pricing, subscription charge, provider activation or production deployment is authorised by this document. Existing accepted main and parallel delivery branches must be preserved.

## Required verification

Run the repository API quality gates, the new Feature tests, cross-Space and removed-member cases, PostgreSQL migration/concurrency/immutability tests and dependency audit. Exercise the complete source-to-review-to-case-to-report journey. When client changes are present, run Web audit/typecheck/lint/tests/build and affected Flutter analysis/tests/release compiles. Independently review calculations and privacy boundaries. Record exact candidate commits and executed commands, not inferred success.
