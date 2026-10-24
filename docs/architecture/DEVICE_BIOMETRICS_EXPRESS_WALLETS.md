# Device Biometrics and Express Payment Wallets

**Status:** Native biometric device-unlock source candidate; Apple Pay/Google Pay tokenised checkout **not activated**.  
**Date:** 24 October 2026 • British English  
**Related strategy:** [Unified Five-Direction Strategy](../product/OPFIN_UNIFIED_FIVE_DIRECTION_STRATEGY.md) and [Financial Spaces](FINANCIAL_SPACES_DOMAIN_MODEL.md).

## 1. Two independent product capabilities

**Touch ID / Face ID / Android fingerprint or enrolled face recognition** are local authentication tools, not a provider integration. OpFin uses the **operating system's enrolment** only; no biometric templates, images, fingerprints, voiceprints, PINs or raw match data are sent to the API, shared with institutions or stored by OpFin.

**Apple Pay / Google Pay** are tokenised **checkout methods** provided through a certified acquiring/payment service in supported markets. They are not equivalents of an OpFin wallet, a mobile-money account, a customer's loan disbursement destination or a licence to collect pooled investment, member savings or customer deposits.

## 2. Implemented native local biometric foundation

| Layer | Source | Behaviour |
| --- | --- | --- |
| Flutter security client | `apps/client/lib/services/device_biometrics.dart` | Native channel `co.opfin/device_biometrics`; only enrolled OS biometrics; returns available/not available and success/fail; exception fails closed |
| iOS | `apps/client/ios/Runner/AppDelegate.swift` + `Info.plist` | LocalAuthentication Touch ID/Face ID, purpose-specific native OS prompt and Face ID purpose text |
| Android | `apps/client/android/app/src/main/kotlin/co/opfin/app/MainActivity.kt` and `AndroidManifest.xml` | Native BiometricPrompt on API 28+ when compatible enrolled biometrics exist, USE_BIOMETRIC; older/unsupported phones use OpFin PIN |
| Opt-in settings | `apps/client/lib/device_security_screen.dart`, Profile and More | Customer explicitly verifies biometrics before enabling. Setting stored securely, bound to current user and wiped by sign-out |
| Existing-session cold start | Splash → `BiometricUnlockScreen` | Biometrically unlock only an existing authenticated session; cancellation/failure never opens Home; PIN alternative clears cached session and requires new server login |
| Sensitive local change | `apps/client/lib/wallets_screen.dart` | When opted in, confirm device biometrics before requesting change to saved payout/repayment wallet. API still performs real mutation and checks |
| Tests | `device_biometrics_test.dart` | Enrolment success, cancellation, unsupported devices, encrypted opt-in, logout removal |

### Current limitations and security requirements

- The biometric prompt is **not** a replacement for fresh OTP, real six-digit PIN login, KYC/NIN, customer consent, identity proof or server-signed high-value transaction authorisation.
- It does **not** constitute a legally sufficient authorisation for loan offers, capital contributions, group payout, investment order, insurer premium or employer salary deduction.
- Biometric matching happens in secure OS facilities; OpFin never stores or transfers raw biometrics.
- Existing-session access is gated at **cold start**. A complete resume-from-background privacy shield, compromised/rooted-device handling, OS enrolment change assurance, additional sensitive steps and lower-API device compatibility remain separate security/device-acceptance work before describing this as comprehensive application locking.
- Device security settings are per account, and sign-out wipes the setting and session secrets. Access without enrolled biometrics falls back to server-verified OpFin PIN, never an unverified skip.
- Android native prompt is **SDK 28+**. Older Android users still have the normal PIN entry. This avoids adding a new SDK host, dependency and persistent network traffic to low-cost devices.
- Biometric enrolment information must not be mistaken for a person's lawful identity proof, legal signature or age verification. No institutional admin can enable it on behalf of a member.

## 3. Apple Pay and Google Pay eligibility

