# Production readiness matrix

Updated: 18 September 2026

**Available** means the software/control exists and is covered by current source/tests. It does not mean a regulator, lender, bureau, telco or payment provider has activated the external dependency.

| Capability | Software | External / operational gate |
| --- | --- | --- |
| Phone/OTP/PIN authentication | Available | SMS delivery/provider health and final production configuration |
| KYC front/back/selfie/liveness/face/NIN-phone | Available | Production identity-provider credentials/certification |
| Consent | Available | Current legal/policy versions and production privacy notice |
| Composite credit profile | Available | CRB/MNO/approved partner sources actually configured |
| Affordability | Available | Verified income/obligation source required for automatic approval |
| Loan application | Available | Approved product catalogue/licensed funding |
| Formal offer/disclosures | Available | Licensed entity/address/complaint contacts/pricing verified |
| Credit-information reporting | Available | Applicable authorised bureau/mechanism endpoint/schema/certification |
| Credit-reporting consent | Available | Customer must actively consent; no bypass |
| Disbursement/repayment | Available | CPay production credentials/certification/provider health |
| Ledger/reconciliation | Available | Production operations monitoring and exception handling |
| E-receipts/instant acknowledgement | Available | SMS/provider delivery health |
| Complaint SLA | Available | Staffed operating process and official complaint contacts |
| NPL/default-interest controls | Available | Approved operations policy and exception governance |
| Guarantor controls | Available | Only for products that use guarantors |
| Credit-term/rate governance | Available | Prior UMRA approval evidence for rate changes |
| Regulatory books/reports | Available | Responsible officer review and actual external filing |
| WhatsApp | Available | Meta production credentials |
| USSD | Available | Aggregator/short code/callback configuration |
| Accessibility | Available in software | Real-device/PWD UAT evidence |
| Account deletion | Available | Retention/legal basis and live public URL |
| Backup/recovery | Infrastructure gate | Backup/PITR/restore drill |
| Savings/protection/investments/etc. | Capability gated | Applicable licensed partner/product approval |

## Launch navigation

**Home | Borrow | Activity | More**

Long-range capabilities remain behind capability/provider gates and should not crowd the launch borrower experience.

## No-go conditions

Production is blocked by any of the following:

- failed release/security/deployment gate;
- unconfigured or unverified lender/legal identity;
- unavailable mandatory KYC/affordability/payment provider path;
- prohibited or unapproved product terms;
- missing private KYC storage;
- unresolved financial-integrity/reconciliation exception;
- missing credit-reporting consent/data-quality controls;
- unresolved Critical/High accessibility/compliance defect.
