# Web and customer-surface production readiness matrix

Updated: 18 September 2026

**Available** means the software journey/control exists. It does not mean an external regulator/provider has authorised or activated it.

| Capability | Software status | External/operational gate |
| --- | --- | --- |
| Phone → OTP → PIN account journey | Available | SMS delivery/provider health |
| KYC front/back/selfie | Available | Identity-provider production credentials/certification |
| Credit profile and decomposable score | Available | CRB/MNO/approved partner feeds actually configured |
| Automatic credit approval | Available | Verified affordability input + licensed product/funding |
| Loan application/offer | Available | Approved catalogue/lender/pricing |
| UMRA disclosure/complaint controls | Available | Legal entity/licence/address/official complaint contacts configured |
| Credit-information exchange | Available | Applicable authorised credit-reference endpoint/schema/credentials |
| NPL/default-interest tracking | Available | Operations policy/authorised exception governance |
| Transaction e-receipts | Available | SMS/payment provider availability |
| Guarantor confirmation | Available | Product uses guarantors; consent process operational |
| Term-change governance | Available | Prior UMRA approval evidence for rate changes |
| Admin UMRA books/reports | Available | Responsible officer review/submission process |
| CPay disbursement/repayment | Available | Production credentials/certification/provider health |
| WhatsApp | Available | Meta production configuration |
| USSD | Available | Aggregator/short-code provisioning |
| Accessibility | Available in software | Real-device/PWD UAT remains release evidence |
| Backup/recovery | Infrastructure controlled | Restore/PITR drill evidence |
| Savings/protection/investments/etc. | Capability gated | Applicable licensed provider/product approval |

## Current launch navigation

**Home | Borrow | Activity | More**

Long-range/provider-gated features must not expand primary launch navigation until activated.

## Release rule

The exact release candidate must pass CI/security/deployment contract and the production environment must satisfy the relevant external gates. A green frontend build does not certify a lender, bureau, payment rail or regulator approval.
