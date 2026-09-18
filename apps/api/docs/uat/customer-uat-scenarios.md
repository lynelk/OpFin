# Customer UAT scenarios

Updated: 18 September 2026  
Primary sign-off: Product owner  
Supporting: Compliance, Support, Finance, Accessibility/PWD representative or adviser, Engineering, Operations

No Critical/High defect may remain open at launch.

| ID | Journey | Test | Expected result |
| --- | --- | --- | --- |
| CUST-01 | Sign-up | Enter valid phone | OTP sent; no long registration form |
| CUST-02 | OTP | Supported Android auto-fill | Code fills without SMS-read permission; manual fallback remains |
| CUST-03 | OTP | Wrong/expired code and resend | Safe attempt/expiry messaging; no account created |
| CUST-04 | Names | First/optional other/last | Required fields clear; names persist |
| CUST-05 | PIN | Create/confirm strong 6-digit PIN | Account created, authenticated session returned, Home opens directly |
| CUST-06 | PIN | Repeated/sequential PIN | Rejected with simple instruction |
| CUST-07 | Login | Phone + PIN | Authenticated; no password required for new customer |
| CUST-08 | Reset | OTP-backed forgotten PIN | PIN reset; old sessions invalidated as designed |
| CUST-09 | Home | New unverified user | One clear next action: identity verification |
| CUST-10 | KYC | NIN without required images | Cannot submit; front/back/selfie all required |
| CUST-11 | KYC | Good ID front/back/selfie | Evidence stored privately; provider checks recorded |
| CUST-12 | KYC | Blur/bad/mismatch/provider unavailable | Retake/review state; never falsely verified |
| CUST-13 | PWD | Customer cannot complete normal camera step | Assisted-verification support case can be created without sharing PIN/OTP |
| CUST-14 | Consent | Grant credit-processing consent | Version/channel/timestamp recorded |
| CUST-15 | Consent | Revoke consent | New credit processing blocked until re-granted |
| CUST-16 | Phone | No second SIM | Baseline scoring can proceed; second phone shown as optional |
| CUST-17 | Phone | Add second phone | OTP verifies ownership; profile refreshes |
| CUST-18 | Scoring | CRB core + partial eligible sources | Coverage recorded; no invented values |
| CUST-19 | Scoring | Source offline | Profile pending/provisional as policy allows; source shown unavailable internally |
| CUST-20 | Score UI | Open score detail | Composite + plain component labels; no probability-of-default shown |
| CUST-21 | Home | Eligible/no active debt | Available-to-borrow is primary; Borrow action enabled |
| CUST-22 | Home | Amount due | Amount due/Repay becomes primary; borrowing blocked per policy |
| CUST-23 | Loan application | Open page | Available limit and amount due visible |
| CUST-24 | Loan application | Enter above limit | Server/client reject and show maximum |
| CUST-25 | Loan application | Terms | Only API-eligible store-compliant terms appear; no legacy 14/30/60-day picker |
| CUST-26 | Application | Submit eligible request | Decision runs automatically where profile qualifies; no money moves |
| CUST-27 | Application | Referred/declined | Clear non-misleading status/reason path |
| CUST-28 | Offer | Review | Amount received, interest, fees, total, term/frequency, required APR/timing match backend snapshot |
| CUST-29 | Wallet | Choose payout wallet | Only customer's verified wallets selectable |
| CUST-30 | Offer | Accept stale/changed disclosure | Hash mismatch blocks acceptance |
| CUST-31 | Disbursement | Provider pending | UI says pending, not received |
| CUST-32 | Disbursement | Provider success/failure/reversal | Loan/ledger/reconciliation state matches provider finality |
| CUST-33 | Repayment | Full repayment request | Idempotency key present; verified wallet used |
| CUST-34 | Repayment | Partial repayment | Allowed amount allocated exactly; remaining balance correct |
| CUST-35 | Repayment | Duplicate retry | No duplicate economic collection |
| CUST-36 | Repayment | Provider pending | UI says payment request sent, not payment received |
| CUST-37 | WhatsApp | Webhook verification/signature | Invalid production signature rejected |
| CUST-38 | WhatsApp | START/verification | Short verified session established for registered user |
| CUST-39 | WhatsApp | Unregistered phone | Directed to secure registration; PIN never requested in chat |
| CUST-40 | WhatsApp | KYC → NIN → front/back/selfie | Three guided images stored as private KYC evidence |
| CUST-41 | WhatsApp | LIMIT/PROFILE | Same score/limit state as API/app |
| CUST-42 | WhatsApp | BORROW/REPAY | Safe hand-off; no PIN in chat; no unauthenticated money movement |
| CUST-43 | USSD | Main menu | Short 1–6 menu fits normal session |
| CUST-44 | USSD | My limit/My loan | Same server-authoritative state as app |
| CUST-45 | USSD | KYC image need | Instructs authenticated app/WhatsApp; does not pretend USSD can capture images |
| CUST-46 | USSD | Invalid callback secret | Rejected where secret configured |
| CUST-47 | Accessibility | TalkBack/VoiceOver | Critical controls/status read in logical order |
| CUST-48 | Accessibility | Large OS/OpFin text | No clipped critical amount/action; scrolling remains understandable |
| CUST-49 | Accessibility | Reduced motion | Non-essential motion reduced/removed |
| CUST-50 | Low literacy | Assisted moderated test | User can identify amount due/limit and next action without interpreting technical scoring terminology |
| CUST-51 | Network | Drop/retry during setup | User can safely resume; no duplicate application/payment |
| CUST-52 | Privacy | Profile/KYC | NIN masked; image paths/raw provider payload not exposed |
| CUST-53 | Account | Delete account | Required regulated retention explained; eligible deletion works |
| CUST-54 | Permissions | Android manifest/runtime | Camera only for KYC; no broad SMS/gallery/storage permissions |
| CUST-55 | Cross-channel | App vs WhatsApp vs USSD same customer | Score, limit, due and loan state are consistent |
| CUST-56 | Offer | Review detailed pricing | Interest method/calculation, fee breakdown/timing, total cost and default terms are understandable |
| CUST-57 | Offer | Review complaints procedure | Complaint resolution target/contact route visible before acceptance |
| CUST-58 | Credit reporting | Offer acceptance | Separate positive/negative credit-information reporting consent required and recorded |
| CUST-59 | Credit reporting | Decline/omit reporting consent | Offer acceptance is blocked; no hidden consent |
| CUST-60 | Receipt | Successful disbursement | E-receipt appears only after provider-confirmed finality |
| CUST-61 | Receipt | Successful repayment | E-receipt appears under Activity and matches transaction amount/reference |
| CUST-62 | Complaint | Submit complaint | Case reference/procedure returned and regulatory due date recorded |
| CUST-63 | Guarantor | Product requires guarantor | Borrower can manually add no more than two contacts; no contact-list access |
| CUST-64 | Guarantor | Independent response | Guarantor confirms/rejects independently; borrower never handles guarantor code |


## Exit criteria

- All scenarios pass or have an explicitly accepted non-critical defect.
- No Critical/High customer, security, accessibility, financial-integrity or compliance defect remains.
- KYC provider, scoring sources and CPay configuration are tested in the intended launch environment.
- Real-device Android UAT includes representative low-literacy and PWD/accessibility testing.
- Product, Compliance, Support, Finance, Engineering and Operations sign off the exact release candidate.
