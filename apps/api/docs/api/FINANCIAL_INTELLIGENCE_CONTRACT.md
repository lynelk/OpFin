# Financial Intelligence API contract

Status: Source candidate; full framework integration and release acceptance outstanding
Language: English (United Kingdom)

## Authentication and authority

Routes are registered by FinancialIntelligenceServiceProvider under /api with API middleware, auth:sanctum, API throttling and FinancialIntelligenceRequestGuard. The feature flag is off by default. Financial Space membership, active entitlement and capability-specific permissions are checked server-side. Platform administrator status does not grant access to institutional portfolios. Collections access is restricted to assigned cases. Personal Space owners have statement access only.

All scoped paths below are relative to /api/financial-spaces/{space}/intelligence. Success responses use {"data": ...}. Unauthenticated, forbidden, missing, conflicting, oversized and invalid requests use HTTP 401, 403, 404, 409, 413 and 422 as applicable. Disabled functionality returns 503. A client must not convert an ambiguous response into a successful financial action.

## Scoped endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | /context | Current role, permissions and Space metadata; no portfolio data. |
| GET | / | Published overview, separately by source/population and currency. |
| GET | /members | Active membership IDs and delegated grants; administrator permission. |
| GET | /template | Canonical CSV and manifest field guidance. |
| POST | /sources | Register source name, population, jurisdiction and processing reference. |
| GET / POST | /sources/{source}/imports | List or stage JSON portfolio observations. |
| POST | /sources/{source}/csv | Stage multipart loan/schedule exports and a JSON manifest. |
| GET | /imports/{import} | Detailed observation and paginated source loans. |
| POST | /imports/{import}/review | Independent publication or rejection with a reason. |
| GET | /comparison | Compare from_import_id with to_import_id. |
| POST | /stress | Source-referenced liquidity sensitivity, not a prediction. |
| GET | /cases | Permitted action queue. |
| GET / POST | /cases/{case}/events | Read or append an authorised versioned action. |
| PUT | /grants | Grant or revoke an expiring delegated institutional role. |
| GET / POST | /reports | List frozen reports or freeze a published import. |
| GET | /reports/{report} | Read frozen report evidence. |
| GET | /reports/{report}/csv or /html | Protected analysis export or printable HTML. |
| PUT | /reports/{report}/share | Explicit recipient-Space report mandate with expiry/revocation. |
| GET | /network | Privacy-filtered reports shared with the current Space. |
| GET | /issuers | Approved jurisdiction-specific issuer versions. |
| GET / POST | /statements | List evidence or upload an original CSV/PDF. |
| GET | /statements/{statement} | Permitted analysis; PDF may remain quarantined. |
| DELETE | /statements/{statement}/permission | Withdraw further analysis permission, not erase audit evidence. |

Platform-admin issuer routes are POST /api/intelligence/admin/issuers and POST /api/intelligence/admin/issuers/{issuer}/review. Proposal and approval must be by different authorised people. A licence-evidence reference is not independent authentication of every document bearing that brand.

## Portfolio ingestion

JSON stage requests contain a portfolio object with schema_version=opfin.fi.portfolio.v1, as_of, source_system, population, expected_loan_count, control_totals_minor and loans. Optional financials contain source-referenced accounting observations. Input schemas reject undeclared fields, including protected/programme attributes. Use institution-local pseudonymous references, not names or NINs.

A loan requires loan_ref, borrower_ref, currency, original_principal_minor, principal_outstanding_minor and originated_on. Optional dimensions are product, branch, officer, sector, funding_source and guarantor_ref. Optional source-reported regulatory_npl must have regulatory_classification_ref; unlikely_to_pay=true requires an evidence reference. Neither is inferred from demographic characteristics or statement categorisation.

instalments is a complete array or null. Each instalment has instalment_ref, due_on, principal_due_minor, interest_due_minor, principal_paid_minor and interest_paid_minor. Remaining principal must reconcile to outstanding principal. Missing schedules yield unknown delinquency, not performing classifications. Current observations alone do not establish historical recoveries.

All monetary values are bounded integer minor units, not floating-point major amounts. There is no FX conversion. Control totals must match exactly for each currency. Maximum loan count is 50,000; CSV imports also have byte/row/column bounds. Origination, period and reporting dates are validated without inventing replacements.

For multipart imports, submit loans_file, optional instalments_file, manifest JSON and optional mapping JSON. Mapping has loans and instalments objects whose canonical keys map to exact source headers. Use Idempotency-Key for staging and retain the same key when checking/retrying the same instruction. Reusing it for different input produces a conflict.

## Publication, cases and reports

Staging does not publish. A different authorised reviewer submits decision=published or rejected and a reason. Published corrections create a new observation; original facts are protected. The current source pointer cannot move backwards in reporting time. Equal-date correction history remains visible.

Case events accept action, expected_version and note, with assigned_to/due_on/outcome/source_evidence_reference where applicable. Idempotency-Key binds repeated requests to the same instruction and actor. A changed version produces 409. A payment-related closure needs a source reference but never posts or independently certifies settlement.

Frozen reports are management evidence, not audits, regulatory certificates or bank statements. External report sharing removes detailed concentrations, cohorts and accounting information and suppresses currency metrics below five distinct borrowers. The shared-payload hash differs from the original frozen-report hash. Sharing is report-specific and does not authorise marketing or cross-lender borrower searches.

## Statements

Upload statement_file, issuer_version_id, currency, period_start, period_end, account_reference, authority_reference, authority_confirmed, authority_expires_at and purpose=financial_analysis. Optional balances are exact integer minor units. Optional mapping is a JSON object. The original is encrypted and fingerprinted. File hashes establish after-receipt integrity, not issuer authenticity.

CSV analysis produces financial-consistency findings and suggested categories. It does not establish income, ownership or lending eligibility. PDF originals remain quarantined_parser_required until the separate processing pipeline exists and is accepted. No password, PIN or OTP is required by this endpoint. Authority withdrawal or expiry blocks detailed analysis access. Retention execution is outstanding and must be resolved before launch.

## Web integration

The Web workspace is /spaces/{id}/intelligence. Its typed server-only client is src/lib/api/financial-intelligence.ts. Server Actions retain the access token on the server; generated download routes validate numeric Space/report identifiers and allow only CSV or HTML. Report responses are private/no-store and carry a restrictive content policy. The UI never updates core balances or implements an alternative credit policy.

## Acceptance limitations

This contract describes source intent, not a completed live integration. Exact route-cache behaviour, model binding, database schema compatibility, grant revocation races, migrations, rendering and all dependent workflows require actual framework/build/browser tests. No direct bank/MNO/core connector is advertised as certified by this candidate.
