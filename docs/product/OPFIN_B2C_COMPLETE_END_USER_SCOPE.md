# OpFin B2C Complete End-User Capability and Channel Parity Contract

**Status:** Proposed canonical B2C coverage and acceptance addendum, for product/engineering/security review (not a production-certification claim)
**Prepared:** 24 October 2026 • English (UK)
**Scope:** Every OpFin individual/customer/member experience available or planned under the canonical Product Blueprint, including Personal Financial Life, financial products, Financial Spaces, governed add-ons and access channels.
**Sources:** [Product Blueprint](OPFIN_PRODUCT_BLUEPRINT.md), [Canonical Product Breakdown](OPFIN_CANONICAL_PRODUCT_BREAKDOWN.md), [B2C Financial Companion Strategy](OPFIN_B2C_FINANCIAL_COMPANION_STRATEGY.md), [P0–P1 Universal Financial Life](OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md), [B2C Offline-First Contract](../architecture/B2C_LIGHT_OFFLINE_FIRST_CONTRACT.md), [Current State](../CURRENT_STATE.md), [Flutter source reference](../../apps/client/README.md).

## 1. Rules of completeness and prioritisation

**North Star:** Every financial life deserves a future worth looking forward to.
**Promise:** Your next step, clearer.

B2C is **the complete end-user surface of OpFin**, not a stripped-down budgeting or wellness micro-app. It must cover all applicable consumer functions, service-specific authorisation, customer rights and member roles. This is a **full-coverage scope**, not a representation that all targets are already available or activated. Existing authorised journeys must not regress as new B2C prioritisation is introduced.

Three concurrent requirements:
1. **Better financial decisions:** evidence-aware interpretation, personal planning, suitability/eligibility boundaries and action.
2. **Earned, voluntary reliance:** continuing financial memory, practical routines, commitments, timely assistance, saved context, portable history and support.
3. **Light offline-first inclusion:** normal non-transactional activities should continue with no or intermittent internet and on inexpensive devices, subject to properly protected on-device data. Online-only actions remain safely discoverable, never simulated as completed.

**Completeness ≠ making every regulated transaction executable offline.** Functional parity means the appropriate user journey remains available across supported channels and transitions safely from local planning to authenticated online execution. Offline financial finality, unsupported identity verification, loan approval, provider custody or payment settlement would **compromise security and correctness**. They must remain online and provider-confirmed.

Treat each core feature and add-on as independently useful with optional integration. An external lender, insurer, investment custodian, Stolets, People, SACCO or club service cannot become a runtime prerequisite for normal individual financial wellbeing. Roles and products must retain their own legal responsibilities and data boundaries.

## 2. Every end-user capability: inventory and offline boundary

Legend:
- **Local:** safe local operation without network, if bundled or secure local store is present.
- **View/cache:** only previously authorised, protected, freshness-labelled data; never guarantee current external state.
- **Draft:** tentative local record awaiting validation and synchronisation; only approved allowlisted non-final operations.
- **Online:** server/provider-authoritative action; unavailable offline, with explicit recovery path.
- **Gated:** availability requires product/regulatory, age, role, consent, employer/group and/or third-party activation.
- Source maturity varies; do not infer readiness from this catalogue.

