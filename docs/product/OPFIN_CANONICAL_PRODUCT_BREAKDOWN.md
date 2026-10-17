# OpFin — Canonical Product Breakdown

**Classification:** Canonical product architecture and capability register, **proposed for review** (not yet merged to `main`).
**Prepared:** 17 October 2026
**Language:** English (United Kingdom)
**Source of truth:** `lynelk/OpFin`; `docs/product/OPFIN_PRODUCT_BLUEPRINT.md`, `docs/product/OPFIN_CONCEPT_NOTE.md`, `docs/architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md`, `docs/CURRENT_STATE.md`, `docs/product/CANONICAL_IMPLEMENTATION_STATUS.md`, and the P0–P1 architecture proposal in this draft branch. Code examples were checked against Next.js portal and Flutter Home source.

This register expresses the **whole product**, including implemented foundations and target extensions. It must not be interpreted as a claim that every listed capability is already deployed, available to customers, licensed, provider-activated, tested or launch-approved. An application screen or a catalogue entry is not proof of a live financial product.

## 1. North Star and product identity

> **Every financial life deserves a future worth looking forward to.**
>
> OpFin exists to help everyone understand their finances, make confident decisions and pursue what matters to them, at every age, every stage and every financial scale.
>
> **We don't define success for people. We help them make progress towards their own.**

**Customer promise:** *OpFin. Your next step, clearer.*

**Canonical definition:** OpFin is a universal personal financial operating platform with embedded financial services. It preserves a continuing relationship to the person and connects everyday money management, financial wellbeing, aspirations, regulated products and governance across authorised Financial Spaces.

**What it is not:** a lending-only app; a bank/custodian by assumption; an undifferentiated marketplace; a universal licence for financial services; a financial score ranking people's worth; or a separate app for each wealth, age, gender or geography category.

**Product values expressed in system behaviour:**
- **Continuity:** durable person identity, consented history and financial context across life stages and channels.
- **Dignity:** no shame, exploitation or automatic exclusion from safe learning and planning because a specific product is unavailable.
- **Aspiration:** users choose goals, change them, and see attributed progress; the system does not define success or infer wealth class from demographics.
- **Clarity:** compact, accessible, evidence-labelled money picture and one relevant next action.
- **Control:** privacy, permissions, understandable terms, authorised confirmation and human routes to challenge decisions.
- **Progressive capability:** simple by default, powerful on demand; same system, different depth of use.

The customer relationship follows **Observe → Understand → Plan → Act → Monitor → Adjust**.

## 2. The canonical product architecture

```text
OPFIN
├── A. Universal Financial Life
│   ├── Identity, Consent & Continuity
│   ├── Financial Spaces & Membership
│   ├── Everyday Money & Financial Compass
│   ├── Aspirations, Goals & Progress
│   ├── Guidance, Education & Recovery
│   └── Financial Passport & Consented Evidence
├── B. Financial Services (eligible, authorised, partner-governed)
│   ├── Finance / Borrow / Credit Servicing
│   ├── Essentials (purpose-bound bill/rent finance)
│   ├── Save
│   ├── Grow / Invest
│   ├── Protect
│   └── Capital & Assets
├── C. Collective & Institutional Finance
│   ├── Household
│   ├── Savings Group / Community / VSLA
│   ├── Investment Club & Treasury
│   ├── Business & OpFin Work
│   ├── SACCO / Cooperative
│   ├── Investment/Fund Organisation
│   └── Regulated Partner & Programme
├── D. Intelligence, Operations & Platform
│   ├── Governance, Eligibility, Permissions & Entitlements
│   ├── Ledger, Provider State, Reconciliation & Audit
│   ├── Partner Catalogue, Product Factory & Cito Orchestration
│   ├── Customer Support, Compliance, Programme Impact
│   ├── Commercial Service Economics & Reporting
│   └── Developer/API Contract & Observability
└── E. Channels
    ├── Flutter Android/iOS App
    ├── Next.js Customer Web
    ├── Institutional Workspace & Partner Portals
    ├── Public Information and Privacy-Preserving Learning
    └── Approved USSD, Messaging and Assisted Access
```

