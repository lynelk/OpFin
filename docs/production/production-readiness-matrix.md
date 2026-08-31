# Production Readiness Matrix

Date: 2026-08-31

This matrix replaces the stale May 2026 snapshot. Statuses are intentionally conservative: **Available** means the product surface and backend control exist; **Pilot** means the journey is built but still depends on provider, regulatory, certification or limited-release controls; **Planned** means it must not be represented as live.

| Capability | Frontend status | Backend/control status | Production verdict |
| --- | --- | --- | --- |
| Phone-first registration and OTP proof | Available | Available | Production foundation present; OTP proof is required before registration. |
| Progressive activation | Available | Available | Account-scoped activation progress and primary goal are implemented. |
| Route protection and RBAC | Available | Available | Frontend route guards plus backend authorization; backend remains source of truth. |
| Customer navigation | Available | N/A | Five permanent customer areas only: Home, Borrow, Save, Grow, More. |
| Financial Compass / Home | Available | Available | Next-best-action, financial position, safe-to-spend, commitments, goals and calendar are implemented. |
| Budgeting / money plan | Available | Available | Budgeting and recorded cash-flow journeys are implemented; linked-account auto-ingestion remains planned. |
| Financial calendar | Available | Available | Confirmed/scheduled/estimated event distinctions are implemented. |
| KYC | Pilot | Pilot | Central KYC journey exists; live provider evidence/certification remains provider-dependent. |
| Consent management | Available | Available | Purpose-specific grant/revoke and auditability are implemented. |
| Security Centre | Available | Available | Security controls and account-security activity are implemented. |
| Financial Passport | Available | Available | Provenance-labelled consolidated snapshot is implemented. |
| Responsible borrowing | Pilot | Pilot | Eligibility, affordability, decision/offer, repayment schedule, repayment servicing and hardship foundations exist; live product approvals/certification remain launch gates. |
| Repayments | Pilot | Pilot | Production repayment service and customer repayment/account views exist; provider finality remains CPay-controlled. |
| Credit Builder | Available | Available | Improvement-plan logic uses confirmed behaviour and avoids fabricated bureau scores. |
| Financial Shock / hardship | Available | Available | Guided hardship intake and maker-checker approval are implemented. |
| Savings | Pilot | Pilot | Products, contributions, withdrawals and operating controls exist; partner funds-flow certification is still required. |
| Insurance / protection | Pilot | Pilot | Product, enrolment, premiums and claims journeys exist; insurer activation remains external. |
| Investments | Pilot | Pilot | Suitability and approved-product order flow exist; live custody/settlement partner remains external. |
| Employer services | Pilot | Pilot | Employer membership and benefit-programme workflows exist; live payroll/employer integrations remain external. |
| Money Autopilot | Pilot | Pilot | User-authorised rules and execution logs exist; financial actions remain provider/finality governed. |
| WhatsApp | Pilot | Pilot | Secure session, dedicated OTP, signed/replay-protected webhook model and audited journeys exist; Meta credentials are required for live delivery. |
| Support | Pilot | Pilot | Case creation/reference/status foundation exists; continue expanding SLA automation and knowledge routing. |
| Platform Autopilot | Available | Pilot | Exception-first control centre exists; expand autonomous actions only after evidence and policy approval. |
| Regulatory reporting | Available | Available | Auto-generated regulator evidence packs, validation, hashing and maker-checker approval exist. |
| Financial integrity / self-audit | Available | Available | Continuous ledger/reconciliation integrity checks and persistent high-impact exceptions exist. |
| Reconciliation | Available | Available / CPay-owned movement | OpFin monitors evidence and exceptions; CPay remains canonical payment execution/reconciliation owner. |
| Product Factory | Available | Available | Versioned product lifecycle and maker-checker governance exist. |
| Rules Engine | Available | Available | Versioned decision rules and independent approval exist. |
| Workflow Engine | Available | Available | Versioned workflow definitions and approval exist. |
| SACCO/community finance | Planned | Planned | Do not market as live. |
| P2P/participatory finance | Planned | Planned | Do not market as live until lender-of-record, custody, settlement, disclosures and complaints model are approved. |
| Capital/private loan books | Planned | Planned | Do not market as live. |
| USSD | Planned | Planned | Do not market as live until telco/aggregator channel and secure session adapter are certified. |
| Linked accounts | Planned | Planned | Do not treat external balances as confirmed until provider integrations and freshness/provenance rules exist. |
| Rewards/referrals | Planned | Planned | Do not market as live until abuse controls and ledger-backed reward accounting exist. |
| Asset/device finance | Planned | Planned | Do not market as live until product, privacy, geolocation and servicing controls are approved. |
| Offline-aware mode | Planned | Planned | Financial state remains server-authoritative; safe offline boundaries still need implementation. |
| Household finance | Planned | Planned | Separate financial context and policy model still required. |
| Microbusiness finance | Planned | Planned | Separate categorisation, risk and product policy still required. |
| Accessibility | Partial / improving | N/A | Responsive semantic structure exists; formal WCAG audit remains a release-quality improvement. |
| Mobile responsiveness | Available / tested in CI build | N/A | Web responsive shell and Flutter Android release build are part of CI; broader real-device UAT remains recommended. |
| Tests/build | Available | Available | Frontend and backend CI pipelines are required release gates. |

## Production release rule

A capability is not considered production-ready merely because a route exists. Regulated or money-changing capabilities require the relevant legal/regulatory approval, provider credentials, product version approval, KYC/consent controls, financial calculation testing, CPay certification, reconciliation evidence, support readiness, monitoring, backup/restore evidence, disclosures, maker-checker controls, audit retention and go/no-go approval.

## Customer simplicity rule

- One primary next action on Home.
- Four clear financial choices: Borrow, Save, Grow, Protect.
- Setup, security, automation and support live under More.
- KYC and consent are contextual controls, not permanent primary destinations.
- Planned capabilities must not appear as live journeys.