| Domain | B2C minimum customer and member functions | Offline-first behaviour | Uncompromised requirements |
| --- | --- | --- | --- |
| Public information and onboarding | Brand, programme information, age-suitable learning, examples, calculators, terms, privacy, phone/OTP/PIN sign-up, account access and recovery | Bundled learning, guest money check **Local**; sign-up/login/recovery **Online** | Never bypass OTP, session, verified identity, guardian law or channel entitlement |
| Account, identity and KYC | Profile, multiple verified contacts, progressive NIN/ID/selfie capture, verification status, account recovery, trusted devices, security preferences | Information and status **View/cache** where safe; KYC submission, new verification, session revocation **Online** | No offline approval, cached raw ID artefacts or PIN/token logging |
| Consent and privacy | Explain, grant and where lawful revoke purpose-specific permissions, data use, recipients, export, access/deletion requests | Review safe previously stored explanations **Local/View**; authoritative consent grant/revoke/export/closure **Online** | No offline permission grant; records lawfully retained with redaction and auditable rights |
| Personal money and accounts | Cash, bank, wallet tracking; manual income/expenses; transfers as records, categorial tags; actual versus plan; cash-flow overview | Calculator **Local**; personal money entries **Draft** only when secure journal enabled; prior position **View/cache** | User report not verified money; online transfers not inferred from ledger entries |
| Budgets and calendar | Category budgets; income schedule, irregular/seasonal income, bill calendar, receivables/payables, debt reminders, planned vs actual | Plan and what-if **Local**; safe stored items **Draft/View** | Nonexistent/unknown transactions never become zero; reminders are not settlements |
| Financial Compass and insights | Recorded available/committed money, confidence, source timestamps, debt/assets, safe-to-spend estimates, next action, history | Last accepted snapshot **View/cache** if securely available; fresh server compass **Online**; local what-if separate | Stale timestamp and coverage; no invented provider values or offline eligibility |
| Goals, resilience and learning | Savings and family goals, education, emergency fund, hardship plan, recovery, milestones, personalised learning and progress | Goal drafts, offline lessons, approved local scenarios **Local/Draft** | Self-reported progress distinct from held assets; pause/revise/exit always permitted |
| Personal assets and liabilities | Asset/debt register, amounts owed, collateral details where applicable, valuations, net worth and provenance | Non-final changes **Draft** subject to secured storage; data **View/cache** | Estimates vs independent valuations, privacy and legal security interests distinct |
| Responsible borrowing / credit | Credit need, education, capacity, multiple lender comparison, score context, consents, KYC, terms, application, decisions, offers, disbursement, instalments, repayments, early settlement, hardship, disputes | Education, pre-application calculators **Local**; supported records **View/cache**; all offers, contracts, scoring, acceptance, funding, repayments **Online + Gated** | Actual lender/funding pool and policy; fees, affordability, disclosure, unambiguous legal approval and provider finality |
| Essentials and bill/utility finance | Understand necessary expenses and service obligations; approved purpose-bound bills, beneficiary verification, provider settlement, exception management | Bill planning **Local**; last state **View/cache**; financing, payee validation and payments **Online + Gated** | No imaginary utility account verification, cash-out, payment or completed settlement |
| Savings services | Plan savings, record external contributions, display provider savings products, initiate and monitor verified deposits/withdrawals, approved auto mandates | Planning and local recorded progress **Local/Draft**; confirmed custody or transfers **Online + Gated** | Custodian/provider of record; pending != confirmed; offline actions cannot debit |
| Investments / Grow | Learning, scenarios, provider catalogue, risk/fees, suitability, account holdings, orders, redemptions, statements and tax evidence where available | Education and what-if **Local**; provider statement **View/cache** with age; orders/redemption/suitability **Online + Gated** | Regulated-advice boundary, authorised provider/custodian, order finality, no guaranteed returns |
| Protect and insurance | Protection education, compare authorised products, quote/disclosures, purchase/enrol, premiums, policy detail, claims, appeal and support | Education and claim checklist **Local**; last policy **View/cache**; quote/enrol/payment/claim lodge and outcomes **Online + Gated** | Real insurer/underwriter, policy issuance, expiry, exclusions and claims authority |
| Participatory / marketplace finance | Investor/borrower learning, private loan books, requests, suitability, capital allocation, returns/losses and performance | Education and what-if **Local**; prior records **View/cache**; requests, investment, disbursement and release **Online + Gated** | Custody, concentration and consumer safeguards, actual lender of record, contractual rights |
| Asset / productive finance | Asset Passport, finance for vehicles/devices/enterprise assets, rental, security documents, repayments, asset claims and service | Basic product education and asset planning **Local**; authorised inventory **View/cache**; binding contracts/control commands/finance **Online + Gated** | Ownership, lawful security, consent; never covert device or location control |
| Future digital/exchange offerings | Eligible education, lawful asset/interest view, contracts, transfer, rights, restrictions | Education **Local**; safe last-known records **View/cache**; any execution **Online + Gated**, default off | Separate regulatory classification and approved custody/AML/market permissions |
| Payments, activity and receipts | Payment intent and history, transaction state, refunds/reversals, statements, receipts, fees and disputes | Cached reconciled receipts **View/cache**, draft expense records **Local**; initiate/confirm/reverse **Online + Gated** | Transaction idempotency, no silent retries, pending != settled, ledger reconciliation |
| Personal financial statements and intelligence | Upload/import personal statements, review parsing/source quality, challenge errors, consented analysis, exports and document organisation | Locally selected file *may be a pending protected draft* after review; accepted analyses **View/cache** | Raw documents protected; authenticity unverified until separately confirmed; export controls and consent |
| Personal Financial Passport and sharing | Attributable income, debt, goals and permitted evidence; recipient-specific consent, expiry, export and audit history | Offline read of locally permitted history only; share/grants/recipient delivery **Online** | No universal shared credit file; children/group/employer records not implicitly reused |
| Personal Financial Spaces | Choose Personal, Household, Group, Business, SACCO, Club and investment contexts; invitations, joining/leaving, roles, delegation | Explicit previously authorised Space **View/cache** if secure; changes, invites and authoritative membership **Online** | Strong user- and Space-scoped isolation; revoked membership must not leak records |
| Household and family finance | Shared budgets, delegated obligations, member consent, caregiver/guardian-approved planning, household goals and commitments | Individual plan **Local/Draft** where safe; shared member actions and permissions **Online** | Separate ownership; no presumed account pooling, guardian rights or household access |
| Savings Groups / VSLA | Join/create group, member registry, officials, rules, cycles, pledges, contributions, loans, social fund, approvals, meetings, goals, share-out, statements, audit | Offline learning and member reminders **Local**; safe individual draft/last statement **Draft/View**; formal approvals/contributions/disbursement/share-outs **Online + Gated** | Verified payments, quorum, maker-checker, group-role authority, reconciliation, product-specific regulatory review |
| Investment Club | Membership, capital and ownership, subscriptions, units/NAV, treasury/cashbook, investments, statements, reconciliations, approvals, distributions, export, historical member access | Individual planning **Local**; authorised issued statement **View/cache**; treasury posting, approvals, imports and distributions **Online + Gated** | Distinguish club-issued statements from bank/custodian evidence; immutable audit/financial record and maker-checker |
| SACCO / Cooperative | Member dashboard, shares/savings/loans, obligations, statements, member requests, authorised governance and administrative workflows | Education and personal plan **Local**; last-issued member document **View/cache**; institutional money and approval **Online + Gated** | SACCO legal status, products, officials' permissions, privacy and settlement |
| OpFin People / employer member | Employer relationship, employment status, benefits, payslips, leave, attendance, KPIs, assigned assets, authorised salary loan workflows | Prior permitted payslip **View/cache**, consented attendance **Draft** if scoped queue approved; employer actions and payroll deductions **Online + Gated** | Employer never receives personal finance by default; attendance != payroll; approved deduction != remittance |
| Business/merchant Personal Space | Personal view plus permitted business financial Space and optional merchant evidence from Stolets | Personal money planning **Local**; business Space safe records **View/cache** | Stolets independent product/database; consent and ownership verified at both ends |
| Programmes, education and inclusion | Voluntary enrolment, age/language-appropriate materials, check-ins, survey consent, reputation/non-score progress and support | Pre-bundled learning **Local**, limited non-sensitive response **Draft** if approved; consent/enrolment/measurements **Online** | Protected attributes not silent underwriting; small cohort suppression; safeguarding |
| Support, complaints and hardship | Help/FAQ, protected cases, complaints, escalation, hardship actions, receipts and case progress | Bundled safe guides **Local** and possible protected draft, case creation/status **Online** | Accessible human escalation, confidentiality, authenticated status and honest complaint timestamps |
| Customer subscription and premium tools | Free essentials, premium planning features, invoices, renewal/cancel and support | Personal free tools **Local**; offline advanced calculations if licensed/cached and safe; commercial activation **Online** | Paid subscriptions cannot be sole access to basic personal/group financial functions |
| Notifications, reminders and autonomy | Device preference, due alerts, meaningful events, user-defined cadence, pause/quiet hours, action history | Device-only reminders possible with permission and cached dates; central preferences **Online** | Never include private balances in unlocked push, manipulate distress or force daily streaks |
| Location and nearby service context | Task-based location for services/assets/claims and manual location alternative | Manual location **Local**; live maps and real partner data **Online + Gated** | No background surveillance; precision only where truly necessary and consented |
| Access, accessibility and low-data channels | Flutter Android/iOS full personal + normal Savings Group/club member tasks; Web deeper work; approved USSD/WhatsApp/assisted tasks; data meter | Native offline planned functions; USSD/SMS inherently carrier-dependent | Equivalence of authoritative records and safety; do not claim offline USSD/WhatsApp |
| Account closure and continuity | Logout, PIN/reset, data rights, recovery, leave group, delink integrations, export, retain contractual obligations | Local data wipe on logout and available safe offline user controls; server actions **Online** | No unreviewed account deletion with active obligations; lawful retained records still protected |

