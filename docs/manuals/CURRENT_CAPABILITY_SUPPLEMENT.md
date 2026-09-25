# Current capability supplement: clubs, Essentials and release evidence

Status: Controlled task, training and acceptance reference  
Reviewed: 25 September 2026  
Language: English (United Kingdom)  
Authority/closure verification candidate: `e336daf4962cb735d1ff0b2360690b1eaffab83e`

Read with the [user](OPFIN_USER_MANUAL.md), [training](OPFIN_TRAINING_MANUAL.md), [operational](OPFIN_OPERATIONAL_MANUAL.md) and [UAT](OPFIN_UAT_MANUAL.md) manuals. The [24 September evidence](../operations/DELIVERY_EVIDENCE_2026-09-24.md) is a historical record, not a live deployment status. The [25 September verification record](../operations/ESSENTIALS_AUTHORITY_VERIFICATION_2026-09-25.md) distinguishes tested source, independent approval and actual deployment. These tasks are not a blanket production acceptance certificate.

## 1. Investment-club treasury workflow

### Before starting

Use an authorised club/group Space and verify your role. Keep training data synthetic. Establish the account currency, opening balance and opening date from approved source records. A cashbook opening balance is not proof of a bank balance.

The detailed CSV import and reconciliation workflow remains a Web administration task. App statement access does not demonstrate that every treasury administration action is mobile-complete.

### Record and reconcile

1. Select the intended Financial Space and treasury account. Confirm the Space/account names and currency before proceeding.
2. Record supported receipts and payments with amount, direction, transaction/value dates, description and reference. Do not alter source dates merely to avoid a validation error.
3. Upload the account's CSV statement and map its date, description, reference, debit, credit and running-balance columns as applicable. Review currency and minor-unit interpretation before import.
4. Inspect parsed rows, duplicates, suggested matches and unmatched book entries. Import alone is not confirmed reconciliation.
5. Match only when source evidence supports the correspondence. For an external-only item, duplicate, new book entry, book-only item or balance variance, record the applicable decision and reason.
6. Complete required review tasks before confirmation. Accepted differences remain visible; confirmation must not invent a balancing entry or erase the difference.
7. Issue the account or consolidated statement for the intended period. Check currency-separated totals, period and frozen statement identity. Use its HTML print view or CSV export as appropriate.

Generated OpFin statements represent recorded and reviewed information. They are not bank-issued statements and are not evidence of complete member-capital, NAV/unitisation, distribution or investment-performance accounting. The separate club-accounting completion branch must pass its own integration and acceptance before those workflows are described as released.

### Trainer exercise and expected arithmetic

Create a synthetic UGX account with a 1 September opening balance of 1,000,000. Record a 250,000 receipt on 5 September and a 100,000 payment on 10 September. The expected closing balance is 1,150,000. Import the corresponding two-row statement and verify matching, variance, confirmation and issuance.

The opening-baseline regression reported on 24 September was repaired by PR #116. Later test evidence supersedes the earlier failure claim without changing the historical record. The fixed baseline must still reject transactions before the genuine opening date. Record the result for the actual training build; do not change correct source dates or disable the guard.

## 2. Essentials: safe explanation and controlled practice

### What the learner must understand

Essentials arranges purpose-bound bill or rent finance from the identified participating lender. OpFin must not be presented as a lending-only platform. The financed amount is intended for the verified biller or rental beneficiary rather than unrestricted cash-out. Lender limits do not add up into unlimited headroom.

Remaining internal financial-control findings prevent describing the whole Essentials lifecycle as launch-certified. Do not use this module to invite live borrowing, debt creation or real provider settlement before the relevant internal and external gates are closed.

### Controlled test procedure

With an approved synthetic, non-money-moving setup, demonstrate account/beneficiary capture, verification state, lender eligibility, amount selection and exact offer disclosures. Explain lender identity, provider amount, fees/interest, total repayment, due dates and provider-unavailable behaviour.

Where the fixture permits acceptance, retain the quote/disclosure reference. Inspect lender funding, fulfilment and repayment states separately. A successful request is not an issued token, paid rent or confirmed repayment. Do not repeat an ambiguous collection with a fresh key.

Demonstrate connected-platform permissions and revocation. An omitted Space means the existing Personal Space, not all Spaces. An eligibility grant does not disclose customer accounts, other-Space debts or raw credit-decision evidence. The revised partner response exposes only its limited eligible lines and bounded availability. A programme-partner identity is not a substitute for a lending-platform role.

