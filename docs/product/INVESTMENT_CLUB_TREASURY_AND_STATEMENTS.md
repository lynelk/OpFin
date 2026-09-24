# Investment Club Treasury, Reconciliation and Statements

Status: Canonical product and implementation contract  
Updated: 24 September 2026  
Language: English (United Kingdom)

## Purpose

Investment Clubs, Saving Groups, SACCOs and eligible organisation Financial Spaces need a trustworthy treasury record, not only a list of investments or member contributions.

OpFin therefore provides a Financial Space treasury layer for:

1. recording the organisation's internal cashbook;
2. importing external account statements;
3. reconciling external evidence against the OpFin cashbook;
4. resolving reconciliation exceptions under role control; and
5. issuing immutable, bank-style OpFin Financial Space statements.

This capability does not make OpFin the underlying bank, custodian, broker or deposit taker.

## Treasury accounts

A Financial Space can maintain multiple treasury accounts, including:

- bank;
- mobile money;
- cash;
- custodian;
- broker;
- investment wallet; and
- other approved account types.

Each treasury account records:

- account name;
- account type;
- institution/provider name where applicable;
- masked account/reference only;
- currency;
- opening balance;
- current book balance;
- balance-as-of date; and
- status.

The full bank/provider account number is not stored by this treasury account flow. The supplied reference is masked before persistence.

## Internal cashbook

Authorised finance/governance roles can record append-only treasury transactions.

Each transaction records:

- transaction date;
- optional value date;
- debit or credit direction;
- amount;
- currency;
- transaction type;
- description;
- counterparty where relevant;
- transaction reference;
- source type/reference; and
- reconciliation state.

A transaction is initially unreconciled. Reconciliation state can change, but the transaction is not deleted to make the books appear cleaner.

The current book balance is derived from the treasury account opening balance plus cashbook credits less cashbook debits.

## External statement import

The first production import format is **CSV**.

This deliberately supports the most portable export format available from banks, mobile-money providers, custodians and brokers without making OpFin dependent on a specific institution template.

An authorised officer uploads the CSV and maps its headings to:

Required:

- Date
- Description
- Debit/Credit, or Amount + Direction

Optional:

- Value Date
- Reference
- Running Balance

The importer supports a configurable decimal/minor-unit exponent so exported decimal values can be normalised into OpFin integer money units.

OpFin stores normalised statement rows and a SHA-256 source-file hash. Importing the same file into the same treasury account is idempotent and does not duplicate statement rows.

The raw uploaded file is not treated as the OpFin cashbook and does not silently overwrite internal transactions.

## Reconciliation

Automatic reconciliation matches external rows against unreconciled OpFin treasury transactions.

Primary matching evidence:

1. same treasury account;
2. same currency;
3. same debit/credit direction;
4. same amount;
5. exact normalised transaction reference where available; otherwise
6. a unique amount/direction/date match within a controlled date tolerance.

Outcomes:

- matched;
- exception: ambiguous match;
- exception: missing OpFin transaction; or
- future exception classifications as reconciliation depth expands.

The import summary records:

- imported row count;
- matched row count;
- external statement exceptions;
- unmatched OpFin cashbook transactions;
- statement closing balance;
- calculated book closing balance; and
- closing-balance variance where supplied.

Re-running reconciliation recomputes the final totals rather than resetting them to only newly matched rows.

## Manual exception matching

An authorised finance/governance role can manually match an exception row to a specific OpFin transaction.

Manual matching is allowed only when:

- both records belong to the same Financial Space;
- both use the same treasury account;
- currency matches;
- debit/credit direction matches; and
- amount matches.

A transaction already matched to a different statement row cannot be reused.

A statement row already reconciled to a different transaction cannot be silently rematched.

Manual matching is audit logged.

## Role model

Members can:

- view treasury accounts;
- view issued statements;
- inspect statement balances and transactions.

Authorised roles can additionally:

- create treasury accounts;
- record cashbook transactions;
- import external statements;
- run reconciliation;
- manually resolve valid match exceptions; and
- issue official OpFin Financial Space statements.

Current authorised roles:

- owner;
- administrator/admin;
- chairperson;
- treasurer;
- secretary;
- director; and
- manager.

A Personal Financial Space cannot be converted into an Investment Club treasury through these endpoints.

## Bank-style statement standard

An issued OpFin statement uses a bank-style information hierarchy:

- OpFin identity;
- "Financial Space Statement" title;
- unique statement number;
- account holder / Financial Space name;
- Financial Space type;
- treasury account name;
- provider/institution name where applicable;
- masked account reference;
- currency;
- statement period;
- opening balance;
- transaction date;
- value date;
- transaction description;
- reference;
- debit;
- credit;
- running balance;
- total debits;
- total credits;
- closing balance;
- reconciliation status;
- generation timestamp; and
- SHA-256 integrity reference.

The HTML version is print-optimised for A4 and can be printed/saved as PDF by the user's browser.

CSV export is also available for analysis and audit support. Text cells are protected against common spreadsheet-formula injection prefixes.

## Statement immutability

Issuing a statement creates an immutable snapshot.

The statement stores:

- frozen Financial Space identity/name at issue time;
- frozen treasury account presentation details at issue time;
- frozen statement rows;
- frozen balances/totals;
- statement period;
- reconciliation status;
- unique statement number;
- generation timestamp; and
- content hash.

Subsequent changes to the Financial Space name, account name, provider name or later cashbook corrections do not rewrite an issued statement.

After a correction, issue a new statement.

Issued statements cannot be updated or deleted through the model.

## Important presentation boundary

An OpFin statement is **bank-style**, but it is not falsely presented as a bank-issued statement.

Every rendered statement states that:

> This is a system-generated OpFin Financial Space statement designed in bank-style format for record keeping, member reporting and reconciliation. It is not a statement issued by the underlying bank, custodian or payment provider.

Imported external statements remain separate reconciliation evidence.

## Channels

### OpFin App

Members can:

- see treasury accounts and book balances;
- see issued statements;
- open a statement-style transaction view.

Authorised officers can also issue a statement from the App.

CSV import, column mapping and reconciliation remain Web Workspace tasks because they are finance-administration workflows.

### OpFin Workspace

The Web Workspace supports:

- account setup;
- cashbook transaction entry;
- CSV statement import;
- column mapping;
- reconciliation;
- exception inspection;
- manual matching;
- statement issue;
- HTML statement view; and
- CSV statement export.

## Current limitations / next extensions

This release intentionally starts with a strong financial-control core.

Next Investment Club accounting extensions should include:

1. member capital accounts;
2. capital calls and contribution schedules;
3. ownership/unitisation and NAV where appropriate;
4. income allocation and distributions;
5. investment asset register linked to acquisition/disposal transactions;
6. portfolio valuation and performance;
7. member-specific capital/ownership statements;
8. chart of accounts and accounting reports;
9. maker-checker for high-value treasury postings;
10. bank/provider API feeds where commercial agreements permit;
11. additional statement import adapters such as XLSX/OFX where justified; and
12. audit/tax/board reporting packs.

The CSV importer is not a substitute for future direct bank/custodian feeds. It is the portable interoperability baseline.

## Product position

This capability materially improves OpFin's Investment Club proposition because it connects governance and investment administration to actual financial evidence.

The target Investment Club experience remains:

Pool capital → govern decisions → transact → reconcile → report → value portfolio → distribute → preserve institutional memory.

Treasury and statements cover the **transact → reconcile → report** part of that chain.
