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
10. **Reuse financial infrastructure.** OpFin owns financial intent, experience, intelligence and orchestration. CPay/Cito executes and reconciles money movement where that boundary applies.

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
SACCOs, insurers/brokers, fund managers, banks/MFIs, CRBs, employers, payment providers, cooperatives and other approved providers connect through a standard Partner Catalogue and adapters.

### Monetisation
OpFin is not economically dependent on lending. Monetisation is multi-sided and capability based:

- **Customers:** optional premium financial-management automation, advanced analytics/planning and convenience services; essential Individual and Savings Group management remains accessible.
- **Employers and organisations:** per-organisation and/or per-active-member subscriptions, financial-wellness programme administration, payroll/benefit integrations and approved servicing fees.
- **Credit:** interest margin and disclosed fees only where the applicable licence/product policy permits them.
- **Savings, investments and protection:** partner-paid distribution, administration, referral or AUM-linked economics where lawful and contractually agreed; customer assets remain partner/custody controlled.
- **Payments and remittance:** orchestration/transaction/FX economics only where OpFin or the executing regulated partner is authorised and the commercial agreement permits it.
- **Partner marketplace:** product distribution, servicing and revenue share with banks/MFIs, SACCOs, insurers/brokers, investment/fund managers and other approved partners.
- **Platform/API:** integration setup, API usage, SaaS/platform access and managed-service fees for institutional partners.
- **Participatory/asset/community finance:** administration and servicing income only after the relevant governance, custody and regulatory gates are activated.
- **Data/insights:** privacy-safe institutional analytics may be monetised only from permitted aggregate/derived insights; OpFin does not sell customers' personal data.

Every revenue stream has an activation gate. A forecast may model a future stream, but production revenue is recognised only after the applicable regulatory, contractual, provider, tax and accounting controls are active.

Commercial terms remain downstream of customer need, eligibility and suitability.

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

**Product boundary:** Stolets is a separate SME automation, digitisation and commerce product. OpFin may consume an explicitly consented, approved external signal from Stolets or refer a customer to it, but OpFin does not absorb POS, inventory, purchasing or merchant-operations functions. Shared infrastructure does not create shared product identity.

See product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md for the canonical programme and data-governance rules.

## Revenue integrity

Revenue events must be attributable to customer/Space, partner, product, commercial agreement, gross amount, OpFin share, partner share, tax, settlement state and reconciliation reference. Advice and financial-health calculations must not use commission/revenue-share preference as a recommendation signal.

## Delivery rule

Implementation proceeds by vertical journey slices and reuses working production components. Existing identity, consent, credit, savings/protection, financial-wellbeing, ledger/reconciliation, CPay adapter, community-finance and channel foundations are migrated or adapted rather than rewritten without evidence.