### Scope and maturity notes

- The matrix covers the **whole canonical B2C promise**, not just capabilities with implementation evidence.
- Existing source has personal money, Compass, repayment and lender workflows, several regulated-product foundations, group/workspace and club accounting candidates, Financial Intelligence and People membership/workspace paths. All must be traced to their actual active backend contract and tested state.
- Certain journeys (e.g. full marketplace, external fund custody, digital/exchange, statutory payroll, some group share-out settlement, youth accounts) remain targets or independently gated capabilities.
- **Never interpret "complete B2C coverage" as permission to display unavailable products as active or to remove operating/legal controls.**
- Institutional workspaces can use a larger Web surface for specialised treasury or large-scale administrative work, but essential individual/member participation cannot silently require a computer.

## 3. Product hierarchy, information architecture and provider independence

### Core shell
- OpFin Today: financial position, meaningful next action, upcoming event, entry to appropriate Financial Space and financial products.
- My Money: accounts, money diary, commitments, budgets, debt/credit obligations, receivables, assets/liabilities and activity.
- Progress / Ahead: goals, resilience, milestones, forecasts and practical guidance.
- Services: borrow, essentials, save, grow, protection and other activated services, each with distinct provider-of-record and eligibility.
- My relationships: Household, group, investment club, SACCO, employer, business, fund and other authorised Financial Spaces.
- Trust / Support: identity, privacy, settings, statement export, complaints, help and accessibility.