**Do not** build separate permanent 'poor', 'premium wealth', 'women's', 'youth' or 'rural' product cores. User agency, literacy, accessibility, activity, eligibility and required feature depth drive presentation; legal service access is determined independently.

## 3. Canonical financial life: foundational capabilities

| ID | Product capability | Minimum customer value and functional scope | System rules and ownership | Source/target maturity |
| --- | --- | --- | --- | --- |
| FL-01 | Person, identity and account | Phone-first onboarding, OTP, names, six-digit PIN, profile, progressive verification, recovery and security | One person/identity; protected evidence, consent and lawful access; no secret collection by assistants | Source foundation; youth transitions proposed |
| FL-02 | Consent and privacy | Purpose-bound collection, viewing, revocation when lawful, deletion and data-use transparency | Consent, lawful basis, capability and permission are separate; retained legal records not silently discarded | Source foundation; further portability/grants planned |
| FL-03 | Financial Spaces | Personal by default; deliberate switching to authorised household/group/organisation context; memberships, roles, invitations and Space credentials | Space ownership and access isolation; no organisation gains a person's Personal Space | Source foundation; end-to-end channel acceptance required |
| FL-04 | Money accounts and cash flow | Recorded cash/bank/mobile-money accounts; income, spending, categories and activity | Currency and source evidence; user-reported is not provider-verified | Source foundation |
| FL-05 | Budgeting, calendar and obligations | Budgets, scheduled payments, income events, debt and receivables, cash commitments and adjustment | Confirmed, scheduled, predicted and unavailable are distinct; no invented money | Source foundation |
| FL-06 | Financial Compass | Available money, upcoming commitments, savings, debt, safe-to-spend where supported, source confidence, clear next action | API-calculated, evidence-labelled; derived 'safe' figure is explanatory, not a guarantee | Source foundation; new adaptive presentation targeted |
| FL-07 | Assets, liabilities and position | Personal assets, liabilities, net-worth and financial position views | Ownership, valuation timestamp, currency and verification explained | Source foundation with expansion planned |
| FL-08 | User-owned aspirations | Voluntary ambitions/goals with revisions, realistic planning, chosen priority, milestones and pause/archive | Plans, simulated progress and verified balances cannot be combined as realised assets; not a credit-risk input by default | Existing goal foundations; aspiration domain proposed |
| FL-09 | Financial guidance and education | Explainable guidance, short lessons, budget coaching, helpful reminders and contextual next steps | Advice not ranked by commissions or click-through; no default additional borrowing in hardship | Basic rules in source; age-safe guest education and richer coaching targeted |
| FL-10 | Financial recovery and resilience | Financial shock support, debt planning, hardship referrals, self-directed steps and resilience building | Respectful factually accurate language, support remedies, consent, no manipulation | Journey foundation; integrated dignity/recovery contract targeted |
| FL-11 | Financial Passport | Provenance-labelled financial snapshot and permissioned reuse/export | Recipient/purpose/expiry/freshness and consent; never unrestricted underwriting; no leakage from children's learning or group Spaces | Snapshot source; governed portability expansion targeted |
| FL-12 | Life-stage continuity | Personal relationship remains through adolescence where legal, adulthood, jobs, household/Space changes and retirement | Age/guardian re-authorisation; private youth history not automatically released; no automatic product permission | Target architecture; do not claim implemented |

### The three proposed customer experience modes

**OpFin Today** answers *Where am I, what matters now, and what is my next useful step?* with compact, evidence-aware Home.

**OpFin Progress** shows user-relevant goals, improvements, milestones and resilience, without shame, public financial ranking or artificial engagement streaks.

**OpFin Ahead** supports projections, planning and aspirations with explicit assumptions and permitted deeper analysis.

These are **experience patterns**, not necessarily new product tabs, API endpoints or shipped modules. Build within existing Home, Compass and goal routes first. Simple, Detailed and Advanced are optional information-depth preferences, never financial class labels.