As of 24 October 2026, official Apple [Apple Pay market support](https://support.apple.com/en-us/102775) does **not** list Uganda. Official Google [Wallet feature availability for Uganda](https://support.google.com/wallet/answer/12060037?co=ADWORDS.BillingCountryCode%3DUG&hl=en-uk) lists the Wallet app, but **not contactless payments**. Contactless status alone does not certify Google Pay **in-app** processing through any particular acquiring provider. Therefore OpFin has **no live Uganda Apple Pay or Google Pay checkout** and must not use Google Wallet card/pass storage as evidence of payment acceptance.

`apps/client/lib/services/express_wallet_policy.dart` deliberately fails closed in Uganda. In any later eligible country, the UI is allowed to **consider showing** the relevant wallet method only with **all** of:
1. supported customer device, wallet, issuer and market;
2. actually verified provider/acquirer contract, scheme/acquirer approval and merchant configuration;
3. matching country, currency, legal product, seller/payee of record and transaction purpose;
4. a live authoritative checkout quote and a current server-side activation check;
5. separate Apple Pay Merchant ID and entitlement/config where required; Google Pay gateway/merchant/acquirer configuration where required;
6. provider tokenisation and tamper-resistant server processing, distinct provider-confirmed settlement, refunds, reversals, disputes and reconciliation;
7. platform terms and store rules including Apple In-App Purchase / Google Play Billing for digital content/subscription cases where required.

The policy is **display-only, not payment authority**, and its default false Uganda boundary needs an explicit reviewed market change to alter. There is intentionally **no synthetic Apple Pay button, fabricated token, stored raw card data, direct client-to-provider credential or payment-success response**.

### Payment-purpose rules

| Purpose | Appropriate in principle when independently activated | Not appropriate without separate regulated/service rules |
| --- | --- | --- |
| External goods and services / merchant purchase | Tokenised Apple Pay/Google Pay in eligible jurisdictions and schemes | Issuer/merchant/acquirer unsupported, in-app digital subscriptions requiring store billing |
| Fee-bearing financial service, premium, loan repayment | Only if specifically allowed by provider, card network, issuer, country, product and consumer law; charge and fee disclosed | Treating a card checkout authorisation as lender repayment settled, active insurance cover or regulated deposit |
| Loan disbursement | Continue verified destination bank/mobile wallet/provider route | Treat Apple Pay/Google Pay checkout as cash payout account |
| Investment Club/SACCO/savings-group funds | Institution-specific lawful custody/collection and reconciliation first | Apple Pay button implying OpFin itself holds pooled assets or deposits |
| Digital subscriptions/content | Store platform billing and policy-specific requirements | Substituting ordinary wallet checkout for applicable App Store/Play billing |

## 4. Rollout and UAT

**Phase A: Native biometrics (this candidate).** Exact-head iOS/Android compile, method channel registration, enrolled/unenrolled device, system lockout, cancellation, PIN fallback, account switching, logout, privacy strings, no sensitive logs; budget/low-data unchanged.

**Phase B: Sensitive-session hardening.** Re-lock on app resume/background/screenshots where appropriate, reauthentication freshness and server binding for truly sensitive actions; fraud and accessibility tests without forcing biometrics for customers lacking hardware. Native biometric prompt is an optional factor, not the only path.

**Phase C: Wallet acquiring/provider discovery.** Decide acquiring PSP, country/currency, merchant IDs, approved use cases, Apple/Google acceptance contracts and compliant refunds. Verify certificates/tokens/merchant signing with real sandbox provider; do not collect raw PAN.

**Phase D: Checkout integration.** Only when C is accepted, wire wallet method in actual **checkout**, not in payout wallet management, then implement provider token handoff to backend, signed callbacks, idempotent original reference, ambiguous/reversed state, reconciliation, financial disclosures and UAT.

**Phase E: Activate only authorised countries/services.** Explicit feature/config gates; independently approve each institution/product/channel and store listing wording; reconcile processor charges and contribution margin, monitor disputes. Personal financial planning remains available with no wallet providers.

## 5. Cross-model alignment

- **B2C:** customer-controlled opt-in and financial privacy. Mobile money and PIN remain accessible even without biometric hardware.
- **B2B:** institution officers may opt in on their own personal device; never inherit a member's device authentication as an institution approval.
- **B2B2C:** the member's approved Financial Space governs data access; biometrics are supplementary, not a role grant.
- **C2B:** the customer must still consciously accept the specific financial request, provider and terms; native auth is not proof of payment.
- **C2B2B:** each institution-to-institution data/payment hop requires actual mandate, purpose and independent finality; no shared biometric session grants access to either business.

**Do not label Apple Pay or Google Pay 'available' until an approved live payment processor is operating.** The exact user experience and merchant evidence must match the real activation.