These are **conceptual discovery destinations**, not a mandate to add six new bottom navigation tabs. Resolve the documented Flutter/Web navigation discrepancy in a separate acceptance decision. Do not bury, delete or break any authorised path.

### Provider-neutral consumption
- The entire personal money, learning, planning, commitment, goals and manual financial-history loop works without activated partners.
- When a regulated service is selected, disclose actual lender, insurer, savings institution, investment custodian or payment executor, and retain provider legal responsibility.
- Specialty add-ons (Stolets, People, SACCO Tool, Savings Groups and Investment Club) remain independently usable; OpFin shows connected experiences only after customer/admin authority and integration acceptance.
- Failure of an add-on or partner does not take down personal financial records, coaching and authorised standalone functions.

## 4. Offline-first by default, without lying about online financial truth

### Network-limited journeys

**Airplane mode from install / guest:** show a small locally bundled learning route and an ephemeral first-value money check. Do not imply offline OTP or saved personal money records.

**Previously authenticated, session still authorised for local view:** where secure cache and expiry controls have passed UAT, view last-sync information, safely edit local financial planning drafts and see local reminders. Preserve label 'last updated' and cached-authorisation limitations.

**Previously authenticated but logged out / revoked / expired local authority:** no access to private cache. Offer offline guest education/check only. User-switch, lost device and revoked membership are explicit boundary tests.

**Connection resumes:** sync only approved and protected non-final operation types; customer sees pending, accepted and conflict states. Reconciling provider-money outcomes stays online and governed.

**Network outage during credit/payment/insurance/investment action:** preserve a non-sensitive reference to an uncertain request only when safe. Never auto-repeat, mark settled, switch provider or invent provider output. Check authoritative status on reconnection, show recovery/support path.

### Nonfunctional baseline

Reuse [Lightweight and Sponsored Data Standard](../architecture/LIGHTWEIGHT_SPONSORED_DATA.md) with its measured launch/home/batch/monthly-data targets and store artefact gates. The **real customer experience** must also verify budget devices, battery, first-run transfer, cold start, accessibility, app interruptions, data-storage size, secure account changes and real MNO connections. App size / fast UI claims require measured evidence. Data sponsorship must be carrier-confirmed, not inferred.

## 5. Security, financial integrity and release non-regression

Each applicable consumer domain must pass:

