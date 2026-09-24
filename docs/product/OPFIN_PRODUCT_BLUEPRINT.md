# OpFin Product Blueprint

Status: Canonical product contract  
Updated: 24 September 2026  
Language: English (United Kingdom)

## Product position

OpFin is a personal financial operating platform with embedded financial services. It starts with the individual, particularly people who are underserved by conventional financial systems, and helps that person understand, manage, plan, protect and improve their financial life. Groups and organisations extend that personal financial life through governed Financial Spaces; they do not replace the individual as OpFin's centre of gravity. Lending is one capability, not the product boundary.

## Constitutional product principles

1. **One person, one identity.** A human registers once and may participate in many Financial Spaces.
2. **Mobile completeness.** Individuals and Savings Groups must be able to complete normal end-to-end financial journeys in the OpFin App alone. Web enhances; it does not rescue an incomplete mobile product.
3. **Simple by default, powerful on demand.** Primary journeys use plain language, progressive disclosure, large touch targets, minimal typing and obvious next actions.
4. **Inclusion by design.** Low literacy, low digital literacy, rural/peri-urban use, inexpensive devices and unreliable connectivity are normal design conditions.
5. **Progressive verification.** Ask only for information required by the current activity, responsibility, risk or regulation.
6. **Financial Space separation.** Data and authority do not leak between Personal, Household, Group or Organisation contexts.
7. **Channel continuity.** App, Web, Workspace, Partner, USSD, WhatsApp/SMS and assisted channels use the same server-authoritative identity and domain state.
8. **Advice is independent of commercial incentives.** Need, eligibility and suitability precede product ranking and revenue calculation.
9. **Capability-based monetisation.** Essential financial management remains accessible; subscriptions buy additional depth, automation, scale or convenience.
10. **Independent operation with preferred gateways.** OpFin owns customer/product state, financial intent, experience, intelligence, ledger, servicing and reconciliation. Cito is the preferred third-party integration gateway and CPay is the preferred payment route, but neither is a mandatory runtime dependency. Certified direct-provider adapters remain controlled fallbacks.
11. **Evidence over appearance.** Implemented source, deployed source, activated provider service and release certification are separate states and must be described separately.

## Canonical layers

### Identity
Person, identity evidence, verification state, authentication, consent and delegation.

### Financial Spaces
- Personal
- Household
- Savings Group / community group
- Investment Club
- Business
- SACCO / cooperative
- Investment/Fund organisation
- Regulated/financial partner

Employer is a capability of a Business, not a duplicate legal entity. Investor is normally a role/capability of a person or organisation. Fund Manager is an institutional/partner role.

### Membership and roles
A person can belong to multiple Spaces simultaneously. Membership carries role, status, dates and scoped permissions. Examples include owner, member, chairperson, treasurer, employee, director, finance administrator, SACCO member and partner administrator.

### Financial functions
Money/cash flow; budgeting; goals; savings; debt; receivables/payables; assets/liabilities; net worth; investments; portfolios; protection/insurance; payments; borrowing; planning; forecasting; reporting and financial health.

### Financial services
Credit, savings, investment, insurance, payments, pensions and other partner products are exposed through eligibility, consent and regulated-provider controls.

### Intelligence
Financial health, safe-to-spend, forecasts, scenarios, alerts, explanations and recommendations. Calculation remains deterministic and auditable; AI explains and assists rather than inventing financial truth.

### Channels
- App: the primary individual experience and the complete everyday member experience for Individuals, Savings Groups and Investment Clubs. Personal is the default context; group and organisation Spaces are entered deliberately.
- Web: public marketing, existing/authorised customer access, enhanced analysis and institutional productivity.
- Workspace: institutional operations for Businesses/Employers, SACCOs and larger organisations.
- Partner: programme/product/provider operations and governed reporting.
- Access: USSD, WhatsApp/SMS and assisted journeys.

The public website is an information and access surface, not the canonical new-customer registration path. New customers start phone-first in the App with OTP, names and a six-digit PIN.

### Partnerships
SACCOs, insurers/brokers, fund managers, banks/MFIs, CRBs, employers, payment providers, cooperatives and other approved providers connect through a standard Partner Catalogue and provider-neutral adapters. Cito is the primary integration point where configured; each service retains a controlled direct-provider adapter where the underlying provider contract permits it. Provider routing is capability-specific: for identity, Cito/gnuGrid is primary for NIN and phone ownership, while biometric ID-image/selfie checks remain on an evidence-capable provider until Cito exposes an equivalent certified evidence contract.

Ambiguous primary-provider failure must not silently trigger a direct fallback. The original operation is reconciled first, then an explicit policy-controlled route switch may occur.

### Monetisation
OpFin is not economically dependent on lending. Monetisation is multi-sided and capability based:

