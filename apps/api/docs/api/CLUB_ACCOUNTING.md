# Investment-club accounting API and operator guide

Status: Implemented and database-tested backend candidate in PR #126; full channel release pending  
Reviewed: 25 September 2026  
Language: English (United Kingdom)  
Verified application candidate: `1f82f821163ac7058f0e2f2b44672a45a754a166`

## What this module does

The existing treasury cashbook records money received and paid. Club accounting adds the ownership and accounting meaning: whose capital changed, which investment was acquired, how a valuation changes net assets, which member is entitled to a distribution, and how the books reconcile.

Each eligible Financial Space and currency has its own accounting book. Club investments and member contributions are not OpFin corporate assets or revenue. The backend records approved source evidence; it does not automatically send money to a member, broker, bank or payment provider.

A balanced journal is not proof that a bank statement is authentic. Continue using the separate [treasury import/reconciliation contract](CURRENT_CAPABILITY_CONTRACTS.md) and preserve the distinction between recorded cash, external evidence and independently verified settlement.

## Terms for a new developer or operator

A book is the club's accounting record in one currency. A journal is a balanced accounting event: total debits equal total credits. Member capital records a member's financial interest. A unitised book also records ownership units, whose current indicative value depends on net assets. Net asset value, or NAV, is recorded assets less recorded liabilities, not a guaranteed sale price.

A maker prepares an instruction. A different authorised checker approves the exact submitted instruction. A capital call requests a contribution under club rules; it is not automatically a loan, credit default or recognised receivable.

Amounts use integer currency minor units. Ownership and investment quantities use one million micro-units per unit. Do not round money through floating-point client calculations. Use the server's calculations and disclosed rounding evidence, and do not combine currencies into an invented converted grand total.

## Authentication and permissions

The accounting routes require Sanctum authentication and native API throttling. Book, member, instruction, journal and statement identifiers are checked against the exact Financial Space. A platform-wide role alone does not replace the required group membership.

The current maker roles are owner, administrator/admin, chairperson, treasurer, secretary, director and manager. Checkers exclude secretary and must differ from the instruction's maker. Membership and account status are rechecked at approval. Removing a member from operational access does not erase their economic ownership or their right to read their own retained history.

An ordinary member can read their own capital report/statement, not every member's allocations or the full club book. Former members' own-history access is purpose-limited. Obtain identifiers from authorised API responses rather than copying demonstration IDs into live requests.

## Discovery and route map

`GET /api/accounting/club-schema` describes the implemented instruction types, field labels, required fields, supported choices and quantity scales. It is a machine-readable form/schema guide, not a claim that the entire native API has a reviewed OpenAPI specification.

`GET /api/accounting/my-club-books` returns the caller's own accounting memberships/history references.

For the table below, `{base}` is `/api/financial-spaces/{space}/accounting` and `{bookBase}` is `{base}/books/{book}`.

| Method | Route | Purpose |
| --- | --- | --- |
| GET, POST | `{base}/books` | List permitted books or create a draft book |
| GET | `{bookBase}/catalogue` | Authorised officer lookup data: accounts, treasury links, members, holdings, plans, calls and distributions |
| GET | `{bookBase}/report` | Current or historical book/member report |
| GET | `{bookBase}/integrity` | Book control differences and unposted cashbook items |
| GET | `{bookBase}/journals` | Paginated journals with entries |
| GET, POST | `{bookBase}/instructions` | List or submit accounting instructions |
| GET | `{bookBase}/instructions/{instruction}` | Inspect the exact instruction and hash |
| POST | `{bookBase}/instructions/{instruction}/preview` | Transactional simulation, rolled back without committed effects |
| POST | `{bookBase}/instructions/{instruction}/approve` | A different checker approves the exact payload hash |
| POST | `{bookBase}/instructions/{instruction}/reject` | A different checker rejects with a reason |
| POST | `{bookBase}/instructions/{instruction}/cancel` | The maker cancels their pending instruction with a reason |
| GET, POST | `{bookBase}/statements` | List or issue frozen statements |
| GET | `{bookBase}/statements/{statement}` | Read and verify the issued snapshot |
| GET | `{bookBase}/statements/{statement}/html` | Printable statement |
| GET | `{bookBase}/statements/{statement}/csv` | CSV analysis/export |