1. Correct person identity, active session, role/capability and Financial Space authority, both frontend and backend.
2. Separate consent and lawful basis for collecting, sharing, scoring or executing actions.
3. Source confidence, version, timestamp and distinction among user-entered, estimated, pending and confirmed.
4. Correct provider/licence/custody/funding/issuer/insurer attribution where relevant.
5. Transaction idempotency, ledger controls, maker-checker if applicable, refunds/reversals, exception handling and reconciliation.
6. Secured local storage (including encryption keys and cross-user isolation) before allowing offline financial records to persist.
7. No sensitive offline queue, logs, push previews, telemetry or screenshots unless explicitly reviewed and protected.
8. Cross-channel and supported-device parity with locally appropriate channel behaviour; no bypass through Web, assisted channels or offline mode.
9. Practical customer accessibility and understandable, non-coercive financial disclosures.
10. Failure recovery: interrupted uploads, revoked authority, partial sync, network replay, ambiguous provider state, offline logout and lost device.
11. Existing active feature non-regression in candidate-to-current-release test matrix.
12. Accurate statuses: design target, implementation candidate, tested, deployed, independently accepted and externally activated are not interchangeable.

**Release blockers:** false financial finality, offline unauthorised execution, cross-Space financial-data exposure, silent duplicate debits, incorrect provider-of-record, data loss on sync, unprotected sensitive offline records, unsafe child/guardian exposure and removing existing essential financial journeys.

## 6. Whole-product end-user UAT matrix

Test the customer as:
- Anonymous guest, newly registered person, returning person, self-employed irregular earner, debt-stressed individual, and low-literacy/low-end Android/low-data user.
- A person with a blank ledger, full manual ledger, stale provider record, unknown balance, multiple assets/currencies and recurring financial obligations.
- An individual with and without available lender/savings/investment/insurance/payment providers; and with multiple providers supplying independent decisions.
- A household member, guardian where approved, savings group member, group official, club member/former member, SACCO member, People employee, merchant/Business Space participant and institutional authorised officer.
- An App, Web and approved assisted-channel user with appropriate channel capabilities.
- A customer in normal connectivity, weak/intermittent network, airplane mode, app update, device switch, revoked consent, provider outage and expired session.

For each matrix cell record: intended task, supported channel, online/offline state, visibility, source of truth, actor authority, sensitive data exposure, API/provider boundary, evidence, final outcome and remaining acceptance gap.

Do not claim individual B2C completeness without end-to-end App acceptance for its essential functions. Do not claim every institutional administrator task is mobile-complete where the blueprint deliberately assigns deep administrative work to Web.

## 7. Portfolio of delivery work (no destructive rewrite)

- **P0 maintain:** no regression in all existing active B2C consumer/member journeys, security and financial-control acceptance.
- **P0 enhance:** personal first value without partners, local lightweight guest check, personal money consistency, income/expense quick record, goals, commitments, safe status labels, non-credit Home and accessible outage state.
- **P0 architecture:** privacy-reviewed encrypted offline journal if intended for persisted money data; operation allowlist; account/Space isolation, idempotent sync, conflict matrix and state labels.
- **P1 expand:** safer local planning, optional device reminder engine, scenario foresight, contextual learning, whole-financial-cycle progress and data-quality-aware financial health.
- **Parallel gated workstreams:** lending, investments, savings, insurance, Essentials, participatory finance, asset finance, group/club/SACCO/People and institutional features continue according to their **own independent regulatory, capital, custody and operational acceptance plans**. B2C focus does not supersede or freeze them.
- **P2:** certified third-party account sync, premium planning depth, expanded add-ons, long-range financial products with separate legal/product authorisation.

Every capability should be assigned an owner, explicit API/client source, accepted tests, platform availability and blockers in the established implementation register. Do not manufacture coverage percentages.

## 8. Outcome measures alongside functional completeness

**North Star measures:** sustained financial clarity, improved decisions and outcome progress, with durable trust and earned, voluntary reliance measured across real income/financial cycles.

**Full-scope quality measures:** % applicable B2C canonical capabilities mapped to source; % accepted journeys by App/Web/low-data; regression pass rate; device-performance/data budgets; confidentiality incidents; unauthorised/duplicate financial action rate; sync conflict/error resolution; source/provenance correctness; support case resolution; source-vs-provider settlement mismatches.

Engagement metrics alone cannot substitute for release acceptance or customer financial outcome measurement. Similarly, comprehensive catalogue coverage cannot be represented as proven readiness merely because documentation exists.

## 9. Accountable definition of done

B2C is complete only when **all in-scope activated consumer/member journeys** work with the appropriate full functionality, controls, evidence and resilience; every planned or partner-gated capability is accurately labelled; users understand online/offline distinctions; no other product or financial Space is compromised; and the application remains light enough for the intended audiences. No checkbox for 'offline-first' can bypass security and no P0 simplification may remove an existing valid customer right or function.
