# OpFin Product Blueprint

Status: Canonical product contract  
Updated: 23 September 2026  
Language: English (United Kingdom)

## Product position

OpFin is a financial operating platform with embedded financial services. It helps a person or organisation understand, manage, plan and improve its financial position. Lending is one capability, not the product boundary.

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
- App: complete everyday experience for Individuals and Savings Groups.
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

## Mobile completeness contract

An Individual must be able to use the App for onboarding/verification, money tracking, budgets, goals, debt, receivables, assets/liabilities, net worth, savings, investments, insurance, payments, borrowing, financial-health insights, statements/documents and Space switching, subject to actual activated provider services.

A Savings Group must additionally be able to create/join a group, invite/manage members, assign officials/roles, manage contributions/savings, member loans/repayments, expenses/fees, approvals, meetings/voting where enabled, goals, investments/protection, statements and audit history.

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