- **Customers:** optional premium financial-management automation, advanced analytics/planning and convenience services; essential Individual and Savings Group management remains accessible.
- **Employers and organisations:** per-organisation and/or per-active-member subscriptions, financial-wellness programme administration, payroll/benefit integrations and approved servicing fees.
- **Credit:** interest margin and disclosed fees only where the applicable licence/product policy permits them.
- **Savings, investments and protection:** partner-paid distribution, administration, referral or AUM-linked economics where lawful and contractually agreed; customer assets remain partner/custody controlled.
- **Payments and remittance:** orchestration/transaction/FX economics only where OpFin or the executing regulated partner is authorised and the commercial agreement permits it.
- **Partner marketplace:** product distribution, servicing and revenue share with approved partners.
- **Platform/API:** integration setup, API usage, SaaS/platform access and managed-service fees for institutional partners.
- **Participatory/asset/community finance:** administration and servicing income only after governance, custody and regulatory gates are activated.
- **Data/insights:** privacy-safe aggregate/derived institutional analytics where lawful; OpFin does not sell customers' personal data.

Every revenue stream has an activation gate. Forecasted revenue is not production revenue until the applicable regulatory, contractual, provider, tax and accounting controls are active.

Commercial terms remain downstream of customer need, eligibility and suitability.

### Controls
KYC/KYB, consent, role-based permissions, entitlements, eligibility, maker-checker where required, audit, idempotency, ledger integrity, reconciliation, data protection and country policy.

## Core experience model

Observe → Understand → Plan → Act → Monitor → Adjust.

The UI should prefer human concepts such as **Money I have**, **Money I owe**, **Owed to me**, **Coming up** and **My goals** over accounting terminology. Advanced detail remains available on demand.

## Personal-first experience contract

The signed-in customer lands in their Personal context. Home answers **How am I doing financially today, and what needs my attention?** before presenting products. The primary position may include available money, safe-to-spend, savings, debt, upcoming obligations, cash-flow context, protection state and one useful next action backed by recorded server-authoritative data.

Financial products remain subordinate to the financial picture. Credit availability, insurance, savings and investments may be offered when relevant, eligible and activated, but a product is never allowed to become the definition of Home.

A person may enter a Savings Group, Investment Club, SACCO, Household, Business or other authorised Space without creating another OpFin identity. Leaving that organisation never deletes or transfers the person's Personal Space or Financial Passport.

One public OpFin App is the canonical consumer/member application. Administrative depth belongs in the role-aware OpFin Workspace on Web; partner systems integrate through governed APIs. USSD, WhatsApp/SMS and assisted journeys expose deliberately smaller task sets against the same authoritative platform state.

**Progressive disclosure rule:** platform complexity may increase, but the customer sees only capabilities relevant to their current context, permissions, eligibility and financial maturity.

## Group identity and regulatory credentials

Every Financial Space keeps an immutable OpFin internal identity. Government, regulator, cooperative, tax or other authority-issued identifiers are external credentials attached to that Space and carry their own issuer, jurisdiction and verification status. A newly introduced national group code therefore results in registration/verification of a credential rather than migration to a new group record.

External registration never grants automatic access to members' Personal Spaces and never substitutes for product-specific regulatory eligibility.

## Protection and insurance scope

Protection is a native financial-life capability. Personal protection may be discovered, enrolled, paid and serviced through the App only for independently approved products and activated regulated-provider arrangements. The disclosed insurer or underwriter remains responsible for underwriting, policy issuance and claim decisions.

Savings Groups, Investment Clubs and SACCOs may review products explicitly approved for group audiences from their Space. Group policy enrolment, member consent, premium collection, coverage allocation and claims handling remain fail-closed until the applicable partner, regulatory, custody, consent and operating controls are separately activated. Merely exposing a group product catalogue is not activation.

## Location context

Location is an optional supporting context, not a primary navigation module and not a continuous tracking service.

The App may request foreground location for an explicit financial task. Personal service discovery is approximate by design. Precise coordinates are reserved for location-dependent assets, insured risks or claim incidents. Manual location remains available when device/Google location is unavailable.

Google Maps Platform is used behind the OpFin API for place search, place details, reverse geocoding, Static Map previews and optional routes. Server API keys never enter Flutter/Web builds.

Saving Groups, Investment Clubs and SACCOs can record operating areas and meeting places without exposing member home locations. Physical financial assets and location-dependent insurance risks can carry their own location context.

Partner service networks are represented as partner-owned service points. Operations geographic reporting is aggregate-only with cohort suppression; partner users see only their own recorded service network. Individual customer pins are not exposed in partner analytics.

All Location Context records are non-credit by default. Location cannot become an underwriting input merely because it exists in OpFin. See docs/architecture/LOCATION_CONTEXT.md.

## Investment Club treasury and statements

Investment Clubs use the same Financial Space identity/membership architecture as Saving Groups, but mature club administration requires stronger treasury controls.

OpFin therefore supports:

