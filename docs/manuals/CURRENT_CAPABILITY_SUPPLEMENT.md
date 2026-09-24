# Current capability supplement: clubs, Essentials and release evidence

Status: Controlled task, training and acceptance reference  
Reviewed: 24 September 2026  
Language: English (United Kingdom)  
Source baseline: `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`

Read with the [user](OPFIN_USER_MANUAL.md), [training](OPFIN_TRAINING_MANUAL.md), [operational](OPFIN_OPERATIONAL_MANUAL.md) and [UAT](OPFIN_UAT_MANUAL.md) manuals. The [current-state record](../CURRENT_STATE.md) and [delivery evidence](../operations/DELIVERY_EVIDENCE_2026-09-24.md) govern availability claims. These tasks describe source workflows; they are not confirmation that production acceptance has passed.

## 1. Investment-club treasury workflow

### Before starting

Use an authorised club/group Space and verify your role. Keep training data synthetic. Establish the account currency, opening balance and opening date from approved source records. A cashbook opening balance is not proof of a bank balance.

The current detailed CSV import and reconciliation workflow is a Web administration task; App statement access does not demonstrate that every treasury administration action is already mobile-complete.

### Record and reconcile

1. Select the intended Financial Space and treasury account. Confirm the Space/account names and currency before proceeding.
2. Record supported receipts and payments with amount, direction, transaction/value dates, description and reference. Do not backdate or alter a source statement to avoid a validation error.
3. Upload the account's CSV statement and map its date, description, reference, debit, credit and running-balance columns as applicable. Review currency and minor-unit interpretation before import.
4. Inspect parsed rows, duplicates, suggested matches and unmatched book entries. Import alone must not be treated as a confirmed reconciliation.
5. Match only when source evidence supports the correspondence. For an external-only item, duplicate, new book entry, book-only item or balance variance, record the applicable decision and reason.
6. Complete required review tasks before confirmation. Accepted differences remain visible evidence; confirmation must not invent a balancing entry or erase the difference.
7. Issue the account or consolidated statement for the intended period. Check currency-separated totals, the reporting period and the frozen statement identity. Use its HTML print view or CSV export as appropriate.

Generated OpFin statements represent recorded and reviewed information. They are not bank-issued statements and are not evidence of complete member-capital, NAV/unitisation, distribution or investment-performance accounting.

### Trainer exercise and expected arithmetic

Create a synthetic UGX account with a 1 September opening balance of 1,000,000. Record a 250,000 receipt on 5 September and a 100,000 payment on 10 September. The expected cashbook closing balance is 1,150,000. Import the corresponding two-row statement and verify matching, variance, confirmation and issuance.

The current build failed a regression using this historical-date pattern. Its expected result is an acceptance criterion, not a claimed successful exercise. Record the actual error and stop; do not edit the dates or disable the opening-baseline guard to obtain a pass.

## 2. Essentials: safe explanation and controlled practice

### What the learner must understand

Essentials arranges purpose-bound bill or rent finance from the named participating third-party lender. OpFin is not to be described as the primary Essentials lender. The financed amount is intended for the verified biller or rental beneficiary rather than unrestricted cash-out. Lender limits do not add up into unlimited headroom.

Current internal financial-control findings prevent describing Essentials as launch-certified. Do not use this module to invite live borrowing, debt creation or real provider settlement before those findings and external activation gates have been closed.

### Controlled test procedure

With an approved non-money-moving test setup, demonstrate account/beneficiary capture, verification state, lender eligibility, amount selection and the exact offer disclosures. Explain lender identity, amount paid to the provider, fees/interest, total repayment, due dates and what happens when a provider is unavailable.

Where the approved test fixture permits acceptance, retain the quote/disclosure reference. Inspect lender-funding, fulfilment and repayment states separately. A successful request is not an issued token, paid rent or confirmed repayment. Do not repeat an ambiguous collection using a fresh key.

Demonstrate connected-platform permissions and revocation. A general platform grant is not permission for every Financial Space or for unconfirmed debt creation. Do not use a programme-partner identity as a substitute for the appropriate lending-platform role.

### Operations acceptance

Before live activation, obtain evidence for immutable expected accounting, canonical provider references/reconciliation, exact-Space authorisation, concurrent repayment prevention, open-advance account deletion, pending funding/reversal exposure and usable approved capital mandates. Then complete genuine lender/biller/provider agreements, credentials, approved terms and recovery exercises.

These are internal controls and external dependencies respectively. They must not be combined into the misleading statement that only credentials remain.

## 3. Location and programme guidance

Location remains optional for baseline financial use. Demonstrate purpose-specific permission, manual fallback and denied unauthorised access without exposing a provider key. Do not claim background tracking or location-based credit decisions where the product contract prohibits them.

Programme check-ins remain voluntary and require their applicable consent/enrolment. Financial health and programme outcomes are not credit scores. Reviewed translation and English fallback must be shown honestly; a language option does not prove every questionnaire is translated. Partner exports remain aggregate and privacy-suppressed.

## 4. Additional acceptance cases

These identifiers extend, rather than renumber, existing UAT cases. Expected results are requirements; no case is marked passed solely by being listed.

| ID | Scenario and procedure | Expected evidence |
| --- | --- | --- |
| DEL-TR-01 | Record historical movements after setting a fixed opening baseline | The opening baseline remains valid; accepted transaction dates and balances reflect source records |
| DEL-TR-02 | Re-import the identical account statement | Existing import is reused or an explicit duplicate result returned; no duplicate economic entry |
| DEL-TR-03 | Present one statement row with multiple possible matches | Ambiguity stays in review; no silent many-to-one match |
| DEL-TR-04 | Accept a justified variance and issue a statement | Decision/reason retained; difference not fabricated away; issued snapshot stable |
| DEL-TR-05 | Issue a consolidated report across UGX and another currency | Separate currency balances; no unsupported conversion/grand total |
| DEL-ES-01 | Complete funded advance and repayment transitions in a test fixture | One expected balanced immutable event per financial transition, including replay |
| DEL-ES-02 | Submit two concurrent repayments with different keys | Collection/reservation cannot exceed the permitted outstanding amount |
| DEL-ES-03 | Use a grant in another or omitted Space context | Target-Space access denied unless explicitly authorised |
| DEL-ES-04 | Request deletion with pending, active or overdue Essentials exposure | Governed closure prevents unsafe loss of the open obligation or required records |
| DEL-ES-05 | Keep lender funding or reversal unresolved and refresh profile | Exposure remains reserved until authoritative resolution |
| DEL-ES-06 | Select an approved, funded capital mandate | Eligibility/reserve/deploy/release/reverse lifecycle works and is audited |
| DEL-REL-01 | Compare main and all running production services | Exact source/version, migrations and health recorded for each service; no inferred parity |
| DEL-DOC-01 | Compare original plan, current docs, UI and API | Requirement changes labelled; blocked capabilities not advertised as certified; stable source references |

## 5. Evidence record for a completed task

Record task ID, tester/role, source commit, build/environment/channel, synthetic fixture, actual steps and results, non-sensitive request/statement/provider references, defect or review reference and the accountable approver. Never include real PINs, OTPs, access tokens, raw identity evidence or bank account details in a publication pack.

A passing lesson does not certify the whole product. A passing build does not certify provider activation. A published manual does not certify a currently failing workflow.