For a busy-customer 409, inspect the existing operation's state before retrying. The customer mutex does not automatically retry a provider call. A denied/removed/deleted customer must retain the appropriate authorisation/not-found response rather than being treated as a provider outage.

### Operations acceptance

PR #118 implements exact-Space authority, response minimisation, open-obligation closure checks and a shared customer-operation mutex. Candidate `e336daf4962cb735d1ff0b2360690b1eaffab83e` passed 358 tests and 2,362 assertions on both SQLite and PostgreSQL 18, including separate-session lock contention. The [verification record](../operations/ESSENTIALS_AUTHORITY_VERIFICATION_2026-09-25.md) records the scope and limitations. Required independent financial approval and production operating evidence are separate from these passing tests.

Expected immutable accounting, durable provider references/instructions, pending-collection reservations, complete repayment/reversal reconciliation, pending exposure and capital-mandate lifecycle still require their own acceptance. Serialising a running method does not reserve a provider collection that remains pending after the method returns. Do not teach it as a complete solution to overcollection.

Complete genuine lender/biller/provider agreements, credentials, approved terms and recovery exercises separately. It remains incorrect to say that only external credentials are missing.

## 3. Location and programme guidance

Location remains optional for baseline financial use. Demonstrate purpose-specific permission, manual fallback and denied unauthorised access without exposing a provider key. Do not claim background tracking or location-based credit decisions where the product contract prohibits them.

Programme check-ins remain voluntary and require applicable consent/enrolment. Financial health and programme outcomes are not credit scores. Reviewed translation and English fallback must be shown honestly; a language option does not prove every questionnaire is translated. Partner exports remain aggregate and privacy-suppressed.

## 4. Additional acceptance cases

These identifiers extend, rather than renumber, existing UAT cases. Expected results are requirements; a case is not passed solely because it appears here. Automated evidence applies only to the cited revision and covered scenarios, not unperformed device or operating exercises.

| ID | Scenario and procedure | Expected evidence |
| --- | --- | --- |
| DEL-TR-01 | Record historical movements after setting a fixed opening baseline | Baseline remains fixed; accepted dates and balances reflect source records |
| DEL-TR-02 | Re-import the identical account statement | Existing import is reused or an explicit duplicate result returned; no duplicate economic entry |
| DEL-TR-03 | Present one statement row with multiple possible matches | Ambiguity stays in review; no silent many-to-one match |
| DEL-TR-04 | Accept a justified variance and issue a statement | Decision/reason retained; difference not fabricated away; issued snapshot stable |
| DEL-TR-05 | Issue a consolidated report across UGX and another currency | Separate balances; no unsupported conversion/grand total |
| DEL-ES-01 | Complete funded advance and repayment transitions in a fixture | One expected balanced immutable event per financial transition, including replay |
| DEL-ES-02 | Submit two concurrent repayments and an additional request while provider confirmation is pending | Neither in-flight nor pending collections can exceed permitted outstanding amounts; test these distinct cases |
| DEL-ES-03 | Use another or omitted Space context, including eligibility-only access | Target authority enforced; eligibility does not return unrelated account, debt or credit-evidence collections |
| DEL-ES-04 | Request deletion with pending, active or overdue Essentials exposure | Required obligations, records and servicing access remain intact; the shared customer mutex does not move native provider commit boundaries |
| DEL-ES-05 | Keep lender funding or reversal unresolved and refresh profile | Exposure remains reserved until authoritative resolution |
| DEL-ES-06 | Select an approved, funded capital mandate | Eligibility/reserve/deploy/release/reverse lifecycle works and is audited |
| DEL-REL-01 | Compare main and all running production services | Exact source/version, migrations and health recorded per service; no inferred parity |
| DEL-DOC-01 | Compare original plan, current docs, UI and API | Requirement changes labelled; pending acceptance not advertised as certification; stable source references |

## 5. Evidence record for a completed task

Record task ID, tester/role, source commit, build/environment/channel, synthetic fixture, actual steps/results, non-sensitive request/statement/provider references, defect/review reference and accountable approver. Never include real PINs, OTPs, tokens, raw identity evidence or full bank details in a publication pack.

A passing lesson does not certify the whole product. A passing build does not certify provider activation. A published manual does not certify an untested or still-failing workflow.
