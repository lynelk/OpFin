# OpFin financial assurance and shared-credit classification decision pack

**Prepared:** 17 October 2026  
**Accounting status:** **PROPOSED, NOT SIGNED, NOT CERTIFIED, NOT LIVE**  
**Source basis:** existing OpFin canonical general ledger and lender-ECL code on draft PR #182. No authenticated external institutional bank statements, third-party GL extracts, executed co-lending documents or independent model validation report were accessible for this review. Do not treat synthetic test evidence as a real bank or regulator approval.

## Temporary documentary-evidence deferral (decision recorded 17 October 2026)

**Instruction:** Defer the following five external inputs *for development, isolated testing and illustrative accounting only*, until their requirements are reinstated by an authorised business decision:

1. Actual institution-specific bank and payment-provider statements and external source verification.
2. Signed institutional lender general ledgers, trial balances and complete loan registers.
3. Executed direct co-lending, risk-sharing, participation, first-loss and guarantee agreements.
4. Independently validated IFRS 9 impairment methodology and qualified model opinion.
5. Genuine named Finance, Credit Risk, Treasury, Legal and independent-model-review decisions.

**Status of each: `DEFERRED_NOT_APPROVED`.** No fake supporting documents, dummy legal signatures or source approval records may be presented as genuine. Deferred is not waived, reconciled, verified, accepted, or complete.

### Work allowed during the deferral

- Implement, validate and refactor separate OpFin and external lender receivables, participant funding, provider custody, collections, fee allocations, ECL, recovery, write-off and reversal accounting.
- Test with explicitly synthetic user/loan/lender/provider records, seeded QA balances and fixture contracts.
- Preview accounting classifications without external evidence via `POST /api/admin/institutional-assurance/shared-accounting-preview` or the administrative preview page, only in `local`, `testing` or `staging` when `opfin.accounting.defer_institutional_evidence_for_nonproduction=true`.
- Continue independent automated QA and reconciliation arithmetic using synthetic matched or deliberately mismatched accounts.
- Mark unfinished validations as **provisional engineering assumptions**, and retain exception records for later assessment.

### What is not authorised

- Do **not** post synthetic journal illustrations to a real or production GL or send provider disbursement instructions.
- Do **not** approve a credit-risk owner or lender contract merely because a test script or dummy document reference exists.
- Do **not** recognise one lender's loan balance as OpFin's asset or income on the basis of a percentage unless actual legal rights and the selected recognition model are established.
- Do **not** enable real-money shared-loan origination or bypass the production `InstitutionalExposureAccountingService::approved()` fail-closed shared-loan gate.
- Do **not** imply professional IFRS 9 certification, audited bank reconciliation, or qualified Finance/Risk/Treasury approval.
- Do **not** confuse this temporary development deferral with a regulator's ability to waive statutory or prudential obligations.

**Reinstatement procedure:** set `OPFIN_DEFER_INSTITUTIONAL_ASSURANCE_EVIDENCE=false` in non-production environments, disable the preview, and resume the five-category evidence collection and independent acceptance schedule. Production is *always* excluded from the preview independently of this setting. Actual execution of shared-loan ledger treatment will require a separate code and financial acceptance gate.

## 1. Four-ledger reconciliation required, institution by institution

| Source ledger | Source proof | Exactly what must reconcile |
| --- | --- | --- |
| Bank or provider custody | Original bank/provider statement file, validated issuer, time window, currency, file SHA-256, independent statement ID | Institution/provider-specific funds held in cash ledger, with signed opening and closing balances and cleared versus pending transfers |
| OpFin ledger and loan subledger | Immutable transaction/entry IDs and posted cut-off | Cash debit/credit, third-party funds liability, OpFin **owned** receivable, posted impairment and movements; no lender contractual interest or receivable misclassified as platform income |
| Institution's loan general ledger | Signed lender trial balance and complete loan-level schedule with SHA-256 | Outstanding funded principal, interest accrual, write-offs, ECL stage and allowance; discrepancies to customer loan records and signed ECL cut-off documented |
| Independent monthly ECL population | Finalised OpFin ECL run and original source MIS, corrections and restatement history | Total loan count, principal, stage transitions, delinquency, loan-level EAD/PD/LGD and movement between periods |