## Dedicated credit priority and merchant-finance programme

**The universal financial life platform stays universal.** Inside OpFin, Finance is the near-term controlled, evidence-led commercial vertical, prioritising permissioned Stolets merchant finance, four independent underwriting outputs, affordability, transparent pricing, PAR/roll-rate/vintage dashboards, governed lender exposure, reconciliation, Cito orchestration and ultimately permissioned portfolio capital. These workstreams now run alongside active customer and investor growth; they are not a reason to delay authorised services or redefine the customer experience as compulsory credit solicitation.

Preserved in full: [OpFin Finance Evidence-Led Credit Programme](OPFIN_FINANCE_EVIDENCE_LED_CREDIT_PROGRAMME.md). The credit programme calls for immediate growth of lending volume and eligible investor participation across currently authorised products and funding routes. Later months organise optimisation and evidence gathering, not blanket participation delays. The eight Universal Financial Life workstreams and age-appropriate service rules remain intact.

## 4. Financial services: distinct regulated product domains

| ID | Product family | Scope and example journeys | Provider/governance rule | Source/launch interpretation |
| --- | --- | --- | --- | --- |
| FS-01 | **Finance / Borrow** | Credit discovery; need/amount/purpose; KYC/consent; affordability; assessment; disclosed lender offer; acceptance; disbursement tracking; schedules; repayments; hardship and complaints | Named lender of record, approved product and funding, consumer protection, risk and country/store policy; independent authorisation | Source implementation exists; activation/financial acceptance vary |
| FS-02 | **Essentials** | Verified electricity, water, internet, household energy, television and rent beneficiary/account; purpose-bound lender quote and verified settlement | No unrestricted cash-out; named lender, eligibility, capital reservation, accounting, reversal/reconciliation and provider certification | Source exists; documented outstanding financial-control/activation gates |
| FS-03 | **Save** | Goals, contributions, schedules and eligible provider-held saving products; recorded/pending/confirmed funds; withdrawal and automation where activated | Custody remains with actual savings provider, confirmed balances only, adult/child/guardian rules and partner permissions | Source foundations; individual services provider-gated |
| FS-04 | **Grow / Invest** | Investment product discovery, suitability, risk/liquidity/fee disclosures, provider orders, positions, reporting and redemption | Investment provider/custodian and jurisdictional permissions; no promised yield | Source foundations; settlement/custody/redemption separately gated |
| FS-05 | **Protect** | Insurance/protection discovery; underwriting requirements, disclosures, enrolment, premium, policy visibility, claims help and disputes | Insurer retains underwriting and claims decisions; premium payment is not proof of active cover | Source foundations; group and provider journeys independently gated |
| FS-06 | **Participatory/Marketplace Finance** | Reviewed borrower requests, investor/organisation participation, private loan books and permitted capital allocation | Lender-of-record, custody, suitability, credit concentration, loss allocation, servicing and liquidity disclosures | Long-range/source foundations; not a claim of fully live marketplace |
| FS-07 | **OpFin Capital / Assets** | Asset Passport, OpFin Auto, Device Finance, productive asset finance, approved partners, asset finance and related servicing | Asset identity, lawful security interests, contracts, anti-fraud, protection and verified settlement; no covert device controls | Canonical expansion family, activation by asset type |
| FS-08 | **OpFin Digital / Exchange** | Optional eligible digital investment interests, verifiable evidence or controlled transfers | Distinct legal rights, AML/custody, jurisdiction, transferability and market permission; default fail-closed | Long-range target; not launched by blueprint |

### Important product boundaries

- **OpFin Finance** is an appropriate customer-facing direction; some existing customer applications still use the **Borrow** route/label. Preserve compatibility while a coordinated App/Web change is accepted.
- Employer-assisted salary credit is a loan product/use case, not a second OpFin identity or an employer-owned view of an employee's entire financial life.
- Savings goal planning must remain useful even when no regulated savings product is available. Youth account access and guardian-managed deposits are subject to specific legal and provider rules.
- A user may seek financial education and planning without being qualified or compelled to buy a product.
- Provider catalogue visibility is not actual activation. Financial execution and finality require full authority, acceptance and reconciliation.

