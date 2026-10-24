# OpFin B2B Institutional Delivery, Migration and Acceptance Programme

**Status:** Proposed P0–P2 implementation programme; no provider activation or deployed feature is inferred  
**Prepared:** 24 October 2026 • English (UK)  
**Product authority:** [B2B Institutional Blueprint](../product/OPFIN_B2B_INSTITUTIONAL_OPERATING_BLUEPRINT.md)  
**Capability source:** [Readable Register](../product/OPFIN_INSTITUTIONAL_CAPABILITY_REGISTER.md) | [Machine-readable Register](../product/OPFIN_INSTITUTIONAL_CAPABILITY_REGISTER.json)  
**Existing authority preserved:** [Financial Spaces](../architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md), [Financial Change Governance](../FINANCIAL_CHANGE_GOVERNANCE.md), [Product Blueprint](../product/OPFIN_PRODUCT_BLUEPRINT.md).

## 1. Programme structure

Use separate **independently viable product workstreams** and one reviewed common-tools workstream:

| Workstream | Named deliverable | Independence criterion |
| --- | --- | --- |
| B2B-FOUNDATION | Institution profile, onboarding, roles, entitlement, migration, accounting boundary, billing, support, role-specific Web/App navigation | Institution can configure and operate a lawful management-only plan without any finance marketplace |
| B2B-CLUB | Complete investment-club member capital, treasury, valuation, governance, approved accounting and member access | Club can close/reconcile/report its own books without an OpFin investment product |
| B2B-SACCO | Complete lawful SACCO member, share/saving, guarantee, loan, GL, statement and compliance journeys | SACCO accounting and management do not need an OpFin lender or white-label activation |
| B2B-GROUP | Full Savings Group/VSLA cycle, member passbook, loan/welfare and reconciled share-out on affordable Android | Core group journeys work without desktop and without lender/insurer integrations |
| B2B-LENDER | Independent lender product/funding/origination/servicing/portfolio reporting | External lender of record; existing authorised channels grow without a programme-wide moratorium |
| B2B-PEOPLE | Ten-module People product and optional finance bridge | People backend and HR remain usable during OpFin finance outage |
| B2B-OTHER | Funds, insurers, payments, programmes, merchants, technology partners and welfare society tracks | Each has distinct contract, source-of-truth, licensing, role and customer journey, not mandatory cross-product dependencies |

Every workstream keeps its own backlog, UAT cases, metrics, costs and risk/approval owners. Common infrastructure is reused, but failures in another product cannot suppress unrelated healthy institutions or personal financial-life use.

## 2. P0: foundational tasks

| Code | Required work and acceptance | Authoritative source/gate |
| --- | --- | --- |
| INST-001 | Inventory actual institution types, routes, modules, Financial Space capabilities, menu visibility and supported mobile/Web task parity. Assign accountable role to each register module. | Server `apps/api/config/opfin_service_registry.php`, Financial Space role policy, exact branch/source |
| INST-002 | Define versioned Institution Profile / Readiness evidence: legal entity, jurisdiction, director/signatory authority, KYB, regulator by product, custody/funding, permitted business, expiry and evidence. Store sensitive originals securely, with review and non-live training states. | Organisation onboarding service + product activation gates; no fake approval |
| INST-003 | Independent governance: creator/reviewer/approver/checker/recipient, mandate/threshold/expiry, self-approval prevention, delegated authority and revocation across all modules. | Server-side Space and operation permission; immutable actor logs |
| INST-004 | Account classification: distinguish membership fee, paid-up share, withdrawable savings, group pledge, member capital, asset valuation, benefit claim, lender asset and OpFin revenue. Preserve exact currencies, dates and immutable corrections. | Source accounting contract and finance sign-off |
| INST-005 | Money action states and recovery: unique original operation, effective quote, approval, provider submission, uncertain, settled, journalled, reversed, reconciled. No duplicate financial action after network uncertainty. | Financial-space action intents, provider callbacks and matching |
| INST-006 | Institutional migration pipeline: templates for non-technical XLSX/CSV records, mapping, dedupe, period/opening balances, original file hashes, human exception review, maker/checker opening approval, staged correction and verified import. | Club/Financial Intelligence importer reuse; no invented historical adjustments |
| INST-007 | Distinct institution vs member views. Personal Space remains private; no employer/SACCO/group cross-Space disclosure. Logout, revoked roles and former members pass negative cases. | API permissions + Flutter/Web client UAT |
| INST-008 | Optional integrations (payments, messages, identity, consent, data, branded host) use actual partner contract and operational owner, with a fail-closed default. No direct provider guessing or unsafe automatic failover. | Verified adapter, access purpose, idempotency and certification |
| INST-009 | Institutional SaaS pricing: contract, effective-period rules, organisation scale, invoicing, zero-fee intent vs missing fee, cost events, commissions/tax, cancellation/data return, reconciliation and support SLA. | Approved commercial agreement, costs and audit |
| INST-010 | Light/low-data accessible journeys on affordable Android, bounded pending drafts, explicit stale data and proper multi-Space isolation. No offline payment finality or private unencrypted caches. | Low-data standard and independent security/device acceptance |
| INST-011 | Source→tested→deployed→activated→operationally accepted status shown distinctly in internal register and commercial material. | Exact SHA, provider and regulator evidence |
| INST-012 | Customer/member complaints, hardship, import error support, administrative case recovery, audit and record export. | Tenant permission, lawful record retention, ownership |