Every institution and ISO currency is an independent reconciliation perimeter. Exact money values are integers in minor units. A funder mandate is **not** proof of cleared cash. Cash held for Institution A cannot be borrowed for Institution B. Actual payment-provider and bank legal ownership must be separately confirmed. Source authenticity is **not** established by a self-declared SHA digest alone.

### Acceptance tests

1. Load source statements with actual bank/provider references and original file digests, and lender GL extracts, without truncation, fabrication or silent balances.
2. Match *each provider's* statement closing balance to `cash.partner_custody.institution_{id}.provider_{name}`, as-of the declared cut-off.
3. Match lender funds held to `liability.partner_funds_held.institution_{id}`, then reconcile bank total, any explicitly approved in-transit amounts and legally held monies.
4. Match the **external lender's** loan principal and count to the **signed** ECL external-population control totals. This does not imply they are OpFin's balance-sheet assets.
5. Independently verify OpFin-owned principal and allowances against the OpFin GL, with current-period correcting journal plans for older wrongly recognised third-party exposure.
6. Reject incomplete provider populations, duplicated source references, unexplained variances, unsigned lender sources, missing risk-owner evidence and changes to previously approved source records.
7. A checker different from the preparer must accept an exact match. Review evidence by **Treasury**, **Finance**, **Credit Risk**, **Legal** and a qualified **independent model validator** must be supplied separately. Their actual capacities must be verified outside the application. Five different login IDs are an internal safeguard, not proof of professional qualifications.
8. Signed controls remain append-only, with immutable source hashes and corrections retained. No posted closed-period balances are silently overwritten.

The new `institution_finance_reconciliations` service stores source-declared totals, calculates actual GL matching and blocks acceptance when a value differs or a ledger cut-off has changed. This is a controlled **intake workflow**, not a claim that real source files have been checked.

## 2. Shared-exposure legal and accounting decision matrix

The precise right to contractual loan cash flows and legal guarantees determines the financial statement, rather than a single displayed economic risk percentage.

| Contractual form | Financial statement principle | Minimum additional deliverables before any origination |
| --- | --- | --- |
| **Direct joint-creditor co-lending**, separate enforceable pro-rata creditor rights | Each legal lender recognises **its own legally owned portion** of the receivable where recognition conditions and cash-flow classification are met; its ECL/interest and derecognition follow its own contract. OpFin's servicing of another lender's portion is agency, not OpFin interest income | Executed agreement identifying debtor rights, principal/interest/fees pro-rata splits, funding source per party, receivable ledgers, exact rounding, borrower consent, provider custody, collections waterfall, own-share ECL, reversed payouts, delinquencies, settlement and write-off tests |
| **Funded participation, one lender of record** | Do **not** assume a pro-rata asset. Determine whether contractual cash-flow rights transfer, whether risks/rewards transfer and whether derecognition or continuing involvement is achieved. The reporting lender may retain the whole asset and recognise a financing liability depending on the actual terms | Legal derecognition memorandum applying IFRS 9 sections 3.1–3.2; participation-funding account and lender payable, independent valuation, retained interests and stress testing |
| **Financial guarantee contract** | Do **not** book a share of the underlying loan merely because the guarantor absorbs losses. Subject to applicable IFRS 9 scope, recognise the guarantee liability initially at fair value and subsequently apply the higher-of ECL or the unamortised initially recognised amount, unless other classification applies | Signed guarantee trigger and cap, obligor and beneficiary, initial valuation/premium, discount factors, default ECL, guarantee calls, income allocation and insurer/guarantor regulatory authority |
| **First-loss / subordinated risk-share** | No default percentage-of-asset convention. It can be a financial guarantee, first-loss tranche, retained credit-risk transfer or other arrangement requiring case-specific classification and ongoing measurement | Contract seniority, claim priority and waterfall, counterparty exposures, expected losses, timing/cash-flow model, fair value, legal enforceability, derecognition and capital-treatment assessment |

Reference: IFRS Foundation IFRS 9 Financial Instruments, sections 3.1, 3.2, 4.1, 4.2 and 5.5, together with relevant application guidance and issuer guarantee accounting. Guidance and local supervision must be confirmed by the relevant qualified reviewer. The actual executed contract can override the provisional architecture examples.

