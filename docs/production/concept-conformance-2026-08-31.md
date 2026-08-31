# OpFin Concept Conformance Review

Date: 2026-08-31
Source of truth: OpFin Updated Concept Note v2 and OpFin + CPay Build Specification v5.

## Verdict

The architecture and primary customer proposition are implemented, but the complete concept is **not yet fully production-achieved**. The implementation must distinguish AVAILABLE, PILOT and PLANNED capabilities and must never advertise PLANNED capabilities as live.

The production-ready customer mental model is retained as:

**Home | Borrow | Save | Grow | More**

OpFin owns customer identity, product logic, eligibility, consent, financial intelligence and journeys. CPay/Cito remains the canonical money-movement, provider execution and reconciliation layer.

## Conformance matrix

| Concept area | Current state | Evidence / control | Remaining dependency or gap |
| --- | --- | --- | --- |
| Identity, account activation and security | AVAILABLE / PILOT | Phone verification, OTP proof, progressive activation, Security Centre, audit events | KYC provider certification and production evidence policy remain provider-dependent |
| Consent and data governance | AVAILABLE | Purpose-specific consent, revocation, auditability, WhatsApp consent actions | Country-by-country consent/policy packs needed before expansion |
| Financial Passport | AVAILABLE | Consolidated identity/consent/financial snapshot with provenance labels | Broader linked-bank/mobile-money feeds are not yet live |
| Financial Compass, budget and calendar | AVAILABLE | Cash-flow, safe-to-spend, budgets, goals, upcoming events, next-best-action | Automatic external transaction ingestion remains dependent on linked-account capability |
| Responsible credit | PILOT | Eligibility, affordability, decision/offer flow, repayment schedule, production repayment service, hardship | Live underwriting/product approvals and provider certification remain regulated launch gates |
| Savings | PILOT | Savings products, contributions, withdrawals and operating controls | Partner product/funds-flow certification remains required |
| Insurance / protection | PILOT | Product, enrolment, premiums, claims and suitability/disclosure controls | Live insurer products, credentials and regulatory agreements required |
| Investments | PILOT | Suitability, approved product lifecycle, orders, pending-provider settlement | Licensed investment/custody/settlement partner required |
| Employer services | PILOT | Membership, verified employment context and benefit programmes | Live employer/payroll integrations and employer contracts required |
| WhatsApp | PILOT | Signed webhooks, replay prevention, dedicated OTP session, encrypted/auditable message records, step-up for money-changing actions | Meta/WhatsApp Business production credentials are required |
| Platform Autopilot | PILOT | Exception-first work queue, autonomy tiers, safe reversible actions, human controls | Expand only after evidence shows control quality |
| Regulatory reporting | AVAILABLE | Auto-generated evidence packs, validation, hashes, maker-checker approvals, AML/privacy/consumer/payment metrics | Officer/accountable-person filing remains human controlled where law requires it |
| Financial integrity and reconciliation | AVAILABLE | Continuous debit/credit integrity audit, duplicate/orphan detection, reconciliation exceptions, immutable evidence | External settlement evidence quality depends on CPay/provider feeds |
| Product Factory / Rules / Workflow | AVAILABLE | Versioned definitions and maker-checker governance | Additional product-domain rules to be introduced through controlled lifecycle |
| SACCO/community finance | PLANNED | Capability explicitly registered as PLANNED | Build member/guarantor/community workflows and partner/legal model |
| P2P / participatory finance | PLANNED | Capability explicitly registered as PLANNED | Define lender-of-record, custody, loss allocation, marketplace rules, disclosures and settlement |
| Capital/private loan books | PLANNED | Capability explicitly registered as PLANNED | Capital-partner onboarding, mandates, allocation and reporting needed |
| USSD | PLANNED | Capability explicitly registered as PLANNED | Telco/aggregator session, authentication and journey adapter needed |
| Offline-aware mode | PLANNED | Capability explicitly registered as PLANNED | Define safe read/write boundaries; financial state remains server-authoritative |
| Rewards and referrals | PLANNED | Capability explicitly registered as PLANNED | Fraud-resistant attribution, reward rules and accounting required |
| Linked bank/mobile-money accounts | PLANNED | Capability explicitly registered as PLANNED | Aggregator/provider integrations, consent and freshness/provenance rules required |
| Asset/device finance and GPS/geofencing | PLANNED | Capability explicitly registered as PLANNED | Product/legal model, device provider, consent, retention and geolocation safeguards required |
| Household and microbusiness finance | PLANNED | Capabilities explicitly registered as PLANNED | Separate financial contexts, categorisation and product policies required |
| Partner distribution / partner console | PLANNED | Capability explicitly registered as PLANNED | Partner APIs, scopes, certification, settlement and support model required |

## Production integrity rules

1. A provider success message is not accounting finality.
2. A financial command must be idempotent and traceable.
3. No unexplained ledger difference may be hidden by a balancing plug entry.
4. High-impact decisions remain maker-checker or human-controlled where required.
5. A PLANNED capability must not appear to customers as live.
6. PILOT capabilities must retain explicit provider/finality states and launch limits.
7. Regulatory evidence may be generated and validated automatically, but legally accountable filing decisions remain with the designated officer where required.
8. Employer data is purpose-limited and must not become an unrestricted credit-behaviour feed.

## Journey simplification standard

Every customer journey should follow the same pattern:

**Intent → only required information → clear review → explicit consent/confirmation → observable status → next action**

Rules:

- one primary action per screen;
- reuse verified customer information;
- deep-link directly to missing requirements;
- never expose provider/internal state names where plain-language status is sufficient;
- show estimates as estimates, never as confirmed cash;
- keep KYC and consent contextual rather than permanent navigation concepts;
- preserve Home, Borrow, Save, Grow and More as the only permanent customer navigation areas.

## Next implementation sequence

1. Finish provider-backed PILOT certification for KYC, credit, savings, protection, investments, employer and WhatsApp.
2. Activate linked accounts because they improve Compass, affordability, budgeting and recommendations across multiple domains.
3. Build USSD using the same central rules, KYC, consent and product state services rather than duplicating logic.
4. Build SACCO/community and P2P only after lender-of-record, custody, settlement, disclosures and complaint responsibilities are approved.
5. Add rewards/referrals only with abuse controls and ledger-backed reward accounting.
6. Add asset/device finance only after privacy, geolocation, repossession/support and provider controls are approved.
7. Expand country coverage only through versioned country policy packs and provider certification.

## Definition of concept completion

The concept may only be declared fully achieved when every capability required by the concept is either AVAILABLE or an intentionally licensed/partner-dependent PILOT with its external launch gates satisfied. PLANNED items prevent a claim of full implementation completion.