## 5. Financial Spaces, communities and institutions

**One person, many governed contexts.** Every Space has a stable OpFin reference, country/currency, status, capabilities, memberships, roles and separate financial records. The employer capability belongs to a Business Space. Investor may be a personal or organisational role; it does not require creating another app identity.

| Space / operating context | Primary activities | Typical authorised roles | Critical separation |
| --- | --- | --- | --- |
| **Personal** | Money, goals, financial health, saving, borrowing, investing, protection, assets and documents | Account holder; properly scoped delegate where approved | No involuntary employer/group disclosure |
| **Household** | Shared commitments, family budgets, planning, delegated payments and permitted guardianship | Owners, authorised family members, approved guardians | Not a pooled personal account by default |
| **Savings Group / VSLA** | Onboarding, member contributions, member loans, expenses, officers, approvals, meetings/voting where activated, statements | Chairperson, treasurer, member, authorised officer | Group transactions and individual funds distinct |
| **Investment Club** | Membership, treasury accounts, cashbook, statement import/reconciliation, immutable financial reports; later capital/NAV/distributions | Chairperson, finance officer, treasurer, member | Issued OpFin statements are not underlying bank/custodian statements |
| **Business / OpFin Work** | Employer programmes, benefits, permitted employee verification, payroll-linked obligations, finance administration | Employer admin, HR, designated finance officer | Employer cannot browse personal financial records by default |
| **SACCO / Cooperative** | Member spaces, products, governed credit/savings, administration, reporting, approvals | SACCO admin, operations, authorised member | Institution authority and member permission separate |
| **Investment/Fund organisation** | Approved mandate, portfolios, capital/funding records, governance, investments | Fund/finance administrator, approved manager | Real custody, mandate and investor rights remain explicit |
| **Financial/Programme partner** | Catalogue products, programmes, governance, approved reporting and service-network data | Institution partner, programme officer | Only contract-specific data and aggregate, privacy-safe reporting |

Investment Club treasury source includes cashbook, CSV statement import, suggested matches, exceptions, immutable account and consolidated statements. **It does not yet equal full member-capital/NAV/unitisation/distribution accounting.**

## 6. Age inclusion, safeguarding and financial dignity

OpFin is designed for all ages as a financial wellbeing relationship, subject to lawful access. Restrictions must be enforced **per regulated service**, not interpreted as a judgement about the value of a person.

| Access layer | Capability promise | Conditions |
| --- | --- | --- |
| Public learning | Age-appropriate financial information without a loan application, KYC or unnecessary tracking where lawful | Privacy-preserving publishing and safeguarding |
| Protected planning and records | Budgeting, non-transactional goals and appropriate family/youth workflows | Account/data-processing authority and verified guardian consent where required |
| Regulated financial products | Only eligible savings, payments, credit, investment, protection or partner actions | Country, age/capacity, guardian, KYC, risk/suitability and provider activation |
| Human help and constructive alternatives | Explain limits, provide education, support, re-evaluation or hardship routes | No facilitation of prohibited transactions or evasion of controls |

**Canonical coach response to ineligibility:** tell the truth without judgement; explain the applicable constraint; offer one permitted and useful next step; allow return to basic financial guidance; escalate to human support when appropriate.

This is a **target experience**; source and legal approval for personally identified child accounts, guardian authority and child-specific regulated products must be established independently. A publicly accessible learning experience is different from creating or retaining a child's personal financial data.

## 7. Intelligence and customer engagement