### Proposed general journal design, NOT approved for live posting

**Direct joint-creditor illustration, if both claims are independently legally recognised:** for 40% OpFin / 60% external lender ownership and UGX 100,000 net cash to the borrower, a fully validated disbursement would need the OpFin receivable debited 40,000, the lender-held funds liability debited 60,000, and the correct verified provider cash account(s) credited 100,000, **subject to each provider's own liquidity and funds-transfer evidence**. Separate booking/reconciliation is required where OpFin uses a different funding provider, and fees can change the journal. On repayment, own-share contractual principal and interest must be distinguished from partner collections, which credit the lender payable. Stage-3 interest, write-offs and ECL cannot be calculated from a single full-loan balance in the existing legacy loan books.

**Financial guarantee illustration:** record the issuer's initially valued obligation and separately updated guarantee-loss exposure; it is *not* a 40% debit to a cash-loan receivable. Guarantees may have different loss triggers, fair value and capital treatment.

**Do not use these illustrative entries as production GL templates** before signed legal opinions, IFRS 9 valuations, model validation, approved chart of accounts, end-to-end double-entry and provider test acceptance.

## 3. Five independent approvals

The system captures the following **actual reviewer declarations** against matching source documents; these fields are not themselves an independent external auditor's opinion.

- **Treasury:** confirm original bank files, provider IDs, bank ownership, matched cash, escrow/trust restrictions, settlement/payout reversals and funding restrictions.
- **Finance:** confirm entity recognition/derecognition, chart of accounts, net versus gross balances, interest/fee treatment, historical correction journals, taxation, reporting cuts and disclosures.
- **Risk:** confirm borrower exposure and portfolios, credit-loss allocation, LGD, recovery rights, risk participation, stages, EAD and control exceptions.
- **Independent model reviewer:** inspect model design, historical default data, PD/LGD/EAD calibration, discounting and EIR, forward-looking weights, SICR, backtesting and IFRS 9 design gaps. They cannot validate the existing simplified PD × LGD proxy into full compliance merely by a signature.
- **Legal/compliance:** confirm enforceable creditor rights and liabilities, authorised lender and guarantor, direct/funded participation, privacy, customer disclosures and country permissions.

The completed acceptance pack must include reviewer identity/qualification, independently provided report, report SHA-256, decisions/conditions and genuine approval authority. A finance administrator may record an attestation but may not invent an external opinion or sign for another professional.

## 4. Current engineering behaviour

- Signed bank/lender external totals are compared against the actual immutable provider-cash, partner-liability and finalised ECL source cut-off. Unexplained differences **cannot** receive reconciliation approval.
- Preparers cannot check their own cut-off. Reviewers cannot sign more than one capacity under the same cut-off or classification. Source data and reviews are immutable.
- Legal contract forms can be classified with documents and independent reviewer attestations. **Every form remains non-live pending separately validated journal production.**
- Missing third-party source evidence cannot be treated as zero; passing **synthetic** test cases is not real financial acceptance.
- Shared-account origination in `InstitutionalExposureAccountingService::approved()` remains intentionally blocked in every environment, including production.

## 5. Real-world blockers and acceptance outcome

| Requirement | Current evidence | Decision |
| --- | --- | --- |
| Live institution bank/provider reconciliation | No original external bank or verified provider statement supplied or accessible | **Not certified** |
| Lender general ledger and full loan register | No signed lender trial balance / loan-book dataset supplied or accessible | **Not certified** |
| Independent IFRS 9 model validation | No qualified independent report or historic calibration dataset | **Not approved** |
| Treasury/Finance/Risk/Legal signatures | No authentic signed opinions or source-linked sign-offs | **Not signed** |
| Direct joint-creditor or participation GL split | Proposed treatment only; actual receivable, repayments, ECL, fees, write-offs and refunds require code and testing | **Shared origination remains blocked** |
| Synthetic application and concurrency QA | See `docs/verification/INSTITUTIONAL_ECL_FINANCE_ACCEPTANCE.md` | **Engineering evidence only** |

**Release disposition: FAIL CLOSED.** Do not merge/deploy a claim of financial certification or enable real-money shared origination until complete source-ledger reconciliation and legally authorised approvals are attached and verified.
