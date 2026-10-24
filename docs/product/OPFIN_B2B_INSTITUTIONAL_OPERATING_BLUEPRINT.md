# OpFin B2B and B2B2C Institutional Operating Blueprint

**Status:** Proposed canonical product and commercial strategy; implementation/activation evidence remains separately controlled  
**Prepared:** 24 October 2026 • English (UK)  
**Scope:** Institution-owned operations, independently viable institutional subscriptions, customer/member financial relationships, authorised provider services, and white-label/API distribution.  
**North Star retained:** *Every financial life deserves a future worth looking forward to.*  
**Customer promise retained:** *Your next step, clearer.*

## Relationship strategy alignment (24 October 2026)

This B2B/B2B2C institutional specification is a **domain component** of the [Unified Five-Direction Strategy](OPFIN_UNIFIED_FIVE_DIRECTION_STRATEGY.md), not an alternative to B2C, C2B or C2B2B. Institution-owned software remains independently viable. Its lawful member-facing (B2B2C), customer-requested (C2B) and customer-confirmed institution-to-institution (C2B2B) relationships use the same identity, Financial Space, permission, consent and money-state controls. The [Unified Functional Acceptance Register](../development/OPFIN_UNIFIED_FIVE_DIRECTION_ACCEPTANCE.md) sets cross-mode tests and release boundaries.

## 1. Executive decision

Build **one governed institutional foundation with distinctly complete operating products for each institution type**, not one generic dashboard with 15 interchangeable cards. OpFin is useful to an institution **before** it originates a loan, integrates a provider or redirects a consumer into OpFin Finance. End-to-end operating journeys, clear money ownership, member access, institutional controls and sustainable economics define completion.

Two independent sources of value must work:

1. **Institution as an operating customer:** it uses OpFin to govern memberships, financial/non-financial records, accounting, approvals, reporting and administration on its own terms.
2. **Institution as a financial-service provider or distributor:** it offers its own authorised products to its customers and/or reaches consenting OpFin customers through a governed partner relationship.

Neither relationship automatically authorises the other. The individual remains free to use Personal Financial Life without belonging to an institution. The institution may use its standalone management tools without joining OpFin's financial-services marketplace.

**B2B** means a directly contracted institutional operating customer. **B2B2C** means an institution extends an authorised service or branded experience to its members/customers through OpFin, subject to actual contractual ownership and user consent. **Hybrid** means both customer-origin routes coexist: no double-counted people, no inferred cross-Space rights and no duplicate financial obligations.