- multiple club treasury accounts;
- an internal append-only cashbook;
- CSV import of bank/mobile-money/custodian/broker statements;
- idempotent source-file hashing;
- deterministic confidence-scored statement reconciliation;
- automatic high-confidence matching;
- a minimal human to-do queue for uncertain items;
- user-applied reconciliation requests such as suggested-match acceptance, missing-entry creation and documented accepted exceptions;
- explicit reconciliation confirmation after to-dos are cleared;
- closing-balance variance checks;
- immutable account statements;
- immutable consolidated all-activity Financial Space statements; and
- professional bank-style HTML plus CSV output.

Issued statements freeze club/account presentation details, period, transactions, running balances, totals, reconciliation status and an integrity hash. A later correction requires a new statement rather than rewriting an old one.

Bank-style means professional statement structure, not impersonation of an underlying financial institution. OpFin statements are clearly labelled as OpFin Financial Space statements; imported bank/custodian statements remain external reconciliation evidence.

Administration/import/reconciliation is a Web Workspace responsibility. Members can view issued statements in the App; authorised officers may issue account and consolidated statements. Consolidated statements cover all treasury accounts plus recorded Financial Space assets/obligations, with totals separated by currency rather than invented FX conversions.

See product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md for the canonical contract.

## Mobile completeness contract

An Individual must be able to use the App for onboarding/verification, money tracking, budgets, goals, debt, receivables, assets/liabilities, net worth, savings, investments, insurance, payments, borrowing, financial-health insights, statements/documents and Space switching, subject to actual activated provider services.

A Savings Group must additionally be able to create/join a group, invite/manage members, assign officials/roles, attach applicable external registration credentials, manage contributions/savings, member loans/repayments, expenses/fees, approvals, meetings/voting where enabled, goals, investments/protection, statements and audit history. An Investment Club reuses this group foundation and progressively adds capital accounts, ownership/unit rules, investment governance, portfolio administration and distributions rather than creating a parallel identity system.

No computer or paid subscription may be required for essential Individual or Savings Group financial management.

## Inclusive-finance and programme-delivery contract

OpFin supports inclusive-finance programmes through a deliberately separate measurement and delivery layer. Voluntary inclusion attributes such as gender, age cohort, disability status, refugee/displaced-person status and rural/urban classification may be used for service adaptation, lawful programme eligibility and aggregate reporting, but are not credit-risk inputs.

The platform provides financial-capability guidance, a non-score financial-reputation pathway, governed alternative-data provenance, configurable programme enrolment, versioned instruments/questions, follow-up schedules, reviewed localisation, multi-channel response capture, alternative credit-support evidence and privacy-suppressed impact/MEL reporting.

Programme-linked participant measurement requires active measurement consent and active enrolment. Programme exit closes the participation window for later outcome reporting while preserving legitimate historical/audit evidence.

Programme partners use dedicated identities and programme-scoped aggregate access. Small cohorts remain suppressed. Measured change must not be described as proven causal impact unless the evaluation design separately supports that conclusion.

Verified positive employment behaviour may create a small capped underwriting benefit when consented, independently verifiable and risk-eligible. Missing, unavailable, declined or negative employment-behaviour information is neutral and must not reduce the base Composite Score or data-coverage calculation.

**Product boundary:** Stolets is a separate SME automation, digitisation and commerce product. OpFin may consume an explicitly consented, approved external signal from Stolets or refer a customer to it, but OpFin does not absorb POS, inventory, purchasing or merchant-operations functions. Shared infrastructure does not create shared product identity.

See `product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md` for canonical programme and data-governance rules.

## Commercial and reporting integrity

Revenue events must be attributable to customer/Space, partner, product, commercial agreement, gross amount, OpFin share, partner share, tax, settlement state and reconciliation reference.

Commercial performance may report acquisition attribution, recorded costs, funnel state, repeat usage, overdue/NPL outcomes, recorded revenue and contribution. Unknown commercial fields remain unknown rather than being entered as zero.

Programme-to-commercial graduation is analytics only. It does not approve credit or change price/limit.

Every commercially relevant third-party service may emit service-economics evidence covering provider cost, customer charge, customer/partner/Cito/OpFin fees, tax, provider settlement, net revenue and margin where known. Principal, premium and investment capital are not automatically treated as revenue.

Advice and financial-health calculations must not use commission/revenue-share preference as a recommendation signal.

The canonical reporting contract is `PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md`.

## Current delivery evidence

At reviewed `main` commit `1a580e490ccb4cd5cc55f06ea9d09fad6619fd7e`, Railway commit statuses report successful API, Web, worker and scheduler deployments. No GitHub Actions workflow run is associated with that exact head.

Therefore the current source may be described as deployed, but the exact head must not be described as fully release-certified until required repository gates and external activation evidence pass.

## Delivery rule

Implementation proceeds by vertical journey slices and reuses working components. Existing identity, consent, credit, savings/protection, financial-wellbeing, ledger/reconciliation, governed provider adapters, community-finance and channel foundations are adapted rather than rewritten without evidence.

Every slice is done only when implementation, permissions, failure states, tests, documentation and applicable production acceptance evidence agree.
