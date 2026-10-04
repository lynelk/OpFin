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

CSV analysis produces financial-consistency findings and suggested categories. It does not establish income, ownership or lending eligibility. Optional `minor_unit_exponent` (0–4) sets how PDF amounts convert to minor units; it defaults to the currency's ISO 4217 exponent (0 for UGX). No password, PIN or OTP is required by this endpoint. Authority withdrawal or expiry blocks detailed analysis access.

### PDF analysis pipeline

1. **Upload.** The PDF must be at most 10 MB. It is encrypted, fingerprinted and stored with status `queued_for_analysis`. An `AnalyseStatementDocument` job is queued after the upload commits; its payload is the statement id only.
2. **Raw screen.** Before parsing, the raw bytes are checked. A document with active content (JavaScript, launch, submit or import actions, embedded files, rich media, XFA, remote go-to actions, including `#xx`-escaped names) becomes `rejected_active_content`. Password protection becomes `export_required_password_protected`, which asks for an unprotected export and never for a password. Oversize documents become `rejected_processing_limits`.
3. **Parse and object check.** Parsing runs on the queue worker with images discarded and decoding memory capped. The parsed object graph is checked again for active content, including dictionaries in compressed object streams. The page count limit is 200.
4. **Read the layout.** A versioned layout reader extracts dated rows, opening and closing balances, amounts and running balances. When the document gives no sign or CR/DR marker, the direction comes only from the balance chain. Rows that cannot be resolved stay unresolved rather than being guessed. The generic reader does not guess transaction references. A document it cannot read becomes `layout_not_supported` ("not yet supported", never suspicious).
5. **Checks.** Each row is checked against the running balance printed on the row before it, so an altered row produces one `amount_balance_discrepancy` or `running_balance_mismatch` on its source line rather than a cascade. Opening and closing totals, reversed (newest-first) ordering, out-of-period rows, mixed ordering and declared balances that differ from the document are reported as review findings. Resolved rows then pass through the same statement engine as CSV imports.
6. **Labels.** The result is `analysed_unconfirmed` with an encrypted analysis. The plain `assurance` codes are stored separately so listings never decrypt evidence:
   - `institution_eligibility`: `approved_at_upload`.
   - `account_authority`: `declared_by_uploader`.
   - `extraction`: `pending`, `complete`, `partial`, `not_yet_supported`, `not_processed` or `unreadable`.
   - `financial_consistency`: `pending`, `consistent`, `discrepancies` or `unable_to_assess`.
   - `source_authenticity`: always `unconfirmed` until an approved issuer channel exists.
   - `review`: `pending`, `no_issues_detected_by_executed_checks`, `needs_review` or `not_applicable`.
   - `document_signals`: `incremental_updates_present`, `digital_signature_present_unvalidated`, `editing_software_metadata` or `modified_after_creation`.

   Signals are reasons to look closer, not findings of wrongdoing. A fully rebalanced alteration can pass every arithmetic check and remains source-unconfirmed.
7. **Recovery.** `opfin:statements:analyse-pending` runs every five minutes. It requeues statements whose analysis was interrupted and closes them as `unreadable` after two attempts.

Retention: `opfin:statements:purge-originals` runs daily at 02:30. Once analysis permission has expired or been withdrawn for `OPFIN_STATEMENT_RETENTION_DAYS` days (default 90; confirm with legal and privacy before launch), it deletes the original and its analysis. The file hash, authority record and assurance codes remain as audit evidence, and `original_purged_at` is set. A `legal_hold_until` date defers purging. There is not yet an API to set legal holds.

### Review, appeal and resubmission

Reviews take place inside institutional Spaces. They are decided by Space administrators or users with a current `reviewer` grant (permission `statement_review`). Personal Spaces have no reviewer: the owner sees the results and can resubmit. Platform staff do not gain access to a Space's statements.

- `GET /statement-reviews` is the queue. It lists statements whose `review` is `needs_review`, `escalated`, `appealed` or `issuer_verification_requested`. Each item shows a masked member ("Member with phone ending 4321"), the issuer's legal name and jurisdiction, coverage, findings with source lines, the assurance codes and the decisions the viewer may take.
- `POST /statements/{statement}/reviews` records a decision. It needs `decision`, `reason_code`, an optional `evidence_reference` and optional `notes`, and requires `Idempotency-Key`.

| Decision | Resulting `review` | Reason codes |
|---|---|---|
| `resolved_no_concern` | `resolved_with_reasons` | `explained_by_customer`, `explained_by_layout`, `verified_with_original`, `other_documented`; notes required |
| `resubmission_requested` | `resubmission_requested` | `unreadable_or_partial`, `period_or_account_mismatch`, `balances_do_not_reconcile` |
| `original_requested` | `original_requested` | `edited_copy_suspected`, `signals_need_original` |
| `issuer_verification_requested` | `issuer_verification_requested` | `material_discrepancy`, `high_impact_use` |
| `escalated` | `escalated` | `needs_senior_review`, `possible_conflict` |
| `rejected` | `rejected_with_reasons` | `issuer_confirmed_alteration` (evidence reference required), `not_the_declared_account`, `uploader_withdrew`, `unreadable_after_resubmission` |

The following rules apply:
- Reviewers cannot decide on statements they uploaded.
- Escalated reviews need a Space administrator.
- An appeal must be decided by a reviewer other than the one who rejected the statement.
- No reason code allows rejection on document signals alone.
- A decision never changes `source_authenticity`, which stays `unconfirmed`.
- Decisions are append-only (database triggers on PostgreSQL and SQLite) and audited.
- Notes are encrypted and visible only to reviewers.

`POST /statements/{statement}/appeal` lets the uploader appeal a `rejected_with_reasons` decision once. It takes a `reason` and requires `Idempotency-Key`.

To resubmit, upload a new statement with `supersedes_statement_id` set to one of the uploader's own current statements in the Space. The replaced statement keeps its history and moves to `superseded_by_resubmission`.

The statement detail now includes `reviews`, `next_action`, `can_appeal`, `permitted_decisions` and `supersedes_statement_id`. The uploader's view omits reviewer notes and roles.

Not yet in place: antivirus scanning (needs an approved scanner service), issuer-specific layout readers validated against authorised samples, issuer source verification, member notifications for review decisions, and the Flutter journey.

## Web integration

The Web workspace is /spaces/{id}/intelligence. Its typed server-only client is src/lib/api/financial-intelligence.ts. Server Actions retain the access token on the server; generated download routes validate numeric Space/report identifiers and allow only CSV or HTML. Report responses are private/no-store and carry a restrictive content policy. The UI never updates core balances or implements an alternative credit policy.

## Acceptance limitations

This contract describes source intent, not a completed live integration. Exact route-cache behaviour, model binding, database schema compatibility, grant revocation races, migrations, rendering and all dependent workflows require actual framework/build/browser tests. No direct bank/MNO/core connector is advertised as certified by this candidate.