| System | Canonical function | Key restraint |
| --- | --- | --- |
| Financial Compass | Recorded financial position, cash flow, estimates, commitments and confidence labels | No invented external balances or overconfident 'safe' figures |
| Next Step engine | One reasoned, achievable action, with alternative pathways | No automatic financial product steering driven by commission |
| Goals and Progress | Chosen plans, targets, attributed achievements and future aspirations | Goals must not assume wealth, gender or age ambitions |
| Financial Resilience / Shock | Support after setbacks; recovery options and assistance | Accurate negative facts retained; no humiliation or default new loan |
| Financial Passport | Attributable and consented financial evidence | Not a universal customer surveillance or scoring file |
| Credit intelligence | Approved provider/credit evidence, scoring/explanations and affordability | Regulated policy, protected-data limits and audit |
| Inclusive programmes and impact | Learning and programme delivery, consented follow-up, privacy-safe measurement | Programme outcomes are not underwriting or proven causality |
| OpFin Autopilot | Approved scheduled goals/actions and relevant reminders | Rule creation is not money movement; avoid addictive nudges and hidden mandates |
| Notification and re-engagement | Timely commitments, meaningful progress and useful guidance | No fear-driven engagement or attention-for-attention's-sake |

Customer experience standard: **compact, reassuring, aspirational, truthful, accessible, usable daily when helpful and not artificially habit-forming**. Keep operational and legal disclosures visible at the proper decision point.

## 8. Delivery surfaces and navigation

| Channel | Canonical responsibility | Present caveat |
| --- | --- | --- |
| **Flutter Android/iOS App** | Primary person/member experience; intended complete ordinary Individual and Savings Group journeys; Space switching | Actual mobile completeness and real-device acceptance remain work |
| **Next.js Customer Web** | Existing authorised accounts, financial management, broader analysis and deep-link compatibility | Current primary customer navigation is **Home / Borrow / Save / Grow / More** |
| **Institutional Workspace** | Employer, Business, SACCO, lender, treasury and operations tasks with larger information density | Screen access must stay role- and Space-restricted |
| **Partner portal** | Programme and service network reporting and permitted product/provider operations | No personal member data or unapproved partner scope |
| **Public site and learning** | Product education, trust, privacy and approved guest financial literacy | New customer registration remains phone-first in the App by current contract |
| **USSD, messaging and assisted channels** | Selected understandable, low-data support and customer tasks against server-authoritative state | Never ask for PINs/OTPs through support; sensitive commitments require authenticated confirmation |

**Source/design discrepancy to resolve:** Flutter currently shows **Home / Borrow / Activity / More**, Web shows **Home / Borrow / Save / Grow / More**, while `AGENTS.md` describes a target **Home / Finance / Activity / More**. This breakdown **does not declare those reconciled**. Navigation unification requires a deliberate, tested decision that preserves each financial journey.

**OpFin Today, Progress and Ahead are unified experience patterns**, not mandatory extra tabs that would recreate long navigation and scrolling.

## 9. Platform capabilities and back-office operations

| Back-office/domain component | Required responsibility |
| --- | --- |
| **Product Factory / Partner Catalogue** | Approved partner identity, contracts, territory, channel, product configuration, availability and product-specific eligibility |
| **Capability decisions** | Independent role permission, Space membership, plan entitlement, product eligibility, guardian authority, lawful purpose and actual provider activation |
| **Credit/lender operations** | Lender-of-record, pricing, product mandates, funding pools, decisions, approvals, origination, servicing, collections and hardship |
| **Ledger and money controls** | Immutable appropriate accounting, durable instructions, idempotency, provider finality, funding and balance reservations, refunds, reversal and reconciliation |
| **Support / complaints** | Customer cases, contextual evidence, SLA, financial-recovery escalation and remedy |
| **Risk, compliance and audit** | Credit quality, privacy, safeguarding, AML/KYC, regulatory records, contract evidence, appropriate actor attribution and retention |
| **Programme delivery and outcomes** | Consented enrolment, interventions, survey/questionnaire versions, follow-ups, small-cohort suppression and outcomes reporting |
| **Commercial and service economics** | Contracted fees, provider costs, tax, subscriptions, commission splits, channel costs, margin and reconciled revenue; unknown is not zero |
| **Developer and API access** | Documentation, scoped contract discovery, reviewed OpenAPI coverage, API clients and audit; no unauthorised AI financial execution |
| **Integration orchestration** | Cito as the intended gateway for external identity, communications, payments and service adapters, subject to actual certified capabilities; source/runtime routing needs separately verified alignment |