Instruction lists accept `status`, `page` and `limit`, with page size capped at 100. Journal lists are similarly paginated. Statement lists use their own page parameter. Follow returned pagination; do not mistake one page for all history.

The catalogue is lookup data rather than an unlimited audit export. It includes only recent distribution lookup entries. Use the reporting/statement and journal interfaces for the relevant historical period and verify explicit limits.

## Set up a book

Book creation requires `currency`, `ownership_model` (`capital_accounts` or `unitised`), `cutover_date` and `valuation_max_age_days`. A unitised book also requires `initial_unit_price_minor`. The currency is an uppercase three-letter code; the cutover cannot be in the future. A replay with different opening or valuation terms is rejected rather than silently changing an existing book.

Creation leaves the book in draft state. A separately approved opening instruction establishes the source-reconciled opening balances and explicit member ownership. Do not guess which member owns an unexplained balance. An empty opening book can be approved with empty allocations and no invented zero-value journal.

The opening cash balance must match the existing treasury baseline plus genuine pre-cutover movements. Already-corrupted historical dates are not automatically repaired. Preserve source evidence and a reviewed correction.

## Submit, preview and approve

Every accounting instruction uses this envelope:

```json
{
  "type": "contribution",
  "business_date": "2026-09-02",
  "idempotency_key": "synthetic-contribution-001",
  "payload": {
    "member_user_id": 2,
    "amount_minor": 500000,
    "treasury_account_id": 1,
    "evidence_reference": "SYNTHETIC-RECEIPT-001"
  }
}
```

This is a synthetic example, not a live instruction. Substitute only identifiers returned for the authorised test Space. Submission creates a pending instruction and returns its `payload_hash`; it does not approve itself or execute a payment provider.

The checker inspects the instruction and submits:

```json
{
  "payload_hash": "<the exact 64-character hash returned by the API>"
}
```

Approval rechecks the maker, checker, Space, book, date, current balances and exact instruction. A maker cannot self-approve. A changed payload or actor cannot reuse an existing key. A repeated approval of the same already-approved instruction does not post it again.

Preview performs the same calculations in a transaction and rolls them back. Its simulated identifiers are not committed records and must not be used as new live identifiers. Rejection/cancellation requires `reason` and retains evidence rather than deleting the instruction.

## Implemented instruction families

| Family | Instruction types | Important boundary |
| --- | --- | --- |
| Opening and ownership | `opening`, `contribution`, `redemption`, `allocate_result`, `ownership_transfer` | Explicit source ownership, exact integer quantities and current recorded NAV/capital rules |
| Contribution administration | `capital_call`, `contribution_plan`, `contribution_plan_state`, `capital_call_cancel` | Calls are not automatically debt; paid calls are not erased |
| Investments | `asset_acquisition`, `asset_valuation`, `asset_disposal`, `asset_split` | Weighted-average cost, separate realised/unrealised results, attributable valuation and exact quantities |
| General accounting | `cash_entry`, `journal`, `reverse_journal` | Balanced classified entries; controlled subledgers cannot be bypassed through a generic journal |
| Member distributions | `distribution_declare`, `distribution_pay` | Frozen record-date entitlements, exact allocations and evidenced payment/withholding |
| Book administration | `account_create`, `treasury_link`, `close_period` | Reserved account codes, currency/Space isolation and closed-period protection |

Read the field-level schema for the selected type. Unknown fields are rejected. A unitised redemption/transfer requires its appropriate unit quantity; a capital-account book uses its capital quantity. Conflicting quantities must not be silently ignored.