### No replacement of working systems

- Reuse Investment Club ownership/NAV and cashbook accounting rather than create an alternate book.
- Extend SACCO domain types and subledger correctly; do not represent a savings liability as an ordinary club capital account.
- Reuse existing member settlement journals and checked invoices/approval machinery wherever ownership allows.
- Keep OpFin People independently viable; embed user experience in the existing app without coupling payroll logic to personal finances.
- Keep personal B2C Financial Compass and provider/financial-service domains unaffected.
- Preserve existing authorised money routes and continue appropriate lender/investor expansion without arbitrary time/vintage freezes.

## 3. P0: institution-specific release acceptance

### Club: B2B-CLUB

1. Form/regularise an Investment Club, appoint officer/member roles and import a realistic imperfect multi-sheet workbook.
2. Stage opening balances, dated contributions, member capital and ownership; reject duplicate/negative/ambiguous records; approve by different authorised users.
3. Propose an investment, approve with an independent checker, record permitted acquisition with actual supporting evidence and correct currency/account.
4. Record valuation change and investment income separately from externally confirmed cash; reconcile cashbook with issuer source statements and exceptions.
5. Generate correct capital calls, permitted member transfers and period close; freeze statement with hash and original history preserved.
6. Record proposed distribution with entitlement date and approval; reconcile actual externally executed payout and prohibit self-approval/replay.
7. Former member sees only own historical entitlements under lawful permissions, not other members' books.
8. In Flutter, members view capital, statements, relevant proposals and progress. Deep Web treasurer functions are clearly routed without pretending all administration is mobile.
9. Test multi-currency books, ownership changes, periods straddling member exits, source statement corrections and underlying settlement failure.
10. Collect independent finance sign-off and true Android/Web acceptance before marketing end-to-end club administration.

### SACCO: B2B-SACCO

1. Configure entity/regulatory profile and institution-approved share, deposit and loan products; no live money operation while authority pending.
2. Import KYC/member numbers/branch assignments, member shares, deposits and loan opening positions with source confirmation and checks.
3. Record a verified member share versus savings deposit as different accounting class/liability; trial balance and GL must reconcile.
4. Manage loan origination, guarantor request/acceptance/hold/release, independent committee approval and actual lender/funding authority.
5. Execute real-money actions only with externally confirmed receipt/settlement, no double posting, valid maker/checker, replay/partial failure.
6. Compute permitted dividends/interest through a preview, checker approval and immutable posting; never assume a distribution was paid.
7. Deliver member statement, due loan, arrears, receipts and complaint journey on mobile without leaking another SACCO's records.
8. Exercise branch/teller permissions, provisioning, period close, PAR/NPL, liquidity and actual Uganda-regime reporting only under vetted templates.
9. Reject mis-scoped licence attestation, expired product authority, unauthorised guarantee and a mismatched external receipt.
10. Obtain independent regulatory/finance and signed provider acceptance; source endpoints alone cannot activate live SACCO financial business.

### Savings Group: B2B-GROUP

1. Start group with officer roles and rules; create savings cycle and independently activate it.
2. Pledge member contribution; verify only exact matched external transfer, protect duplicate allocation and correct group/member ownership.
3. Separate savings, fines and social-fund liabilities, track member loan approval distinct from verified disbursement and repayment.
4. Record real meeting evidence and quorum according to group by-laws; manually asserted meeting is not a verified electronic vote.
5. Close an eligible cycle; draft share-outs from verified savings; independently approve and reconcile true payout, interest and offsets.
6. Let a member see own record/history and a treasurer prepare allowed tasks on low-end Android, with simple language and accessibility.
7. In airplane mode, record only permitted non-final drafts; sync with bounded original ids, conflict alerts and no silent overstatement.
8. Simulate lost phone, low storage, revocation, replay, network reconnection and group exit.
9. Test no finance partner, no mobile money route and no external payment execution: member records and governance must remain useful.

### Lender: B2B-LENDER

1. Complete institution KYB/regulatory/country classification and contractual lender-of-record evidence; leave service inactive when missing.
2. Configure product/channel/tenor/pricing rules and funded capital mandate with actual approved amount and owner.
3. Obtain customer consent, perform affordability/assessment, freeze legal offer/terms, accept under valid channel policy.
4. Submit to provider, preserve original transaction reference and correctly distinguish pending/rejected/settled disbursement.
5. Service repayments, partial and early settlements, reversals, hardship, collection, dispute and record retention with financial consistency.
6. Demonstrate lender-specific product, funding, PAR/NPL, provisioning, capital concentration and real contribution margin.
7. Verify no other institution or affiliated lender can access/respond to a customer without a valid agreement, authority and tenant permission.
8. Test no silent route switch after ambiguous provider response and no fabricated funding balance.