**Source authority:** the Laravel API owns decisions, accounts, transactions, user/Space permissions, financial state and evidence. Flutter and Next.js are clients; they do not independently approve a financial service, resolve age or invent payment confirmation.

## 10. Monetisation and business economics

| Revenue category | Economic relationship | Restriction |
| --- | --- | --- |
| Essential personal/group use | Accessible foundation with optional paid depth | Do not charge for indispensable basic financial wellbeing under the stated product direction |
| Premium individual capabilities | Automation, advanced planning, personal convenience and analysis | Avoid tying dignity or basic financial advice to payment |
| Organisational SaaS | Employer, SACCO, group management, programme operations and institutional subscriptions | Roles and commercial plans do not equal regulatory authority |
| Partner-funded credit | Approved origination/servicing/platform/revenue-share fees | Principal is not platform revenue; real lender identified |
| Savings, investment and protection | Lawful partner administration, referral, distribution or AUM-based arrangements | Custody and regulated partner accountability stay explicit |
| Payments and orchestration | Authorised API/platform, processing and permitted transaction fees | Regulated partner scope and reliable reconciliation |
| Marketplace/asset/participatory services | Governed administration and service fees | Not activated merely by code or a prototype |
| Aggregated programme analytics | Lawful privacy-safe service/contracted reporting | No sale of personal financial data; no hidden reuse of protected attributes |

Customer-benefit ranking is independent of commercial incentives. Do not present expected margin as realised, reconciled platform revenue.

## 11. Technical decomposition

```text
lynelk/OpFin
  apps/api                 Laravel domain and authenticated API
  apps/web                 Next.js Customer Web, institutional & operational interfaces
  apps/client              Flutter Android/iOS application
  packages/contracts       Shared reviewed API/schema conventions
  brand                    Approved OpFin visual identity and tokens
  infrastructure/railway   Controlled service and deployment boundaries
  docs                     Product, architecture, API, operations and release evidence
  tools/opfin-mcp           Documentation-only AI/developer discovery bridge
  sites/opfin-public       Public information site
```

Target non-functional requirements: country-specific rules, consent, child protection, cross-Space privacy, strict ledger/provider state, reliable retry/reconciliation, access and audit, low-data/low-cost operation, device support, accessibility, safe fallbacks, performance and recovery. Infrastructure and environment success is not product acceptance.

## 12. Canonical delivery status legend

- **F — Source foundation:** implementation or registered route exists; its exact supported scope is determined by code/tests.
- **G — Gated:** service needs additional provider, financial-control, security, legal, regulatory or cross-channel release evidence.
- **T — Target:** approved design direction or architecture, not a claimed implemented feature.
- **H — Historical:** dated prior test/deployment evidence only; not proof of current release certification.

| Domain | Current categorisation | What remains before broad claims |
| --- | --- | --- |
| Identity, Financial Spaces, money tracking, Compass, goal records | F | Cross-channel quality, privacy/Space regression and device evidence |
| Financial guidance, financial recovery, Financial Passport snapshot | F + T | Rich dignity/goal preferences, audited guidance reason contracts, portable consent |
| OpFin Today / Progress / Ahead, one-screen adaptive depth | T; candidate UI work in separate draft PR | Real screens, accessibility and customer testing, API-compatible implementation |
| Age-inclusive protected accounts, guardian transition and safe guest access | T | Country/privacy/legal/safeguarding architecture and implementation/acceptance |
| Credit, lending platform and core repayment workflows | F + G | Lender/funding/product authority and financial/regulatory release controls |
| Save, Invest, Protect | F + G | Individual provider custody, settlement, cover, claims and redemption acceptance |
| Essentials | F + G | Financial exposure, accounting, finality, concurrency, reversals, permissions and provider activation |
| Participatory finance / broader asset and capital families | F (selected foundations) + G/T | Separate product, custody, authorisation, execution and investor protections |
| Investment Club treasury/statements | F + G | Historical consistency, full accounting/NAV/distribution expansion |
| Inclusive programmes, operational and impact reporting | F + G | Real programme consent, partner activation, evaluation and proven outcomes |
| Commercial economics/analytics | F + G | Actual cost, reconciled settlement, margin and management evidence |
| Customer App and Web/Workspace | F + G | Full journey parity, supported devices, accessibility and production acceptance |