When a cash movement already exists, supply its `treasury_transaction_id` with the matching treasury account, business date, currency, direction and amount. It can be posted into accounting once. Omitting that identifier means recording a new approved cashbook movement, not silently deduplicating arbitrary external records.

A bank-derived or already-reconciled cash entry cannot be cancelled by inventing an opposite cash transaction. Supply actual counter-evidence where the supported correction requires it, or use a non-cash reclassification when only the accounting category was wrong. Original posted evidence remains unchanged.

## Valuations, distributions and periodic calls

Valuations retain their evidence date. Buying more units does not automatically renew an earlier holding's valuation age. Stale valuations or unclassified earlier cashbook activity can block ownership calculations; do not fabricate a fresh price to remove the block.

Distributions use recorded ownership at declaration and preserve the allocation weights. Payments reduce only the appropriate member's entitlement. Withholding is an explicit approved amount; the API does not invent a tax rate or certify the club's tax treatment.

Approved monthly contribution plans generate due calls through the existing scheduler. The due day is clamped to the end of a shorter month. Generation is bounded and idempotent; system-actor audit evidence records the run and plan transition. Pausing/closing a plan does not delete earlier calls. This process does not collect money from members automatically.

## Reports and immutable statements

The report endpoint requires `period_start` and `period_end`; `member_user_id` selects a permitted member-only view. Reports cannot begin before the approved cutover or end in the future. Historical member quantities, capital-call payments/cancellations and distribution payments are derived from dated evidence, not copied from today's mutable totals.

Officer reports include trial balance, recorded financial position, period income/expenditure, cash-flow classification, holdings, NAV, distributions and recorded performance where enough history exists. A displayed return is not independent market-performance certification. Stale or unverified valuations remain a limitation.

Statement issue requires the reporting period and an `idempotency_key`, plus `member_user_id` for a member statement. The issued snapshot includes its identity, period, ownership data and integrity hash. Later renaming or corrections do not rewrite it. Issue a new statement after approved corrections and retain the earlier version.

HTML supports printing. CSV protects text values from spreadsheet-formula prefixes. Neither format is a bank-issued statement or proof that funds were paid externally. A report can visibly identify unposted cashbook items; a balanced accounting equation alone is not external reconciliation.

## Safe acceptance exercise

The test suite includes this synthetic unitised lifecycle:

| Step | Recorded input or expected result |
| --- | --- |
| Opening | UGX 1,000,000 assigned explicitly to the initial member and 1,000,000,000 ownership micro-units |
| Contribution | A second member contributes UGX 500,000 |
| Acquisition | Purchase cost UGX 500,000 plus UGX 1,000 acquisition costs |
| Valuation | The acquired holding is valued at UGX 600,000 with dated evidence |
| Subsequent subscription | UGX 159,900 issues 150,000,000 ownership micro-units at the recorded NAV |
| Distribution | UGX 99,000 allocates UGX 60,000 and UGX 39,000 to the two member positions |
| Partial payment | Record an approved distribution payment and separate withholding |
| Partial redemption/disposal | Release the appropriate ownership/cost/carrying amounts without changing historical entries |
| Control check | Member, investment, cashbook and ledger controls reconcile without posting club assets into OpFin corporate books |

The [executed verification record](../../../../docs/operations/CLUB_ACCOUNTING_VERIFICATION_2026-09-25.md) identifies the exact candidate: 380 tests and 21,852 assertions on both SQLite and PostgreSQL 18. These are backend test results, not proof of complete Web/Flutter screens, provider settlement or independent financial approval.

## Errors and launch boundary

Missing authentication, denied Space/role access, unknown records, invalid payloads and financial conflicts remain distinct error conditions. Read the actual status and message. Never use a new idempotency key merely because a response was lost; inspect the original instruction first.

A full channel release still requires the remaining Web Workspace/Flutter implementation and verification, complete publication checks and the mandatory independent financial APPROVED review. Do not label the entire club product ready because its backend tests pass. Broader Essentials controls, statement-source authenticity and the separate credential-log security incident remain separate workstreams.
