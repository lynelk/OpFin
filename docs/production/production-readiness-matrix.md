# Production Readiness Matrix

Date: 2026-08-31

**Available** means the OpFin software journey, persistent control model, authorization boundary and operational evidence path exist. Where a licensed provider, regulator, telco, employer, custodian or production credential is required, the capability remains externally gated even though the software implementation is available. This distinction prevents code completion from being misrepresented as regulatory or commercial authorization.

| Capability | Software status | External activation gate / production rule |
| --- | --- | --- |
| Phone-first registration, OTP, security, RBAC | Available | Production security policy remains mandatory. |
| Progressive activation / Financial Compass / budgeting / calendar | Available | None beyond normal production operations. |
| KYC | Available | Identity-provider production credentials and certification. |
| Consent / Passport / Credit Builder / hardship | Available | Product/legal policy versions must remain current. |
| Responsible borrowing and repayments | Available | Licensed product/funding configuration; CPay provider finality. |
| Savings | Available | Licensed savings partner products and certified CPay/provider funds flow. |
| Insurance / protection | Available | Licensed insurer/underwriter product activation and issuance. |
| Investments | Available | Licensed investment provider, custody and settlement. |
| Employer services | Available | Employer/payroll integration for live payroll deductions or verification. |
| Money Autopilot | Available | Certified mandates for any automatic money movement. |
| WhatsApp | Available | Meta production credentials. High-impact actions still require step-up. |
| USSD | Available | Telco/aggregator shortcode and callback configuration; sensitive actions redirect to secure authentication. |
| Linked bank/mobile-money/other accounts | Available | Provider API or authorised data connection before provider-confirmed status. |
| Offline-aware mode | Available | Server remains authoritative; replay is hashed/idempotent and conflicts require review. |
| Household finance | Available | None beyond applicable product policy. |
| Microbusiness finance | Available | None beyond applicable product policy. |
| SACCO / VSLA / community finance | Available | Institution integration and independent membership verification. |
| Asset/device finance | Available | Asset supplier and licensed finance product activation. Deposit movement requires approval, step-up and CPay. |
| P2P / participatory finance | Available | Lender-of-record, custody, settlement and regulatory approval. Compliance approval requires loss, fee and custody disclosures. |
| Capital / private loan books | Available | Capital provider and regulatory activation; mandate requires independent approval and investment policy. |
| Partner distribution | Available | Partner due diligence, contract and production credentials; allowed products are explicitly scoped. |
| Rewards / referrals | Available | Reward only after identity/eligibility and anti-abuse review; posting is controlled and ledger-backed. |
| Product Factory / Rules / Workflow | Available | Maker-checker/version controls remain mandatory. |
| Platform Autopilot | Available | High-impact work remains human-controlled unless separately approved. |
| Regulatory reporting | Available | Filing/submission still requires the responsible regulated officer where applicable. |
| Reconciliation / financial integrity self-audit | Available | Continuous exceptions remain persistent; never auto-balance evidence away. |
| Customer support | Available | SLA and operating team capacity remain operational obligations. |
| Accessibility | Improving | Formal WCAG audit and assistive-technology UAT remain release-quality controls. |
| Web / Flutter Android build | Available | Real-device and store-distribution certification remain delivery controls. |
| Backup / recovery | Infrastructure gate | Production backup, PITR and restore-drill evidence remain a separate infrastructure release requirement. |

## Long-range operating rules

1. Customer navigation stays **Home, Borrow, Save, Grow, More**. Long-range capabilities live in one **Connected financial life** workspace rather than expanding primary navigation.
2. OpFin owns identity, consent, eligibility, product, workflow and financial relationship state. CPay/Cito remains the canonical money-movement and payment-finality boundary.
3. No provider balance, membership, payment or settlement is labelled confirmed without provider evidence.
4. High-impact financial actions use idempotency, fresh step-up authentication, explicit CPay execution and asynchronous provider finality.
5. Maker-checker applies to asset finance, linked-account verification, community membership, participatory finance, capital, partner activation and reward authorization.
6. Offline capture cannot overwrite server truth. Reused batch identifiers with changed payloads are rejected and unresolved conflicts enter operations review.
7. External activation gates are first-class capability metadata, not footnotes hidden in documentation.