### Evidence boundaries

The `main` documentation `docs/CURRENT_STATE.md` reports historical successful API, Web, worker and scheduler deployment status for a dated September commit, but does not prove current production health or full customer journey acceptance. The `CANONICAL_IMPLEMENTATION_STATUS.md` register has an older September baseline and known failures. Read them by their **review dates**, rather than treating every old failure or success as the latest fact.

The North Star and consolidated work are on [draft PR #188](https://github.com/lynelk/OpFin/pull/188). The compact UI work is separately on [draft PR #187](https://github.com/lynelk/OpFin/pull/187). Neither draft by itself constitutes merged main, deployed source, regulator/provider activation or customer acceptance.

## 13. One unified P0–P1 delivery programme

| Workstream | First necessary foundation | Customer capability to deliver |
| --- | --- | --- |
| FL-01 OpFin Today | Screen audit, source evidence, compact navigation and role-safe UI | Personalised one-screen Home and Simple/Detailed/Advanced views |
| FL-02 Universal Access | Guest-vs-account distinction, age and provider rules, guardian/data-protection analysis | Safe learning/planning, constructive alternatives, permitted youth journeys |
| FL-03 Financial Continuity | Stable identity, event ownership, Space rights and transitions | Consistent history across stages and channels with reviewed permission changes |
| FL-04 Personal Aspiration | User-owned goal/priority/evidence model | Adjustable goals, meaningful progress and planning scenarios |
| FL-05 Dignity and Recovery | Reason codes, truthful alternatives, human support and complaints | Safe hardship and denial handling with one achievable next action |
| FL-06 Adaptive Compass | Server-authoritative finance and confirmed-vs-estimated status | Same truth at different sophistication levels |
| FL-07 Passport and Consent | Data provenance, purpose/recipient scope, revocation | Portable, permissioned history with visibility and control |
| FL-08 Inclusive Delivery | Low-data, device, language, privacy, accessibility and release gates | Reliable Flutter/Web/approved assisted experiences in real conditions |

**Release slices:** A) trusted foundation and compact clarity; B) aspiration and safe access; C) portable continuity and deeper financial scale. These are parts of one programme, not separate competing roadmaps.

## 14. Product ownership and canonical acceptance

A capability may be described as **customer-ready** only after the API authority and legal/provider status, observed product journey, accessible UI, tested financial controls, incident/recovery support and documentary evidence all agree. A successful code merge is insufficient.

Every implementation task must declare:
- owning domain and relevant Financial Space;
- target customer need and one useful next step;
- required data, provenance, permission, eligibility and consent;
- what is implemented, newly proposed, unavailable or provider-gated;
- intended App/Web/other channel behaviour;
- explicit security, safeguarding, financial and quality acceptance;
- effect on financial continuity, dignity and customer-defined aspirations.

**Canonical reference set:** [Product Blueprint](OPFIN_PRODUCT_BLUEPRINT.md); [Concept Note](OPFIN_CONCEPT_NOTE.md); [Financial Spaces Domain Model](../architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md); [Financial Life Architecture](../architecture/FINANCIAL_LIFE_CONTINUITY_AND_DIGNITY.md); [P0–P1 Universal Financial Life Programme](OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md); [Current State](../CURRENT_STATE.md). These documents retain their separate purposes; the present breakdown is a navigation and decision register, not permission to replace their detailed legal/accounting contracts.
