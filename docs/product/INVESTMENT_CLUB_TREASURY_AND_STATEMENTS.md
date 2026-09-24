# Investment-club treasury, reconciliation and statements

Status: Product and source-implementation contract with outstanding regression acceptance  
Reviewed: 24 September 2026  
Language: English (United Kingdom)

## Purpose and current evidence

Investment Clubs, Savings Groups, SACCOs and eligible organisation Spaces need an internal cashbook, external statement evidence, reconciliation, role-controlled exception decisions and issued OpFin statements. This does not make OpFin the bank, broker, custodian or deposit taker.

The source implements this treasury slice. The [current build evidence](../operations/DELIVERY_EVIDENCE_2026-09-24.md) records five historical-date/opening-baseline regression failures affecting acceptance. The functional contract below is not a claim that every workflow currently passes. Do not repair a test or a real import by falsifying source dates or removing the baseline guard.

## Treasury accounts and cashbook

Supported account categories include bank, mobile money, cash, custodian, broker, investment wallet and other approved types. Records include account name/type, provider, masked reference, currency, opening balance, current book balance, balance-as-of date and status. The inspected creation flow masks the supplied reference before persistence; it does not store the full bank account number in that field.

Authorised roles record treasury transactions with transaction/value dates, debit/credit direction, integer amount, currency, type, description, counterparty, transaction reference, source type/reference and reconciliation state. The initial state is unreconciled. Reconciliation may change its status; transaction deletion must not be used to make the books appear clean.

The book balance is opening balance plus credits less debits. Preserve an accurate opening baseline when refreshing current balances. A subsequent balance refresh must not make legitimate historical transactions earlier than the original baseline by accident.

## External CSV import

CSV is the implemented portable import format, not proof that every institution export has been accepted. Map Date, Description and Debit/Credit or Amount+Direction. Optional fields include Value Date, Reference and Running Balance. Confirm the decimal/minor-unit exponent and currency before normalising into integer amounts.

The importer stores normalised rows and a SHA-256 source-file hash. The inspected service reuses the same file hash for the same Space/account rather than duplicating import rows. Differently formatted overlapping statements still require economic duplicate review; a file hash is not a universal transaction identifier.

The external statement does not silently replace the cashbook, move money or create a verified bank balance. Empty/malformed records and dates before a genuine opening baseline require validation, not invented values.

## Deterministic matching and review

Candidate matching uses the same account, currency, direction, amount and a controlled date window. Ranking considers normalised reference, date proximity and description similarity. High-confidence matching is implemented in the reconciliation flow; lower-confidence possibilities remain suggestions, with up to three ranked candidates.

The review queue covers suggested or alternative matching, a justified missing book entry, external-only items, genuine duplicates, book-only items and closing-balance differences. A suggestion is not a user's approval. User-applied decisions must retain actor, reason and audit evidence.

Manual matching requires the same Space/account, currency, direction and amount. A transaction already matched elsewhere must not be reused, and an existing matched row must not be silently rematched. Re-running reconciliation must recompute final totals, not count only newly matched rows.

The import summary includes imported/matched counts, external and book exceptions, external/book closing balances and supplied variance. Review tasks must be resolved before confirmation. Distinguish confirmed from confirmed with accepted exceptions; accepted differences and reasons remain visible.

## Role model

Members may view the permitted accounts, issued statements and underlying statement balances/transactions. Authorised officials may create accounts, record cashbook movements, import, reconcile, resolve and issue statements under the service's role policy.

The current specification identifies owner, administrator/admin, chairperson, treasurer, secretary, director and manager as treasury administration roles. Validate actual membership/status and target records, not just a role label in the client. A Personal Space is not converted into an Investment Club merely by calling these endpoints.

## Individual and consolidated statements

An issued statement records the Space identity/type, treasury account/provider, masked reference, currency, period, opening balance, dated transactions, descriptions/references, debits/credits, running balance, totals/closing balance, reconciliation state, issuance time, unique statement number and integrity hash.

Consolidated statements cover active treasury accounts and recorded period movements, separate running balances per account, totals by currency, recorded assets, open payables/receivables, transaction count and reconciliation state. No converted grand total is invented. A future conversion needs an explicit valuation rate/date policy and evidence.

The intended reporting uses include member meetings, board packs, treasury review and external review, while preserving the distinction between cashbook activity and investment valuation/accounting policy.

## Frozen issue and export

Issuance freezes Space/account presentation, rows, amounts, period, reconciliation state, statement number, time and hash. Later renaming or cashbook correction must not rewrite a previously issued statement. Issue a new statement after a correction, preserving the earlier record. The source's immutable model and its negative tests must continue protecting update/delete restrictions.

HTML is designed for A4 browser printing or saving as PDF. CSV supports analysis/audit, with text-cell protections against spreadsheet-formula prefixes. Test actual rendering, exported characters, long labels, dates and amounts before publishing a statement pack.

A bank-style layout is not a bank-issued statement. The rendered report must identify itself as a system-generated OpFin Financial Space statement and keep imported bank/custodian/provider evidence separate.

## Channel boundary

The App provides treasury-account/book-balance and issued-statement access with a statement-style transaction view. Authorised officers have statement-issuance support in the current specification.

Detailed CSV upload/mapping, smart reconciliation, suggestions, book-entry creation, exception reasons, confirmation, consolidated issuance and HTML/CSV export are deeper Web Workspace workflows. This is an observed delivery boundary, not automatic compliance with the wider requirement that essential group journeys be mobile-complete. Product/QA must accept the boundary explicitly or complete the missing essential App tasks.

## Acceptance exercise

Use a synthetic 1 September UGX opening balance of 1,000,000, a 250,000 receipt dated 5 September and a 100,000 payment dated 10 September. The expected closing balance is 1,150,000. Exercise record creation, statement import, matching, review, confirmation and frozen issuance without changing valid dates.

This historical pattern currently has regression failures. Its arithmetic and expected states are requirements, not a completed test. Add replay/overlap, ambiguous matches, removed-member denial, accepted variance and multi-currency statement cases. Procedures and evidence fields are in the [manual supplement](../manuals/CURRENT_CAPABILITY_SUPPLEMENT.md); exact routes are in [Current capability contracts](../../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md).

## Remaining club extensions

Treasury and statements cover **transact → reconcile → report** within the wider **pool capital → govern decisions → transact → reconcile → report → value portfolio → distribute → preserve institutional memory** journey.

Additional scope remains: member capital accounts; capital calls/contribution schedules; ownership/unitisation and NAV; income allocation/distributions; an investment asset register linked to acquisitions/disposals; portfolio valuation/performance; member-specific capital/ownership statements; chart of accounts/accounting reports; high-value maker-checker; contracted bank/provider feeds; justified XLSX/OFX adapters; and audit/tax/board reporting packs.

The CSV baseline is not a substitute for direct provider feeds, and a consolidated treasury statement is not completion of investment-club accounting. These gaps are mapped against the [original concept and current plan](CONCEPT_AND_PLAN_COMPARISON.md).
