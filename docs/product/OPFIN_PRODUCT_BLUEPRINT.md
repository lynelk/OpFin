# OpFin Product Blueprint

Status: Canonical product contract  
Updated: 22 September 2026  
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
- Web: enhanced personal/group analysis and productivity.
- Workspace: institutional operations for Businesses/Employers, SACCOs and larger organisations.
- Partner: product/provider operations and integration.
- Access: USSD, WhatsApp/SMS and assisted journeys.

### Partnerships
SACCOs, insurers/brokers, fund managers, banks/MFIs, CRBs, employers, payment providers, cooperatives and other approved providers connect through a standard Partner Catalogue and provider-neutral adapters. Cito is the primary integration point; each service retains a controlled direct-provider adapter where the underlying provider contract permits it.

### Monetisation
Subscriptions, commissions, revenue share, transaction economics, SaaS/platform fees, API/integration fees and servicing/administration income. Commercial terms are downstream of customer need and eligibility.

### Controls
KYC/KYB, consent, role-based permissions, entitlements, eligibility, maker-checker where required, audit, idempotency, ledger integrity, reconciliation, data protection and country policy.

## Core experience model

Observe → Understand → Plan → Act → Monitor → Adjust.

The UI should prefer human concepts such as **Money I have**, **Money I owe**, **Owed to me**, **Coming up** and **My goals** over accounting terminology. Advanced detail remains available on demand.

## Mobile completeness contract

An Individual must be able to use the App for onboarding/verification, money tracking, budgets, goals, debt, receivables, assets/liabilities, net worth, savings, investments, insurance, payments, borrowing, financial-health insights, statements/documents and Space switching.

A Savings Group must additionally be able to create/join a group, invite/manage members, assign officials/roles, manage contributions/savings, member loans/repayments, expenses/fees, approvals, meetings/voting where enabled, goals, investments/protection, statements and audit history.

No computer or paid subscription may be required for essential Individual or Savings Group financial management.

## Inclusive-finance and programme-delivery contract

OpFin supports inclusive-finance programmes through a deliberately separate measurement and delivery layer. Voluntary inclusion attributes such as gender, age cohort, disability status, refugee/displaced-person status and rural/urban classification may be used for service adaptation, lawful programme eligibility and aggregate reporting, but are not credit-risk inputs.

The platform provides financial-capability guidance, a non-score financial-reputation pathway, governed alternative-data provenance, configurable programme enrolment, alternative credit-support evidence and privacy-suppressed impact reporting. Existing affordability, CRB, consent, credit reason-code, UMRA, hardship and partner controls remain authoritative rather than being duplicated.

Verified positive employment behaviour may create a small capped underwriting benefit when consented, independently verifiable and risk-eligible. Missing, unavailable, declined or negative employment-behaviour information is neutral and must not reduce the base Composite Score or data-coverage calculation.

**Product boundary:** Stolets is a separate SME automation, digitisation and commerce product. OpFin may consume an explicitly consented, approved external signal from Stolets or refer a customer to it, but OpFin does not absorb POS, inventory, purchasing or merchant-operations functions. Shared infrastructure does not create shared product identity.

See product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md for the canonical programme and data-governance rules.

## Revenue integrity

Revenue events must be attributable to customer/Space, partner, product, commercial agreement, gross amount, OpFin share, partner share, tax, settlement state and reconciliation reference. Every commercially relevant third-party service also emits a service-economics record covering provider cost, customer charge, customer/partner/Cito/OpFin fees, tax, provider settlement, net revenue and margin where known. Principal, premium and investment capital are not automatically treated as revenue. Advice and financial-health calculations must not use commission/revenue-share preference as a recommendation signal.

The canonical reporting contract is `PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md`, including the Stolets Financial Passport, Financial Account Behaviour Report, Capital & Loan Book Performance Report, Insurance Product/Premium/Claims Report, Savings & Investment Partner Report and Universal Service Economics & Revenue Report.

## Delivery rule

Implementation proceeds by vertical journey slices and reuses working production components. Existing identity, consent, credit, savings/protection, financial-wellbeing, ledger/reconciliation, governed provider adapters, community-finance and channel foundations are migrated or adapted rather than rewritten without evidence.