## 4. P1–P2 institutional slices

| Workstream | Named acceptance outcome |
| --- | --- |
| People (P1) | All ten mandatory People modules operate end-to-end with real payroll rule approval and employee privacy; standalone service works independently |
| Investment fund (P1) | Valid mandate, lawful investor rights/suitability, custody evidence, correct income/valuation, concentration and investor report |
| Insurer/broker (P1) | Provider confirmed policy, premium reconciliation, renewal, claim evidence and actual insurer authority |
| Payments (P1) | Correct provider callback, settlement ledger, reversals, fees, SLA and duplicate recovery |
| Programme (P1) | Consent and participant exit, real instruments, privacy-suppressed outcomes, field translation and partner reports without credit-data leakage |
| Merchant partner (P1) | Customer accepts intent in their own Space, verified merchant/source grant, distinct lender review and contract-scoped fee record |
| Fintech/API (P2) | Scoped institution integration, reviewed OpenAPI schemas, owner-controlled DNS/TLS, contracted usage and tenant isolation |
| Welfare society (P2) | Member dues, approved benefit rules, maker/checker claims, reconciled payout and member reports |

This is a parallel work plan. One workstream's maturity does not imply another's legal or transaction capability.

## 5. Common UAT matrix

For each institution type, test:

| Dimension | Minimum cases |
| --- | --- |
| Roles | Admin, ordinary member, former/suspended member, maker, checker, auditor, external partner, platform role |
| Data | Empty, partially imported, historic periods, duplicated source, missing fields, multi-currency and inconsistent opening balance |
| Access | Own Space, wrong Space, switch Personal→institution→Personal, revoked grant, expired membership, employer vs Personal |
| Money | Proposal, approval, ambiguous provider result, retry with same idempotency key, partial settlement, reversal, negative balance, period close |
| Device | Low-end Android, accessibility, large text, offline permitted tasks, abrupt termination, storage quota, sync conflict and account change |
| Provider | Sandbox/certified integration, outage, wrongly signed event, timeouts, duplicate callbacks, mismatched external amount and reconciliation |
| Compliance | Current institution/product/country rule, minor/guardian where applicable, purpose-bound consent, exported evidence, legal record retention |
| Commercial | No pricing, explicit zero, real fee, contract expiry, partial charge, provider cost, billing dispute and contribution margin |
| Reporting | Amount/time correctness, role scope, report history immutability, reconciled status and no invented cross-currency totals |

For every case capture: **institution type, actor/user/Space, candidate SHA/environment, operation, expected and observed outcomes, independent financial evidence, device/browser, defects, sign-off and links**.

## 6. Approval and delivery gates

- **Gate A — Scope accepted:** Product owner signs required modules and independent business outcome per institution.
- **Gate B — Source verified:** Engineering verifies exact current functions, owner/boundaries and negative tests. Static file references do not constitute complete operation.
- **Gate C — Technical and security:** API/Web/Flutter tests; DB/migration/backup and restore; security; data import; multitenancy; low-data and accessibility/device tests.
- **Gate D — Financial integrity:** Correct ledger/custody, provider confirmations, reversals, reconciliation, ECL/provision where relevant and independent qualified accounting sign-off.
- **Gate E — Legal/product activation:** Evidence-backed institution authority, contracts, product rules, fund/custody/insurer/provider identity, consumer disclosures and compliance acceptance.
- **Gate F — Commercial and operational:** Contracted pricing/support, staff training, member usability, cost-to-serve, monitor/incident ownership and production rollback.

**Management-only scope** can be accepted with Gates A–C and the applicable privacy/commercial parts of F when D/E financial services are not used. **Live money service** additionally requires D and E for the specific institution/product; never mark an entire multi-service institution 'regulated and ready' as a blanket permission.

## 7. Active programme management

Suggested first planning actions:
1. Assign one institution-product owner to each of the 12 register types, and product/security/finance counterparts to the 41 capability areas; record actual responsible people outside GitHub as appropriate.
2. Use existing route and client trace to mark exact source presence and accepted release evidence rather than percentage-complete estimates.
3. Gather a small real-like anonymised migration pack for a Club, SACCO and Savings Group; measure correction loops and initial operating value.
4. Create accepted capability-level GitHub issues on receipt of owners and scoped evidence; do not open 41 placeholder feature PRs or confuse planned work with completion.
5. Complete common source/financial boundary reviews before new on-device caches, ledger alternatives, provider APIs or production feature flags.
6. Test institution adoption, product economics and measurable financial/administrative outcomes; revise packaging and operational roadmap from evidence.

Release authority belongs to existing OpFin controls and named operating owners. This programme does not approve provider integrations, deploy production services, create new infrastructure, modify an existing financial balance or bypass any current security gate.