This strategy extends, rather than replaces, the [canonical product blueprint](OPFIN_PRODUCT_BLUEPRINT.md), [Financial Spaces domain](../architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md), [service registry](../architecture/OPFIN_CANONICAL_SERVICE_REGISTRY.md), [commercial operations programme](../development/FINANCIAL_SPACES_COMMERCIAL_OPERATIONS_2026-09-29.md) and [B2C strategy candidate (separate PR #221)](https://github.com/lynelk/OpFin/pull/221). The last link exists in B2C draft PR #221, so do not make this B2B branch or release depend on that PR merging.

## 2. The institutional product hierarchy

### A. Common institutional operating foundation

Each independent institution/Financial Space receives only its permitted capabilities:

- identity of legal entity and stable Financial Space; internal IDs are distinct from regulator or other external registrations;
- organisation onboarding/KYB, requested-versus-verified authority, directors/signatories, ownership and effective-dated contracts;
- membership/customer directory, invitations, institution roles, delegates and lifecycle transitions, without granting access to members' Personal Spaces;
- common tools: controlled financial records, budgets, documents, evidence, approvals, tasks, audit and reporting with exact Space ownership;
- clear financial-intent → quote → authority/maker-checker → provider submission → finality → ledger posting → reconciliation states where money movement applies;
- product/service catalogue limited by institution type, contract, country, regulator, role, member entitlement and external activation;
- institution-specific chart-of-accounts, member capital and liabilities, source-matched statements, immutable issued reports, exception and correction paths;
- privacy, purpose-bound consent, access/export/retention, member complaints, security recovery and field-level provenance;
- optional integrations using verified APIs, signed requests, consent, controlled costs, reliable callbacks and no ambiguous-operation replay;
- metered low-data App, role-aware Web Workspace and approved assisted channels; offline drafts are never proof of money movement;
- billing/subscription and service-unit economics where a valid commercial agreement exists. Missing price and missing cost remain unknown, not zero;
- approved co-branding/white-label and API access, preserving legal-entity/provider disclosure, control of domain and identity boundaries.

### B. Institution-specific operating products

| Institution | End-to-end operating journey | Primary administration surface | Essential member/customer experience |
| --- | --- | --- | --- |
| Investment Club | Members → capital commitments/calls → contributions → proposal/approval → acquisition/disposal → accounting/reconciliation → valuation/NAV → distribution → statements | Treasury, capital, portfolio and governance Workspace | Contributions, attributable ownership, issued statements, eligible votes, authorised reports |
| SACCO / Cooperative | Member onboarding → shares/savings → product/guarantee → loan decision → collection/disbursement → ledger/reconciliation → provisioning/period close → regulator/member reporting | Member operations, financial products, risk, teller/branches, finance and governance | Shares/savings, borrowing obligations, verified transactions, statements and requests |
| Savings Group / VSLA | Formation → rules/cycle → pledges and receipt evidence → loan/welfare decision → repayment → close/share-out → renewed cycle | Lightweight mobile group officer workflow + optional Web | Pledges, verified contributions, obligations, meeting activity, statement/share-out progress |
| Bank/MFI/lender | Licence/KYB → catalogue/funding → customer acquisition → eligibility/affordability → offer/contract → provider execution → servicing → collections/recovery → risk and economics | Independent institution product, credit/risk, funding and servicing Workspace | Disclosed lender offer, instalments, payments, hardship, complaints |
| Employer / People | Organisation → employee join → attendance/leave → expenses/assets/KPI → approved payroll → benefits → employee lifecycle/exit | Independently viable People employer Workspace | Leave, attendance, payslips, authorised benefits and scoped employment relationship |
| Investment/fund manager | Mandate/investor → suitability → commitments → custody/funding → portfolio allocation → valuation/performance → reporting → lawful distributions/redemptions | Fund/capital and investor administration | Mandate-specific holdings/reporting and investor actions within actual legal rights |
| Insurer/broker | Partner approval → product/quote → underwriting → cover issuance → premium reconciliation → renewal/claim → dispute/report | Product distribution and policy/claims servicing | Quotes, coverage terms/status, premium receipts, claim progress |
| Payment/account service institution | Onboard/approve channel → receive intent → authorised transaction → settlement → reconciliation → exception/reversal → service reporting | Provider integrations, operations, settlement and SLA monitoring | Clear payment status, receipt, dispute/recovery; no unconfirmed transfer |
| NGO/development/programme | Contract → approved participant onboarding → consent → intervention/learning → versioned check-ins → outcomes → privacy-safe reporting/close-out | Programme partner and monitoring Workspace | Consent, learning, voluntary participation, assistance and own relevant status |
| Merchant/supplier/commercial partner | Approval → customer permission → validated referral/evidence → customer confirmation → independent financial-product decision → servicing/commercial reconciliation | Referral and evidence-scoped partner portal | Choice, consent, transparent institution offer, transaction/dispute status |
| Technology/fintech/white-label partner | Contract/KYB → API/brand entitlement → scoped integration → security/UAT → governed production → billing/SLA/reporting | API, application, tenant branding and service-management portal | Source-owned service journey with identified provider and understandable controls |
| Member association/welfare society | Membership → dues → welfare pool/rules → claim proposal → independent approval → settlement evidence → reports | Member dues and governance Workspace | Own dues, benefit eligibility, claims and permitted statements |

**Do not mistake this target scope for currently delivered features.** The [institutional capability and readiness register](OPFIN_INSTITUTIONAL_CAPABILITY_REGISTER.md) separates source foundations, extension proposals, evidence gaps and real-money activation gates.

## 3. Four service packages that do not constrain institution type

1. **Independent Management SaaS:** record-keeping, governance, membership, accounting/reporting, approved imports, support. No OpFin lender, investment custodian, financial-product routing or payment gateway is required.
2. **Connected Operations:** management SaaS plus specifically activated, consented and contracted communications, identity evidence, accounts, payment reconciliation, file exchange and/or exports.
3. **Embedded Financial Services:** an institution uses approved product-specific OpFin distribution, origination, servicing and institution-specific integrations; actual lender, insurer, deposit holder, custodian and payment executor disclosed.
4. **Brand/Platform Infrastructure:** institution-branded/co-branded/white-label presentation, domain requests and governed APIs, without transferring financial authority or unrestricted personal data.

Package choice, module entitlement, operational role, actual legal eligibility and externally confirmed service activation are **five distinct checks**. A commercial upgrade must not silently enable transactions. Essential member and group tasks must not require a computer or paid premium tools contrary to the existing App completeness promise.

## 4. Different institutional money, one precise core

Shared infrastructure is not shared accounting meaning:

| Financial event | Canonical owner and treatment |
| --- | --- |
| Investment Club contribution | Club-owned capital allocation/book entry with source/evidence and member entitlements; not a personal deposit held by OpFin |
| Investment Club NAV/unit change | Club-approved ownership/valuation policy, as-of date and controlled distribution history; valuation ≠ cash or realised return |
| SACCO share | Institution share capital/ownership according to actual legal product; not interchangeable with withdrawable savings |
| SACCO member savings | Actual SACCO/member liability only after approved receipt and ledger policy; unverified request ≠ deposit |
| Savings Group pledge | Member promise distinct from externally statement-matched contribution and payout |
| Savings Group loan | Group/institution contract and funding decision distinct from internal request approval |
| Lender loan | Actual institution lender of record, funded mandate, signed accepted terms, disbursement evidence and servicing |
| Employer salary deduction | Payroll authorisation ≠ payslip ≠ bank/mobile-money remittance or lender receipt |
| Insurer premium / policy | Premium collection and partner settlement separate from insurer policy issuance/active cover |
| Fund/investor position | External custodian, legal investment interest and mandate; OpFin records cannot invent beneficial ownership or liquidity |

Every shared money workflow must distinguish: **requested, quoted, internally approved, externally submitted, uncertain, provider-confirmed, financially posted, reconciled, reversed**. Do not use an all-purpose `completed` flag to conceal these boundaries. Same external receipt cannot be allocated twice. Preserve idempotent references, reversal evidence, dispute rights and maker/checker separation.

## 5. Full institutional onboarding and migration

**Discover → Register → Verify → Configure roles/products → Preview data import → Resolve exceptions → Approve opening position → Train → Certify appropriate services → Activate → Monitor → Renew/exit.**

- Accept practical CSV/XLSX/template data from non-technical groups as **staged input**, not automatic financial truth. Allow field mapping, multiple sheets, partial submissions, corrected re-uploads and provenance.
- Preserve immutable original upload and source identifier/hash, effective dates, currency exponent and record ownership.
- Detect duplicate members, conflicting identities, currencies, historical membership periods, unreconciled external statements, opening balance gaps and non-unique transaction references.
- Provide the importing institution with comprehensible errors and a downloadable correction report. Never change historically correct dates merely to satisfy a validator.
- Require a distinct authorised reviewer to approve opening financial positions and mapping before a live book or external finance action is created.
- Allow read-only and training use while financial migration is still incomplete. Mark unverified items as unverified; do not turn absence into zero.
- Support lossless institutional exit/export to lawful extent, with appropriate retained audit/ledger records and no proprietary hostage-taking.

The existing club import, Financial Intelligence, organisational onboarding and approved source accounting contracts must be reused rather than copied into a new parallel ETL or ledger.

## 6. Governance, access, privacy and regulatory profile

An institutional profile has:
- legal entity and operating country, entity status, verified references, registered officers and any regulatory classifications supported by evidence;
- contracts, liability allocation, processor/controller obligations, service-by-service lawful basis, data recipient/purpose/freshness/expiry/withdrawal;
- roles for member, treasurer, chairperson, loan officer, risk, teller, finance, supervisor, checker, auditor, platform and external partner with **server-side** action permission;
- product-level regulatory/age/country/store rules, funding/custody account, insurer or lender of record, permitted agency and money-rail scheme;
- technical activation record with runtime/version, DNS/TLS as appropriate, approved API contracts, sandbox and real-provider acceptance, incident/SLA/recovery status;
- service owner, integration costs, renewal dates, approval expiry, maker/checker and evidence links.

A bank, MFI, SACCO, club, insurer and payments provider cannot inherit one another's licences. Uganda institutional classifications and operative regulator requirements need current signed/legal evidence per service; product plans are not legal advice or regulatory confirmation. No automatic registration or live product authority from institution profile entry.

An institution sees its own records and only the appropriately shared participant data. Group membership and employer payroll access do not open Personal Financial Life. Institution-specific white-label hosting never changes underlying authorisation. Retain account/Space isolation for offline caches, reporting, financial history and customer support.

## 7. Lightweight access and inclusive service

Apply the existing [Lightweight/Sponsored Data Standard](../architecture/LIGHTWEIGHT_SPONSORED_DATA.md) to member tasks. Minimise ordinary app transfer, avoid mandatory desktop access for essential individual/group operations, and allow secure offline **drafts** of non-final tasks where the operation, storage and conflict policy explicitly permits it.

Institutional Web is appropriate for complex authorisation, treasury matching, migration and multi-dimensional reports. Mobile App should cover normal member actions, useful group officer journeys, statement access, messages and appropriate approvals without bypassing legal verification or server authority. USSD/WhatsApp are connected assisted channels, not offline computation.

An offline meeting record, savings pledge, payroll attendance event or member-loan request is not verified money, approved credit or regulator-confirmed reporting. Offline financial diaries and cached account data require encrypted, user/Space-scoped storage and expiry; never persist sensitive institutional details in generic device preferences.

## 8. Commercial design and unit economics

Pricing must be predictable, transparent and appropriate to operating model:

| Institution | Commercial hypothesis to validate | Main cost/risk dimensions |
| --- | --- | --- |
| Club | Flat institution subscription; optional advanced portfolio/accounting/reporting depth | Member migration, reconciliation exceptions, support |
| SACCO | Institution/subscription by operating scale, enabled regulated modules and branch needs | Migration, accounting assurance, device/branch support, audit and regulatory support |
| Savings Group | Affordable group fee or contracted programme sponsorship; essential functions remain accessible | SMS, assisted onboarding, language/field support |
| Lender | Licence/servicing/API charges and lawful origination/commercial allocations | Provider/API, reconciliation, fraud, servicing, collection and disputes |
| Employer/People | Employer and active-user/feature pricing with predictable published review | Payroll/statutory changes, HR support, integrations |
| Fund/insurer | Mandate/policy/platform administration and approved servicing/distribution economics | Reporting, regulated evidence, custody or underwriting integration |
| Programme | Contracted onboarding, intervention and outcome-reporting services | Field operations, verification, data quality and evaluation support |
| Integration/payment/technology partner | Contracted API/transaction tiers, SLA and service fees where authorised | Third-party messaging/payment costs, operational reliability |

These are *pricing hypotheses*, not approved fees, market rates or realised revenue. Cost and contribution margin must include hosting, communications, provider charges, onboarding/migration, support, compliance, collections, warranty where relevant, reconciliation, tax handling and commission-sharing. Principal, savings, member capital, insurance premium and investment assets are **not** OpFin platform revenue. Unknown margin inputs remain unknown.

Institution contracts must define what happens on non-payment, entitlement changes, data export, records retained by law, service termination and ongoing member rights. No silently repriced historical transaction.

## 9. Institutional growth motion

- **Club:** demonstrate completed books, accurate member capital and end-of-period report from a real-like imported workbook; sell transparency and reduced treasurer workload.
- **SACCO:** demonstrate reliable member statement, guarantor exposure, approved lending workflow and reconciliation, subject to legal activation; sell member service and operational control.
- **Savings Group:** demonstrate phone-based contribution cycle and member passbook in intermittent connectivity; sell continuity, reduced disputes and understandable records.
- **Lender:** demonstrate traced lender-of-record quotation through servicing, portfolio risk and reconciliation; sell operational throughput and accountable growth.
- **Employer:** demonstrate whole People workforce journey and explicitly optional employee financial services; sell HR accuracy and time saved.
- **Fund/insurer/programme:** demonstrate their own compliant lifecycle and reporting, not an unrelated lending marketplace.

Do not infer that a demonstration is operationally live. Collect evidence of institutional demand, time saved, member adoption, support burden and actual contribution margin before fixing multi-year pricing or expansion forecasts.

## 10. Measures, delivery and accountability

Measure **institutional completeness** separately from **commercial performance** and **customer outcomes**:
- % essential end-to-end journeys accepted by institution/role/channel/device and source-of-truth authority;
- % financial activity provider-reconciled, exception age, duplicate/missing postings and period-close accuracy;
- migrated member/account balance acceptance, correction loops and time to useful operation;
- active institutions, active authorised members, successful tasks per financial cycle, institution renewals and complaints;
- subscription/contracted revenue, cost-to-serve, contribution margin, verified external settlement of charges;
- institution-specific outcomes: club accounting close, SACCO PAR/liquidity, VSLA cycle/share-out, lender PAR/collections, People payroll accuracy, insurer policy/claims, programme data quality and correctly attributed outcomes;
- security: cross-Space denials, privacy/consent incidents, revoked member access, provider uncertain state and disaster-recovery evidence.

**No arbitrary fixed waiting period or portfolio-vintage prerequisite blocks already authorised lending or investor participation.** Growth and each additional provider/product run in parallel under actual capacity, legal rules, risk limits, real settlement and user safeguards.

### Delivery waves (not universal release freezes)

- **Foundation acceptance:** typed Institutional Readiness Profile, discoverable module entitlements, complete membership/KYB, document/migration review, legal/commercial agreement, isolated accounting and real activation gates.
- **Priority institutional completeness:** club capital/treasury/member journey, SACCO member/share/savings/loan/guarantor/ledger, Savings Group mobile cycle/funding/share-out, lender origination/servicing with existing finance guardrails. Workstreams run independently rather than waiting for one institution to complete before another starts.
- **Next operating depth:** People ten-module acceptance; programme impact; fund/insurer serviced product journeys; white-label/APIs and billing.
- **Controlled extension:** specialised association/merchant/technology integrations, only with verified source contracts and independently useful business cases.

No extra database service or duplicate finance engine is justified by this blueprint. Confirm existing APIs, tested branch, release controls and operational cost first.

## 11. Source-to-delivery distinctions

- **Investment Club:** `apps/api/routes/club_accounting.php`, [club accounting API](../../apps/api/docs/api/CLUB_ACCOUNTING.md), [verification candidate](../operations/CLUB_ACCOUNTING_VERIFICATION_2026-09-25.md) and [treasury specification](INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md). Distinguish source-backed capital/valuations/distributions and September candidate tests from current exact-head Web/Flutter, external custody and financial acceptance.
- **SACCO:** `apps/api/routes/sacco_core.php`, [completion programme](../development/SACCO_AND_WHITE_LABEL_COMPLETION_2026-09-29.md), regulatory/product/settlement source gates. Neither core routes nor saved legal attestations prove live custody or authorised deposits.
- **Savings Groups:** `apps/api/routes/savings_group_cycles.php` and group Workspace. Draft activation, pledges, contribution matching, loan/welfare requests, settlement proposals and actual money must be traced separately; certain newer settlement routes exist but no blanket verified payout claim.
- **Lenders:** [lender orchestration](../architecture/LENDER_ORCHESTRATION.md), finance contracts and named institution authority. Independent product/market/channel/financial and consumer checks remain.
- **People:** [People Core Release](../architecture/OPFIN_PEOPLE_CORE_RELEASE_SCOPE.md) describes ten required modules and protected candidate source. Full payroll/statutory/device acceptance is not complete.
- **Programmes:** [inclusive finance](INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md) and source-constrained monitoring/reporting; no inferred programme-caused improvement or underwriting use.
- **Existing state:** [CURRENT_STATE](../CURRENT_STATE.md) and [21 October alignment register](../operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md) overrule older snapshot status claims where they conflict.

**Decision rule:** A listed route or screen is an implementation clue, not a customer-complete service; successful synthetic tests are not regulator approval, real provider settlement, production sign-off or signed counterparty data.
