# Testing strategy

The launch release uses layered tests because financial correctness cannot be established by a single happy-path UI test.

## 1. API feature tests

Cover:

- phone OTP and verification-token lifecycle;
- phone-first names + six-digit PIN registration;
- legacy password compatibility during migration;
- weak PIN and brute-force controls;
- KYC requirement for ID front/back/selfie;
- consent grant/revoke;
- optional second phone;
- profile component/coverage/limit computation;
- amount-above-limit rejection;
- application decision/offer state;
- disclosure-hash acceptance;
- verified-wallet ownership;
- idempotent repayment;
- provider finality/reconciliation/reversal;
- WhatsApp signature/session/media KYC;
- USSD state/menu and callback authentication;
- support/assisted identity case.

## 2. Flutter tests

Cover:

- short skippable onboarding;
- phone/OTP/PIN navigation;
- profile state rendering;
- amount due vs available-limit priority;
- limit-aware application form;
- pending-repayment wording;
- large-text layout and semantics for critical controls.

Compile Android/iOS release targets on the exact candidate.

## 3. Accessibility and low-literacy UAT

Automated semantics checks are not enough. On real devices test:

- Android TalkBack and iOS VoiceOver;
- 125%+ and OS maximum practical text size;
- reduced motion;
- one-handed/large tap targets;
- customer who reads slowly or needs a helper;
- PWD-assisted KYC request;
- no secret-sharing required for assistance;
- plain-language error recovery.

## 4. Cross-channel behavioural tests

The same seeded customer must get consistent score/limit/due state from App/API, WhatsApp LIMIT/PROFILE and USSD My limit/My loan.

WhatsApp/USSD must never create an alternative financial ledger or decision engine.

## 5. Production-readiness tests

Before release:

- migrations on PostgreSQL;
- private KYC object-storage write/read and access controls;
- configured identity/CRB/MNO/third-party adapters;
- payment-provider sandbox/certification flow;
- signed webhook tests;
- backup restore;
- exact release CI/security/deployment gates;
- signed candidate on physical Android devices;
- no broad SMS/media permissions introduced.

Real financial tests require authorised test accounts and amounts. Do not create unauthorised customer obligations to prove a release.


## 6. UMRA/regulatory regression

`UmraDigitalLendingControlsTest` and related feature tests cover:

- 30-day complaint resolution clock;
- maximum two guarantors and independent confirmation state;
- NPL/default-interest cap calculation and enforcement;
- blocked direct interest-rate changes;
- maker-checker rate change with recorded prior UMRA approval;
- idempotent transaction receipt generation;
- credit-reporting consent gate and outbound submission;
- generation/validation of UMRA books-and-records evidence packs.

Additional release/UAT must verify real provider schema/certification, official complaint contacts and actual regulatory approval evidence; tests deliberately do not manufacture those external facts.
