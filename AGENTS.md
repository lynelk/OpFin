# OpFin monorepo engineering rules

## Boundaries

- `apps/api` owns identity, consent, eligibility, credit profiles, financial decisions, obligations, ledger postings, provider finality and reconciliation.
- `apps/web` and `apps/client` consume authenticated API contracts and never connect directly to PostgreSQL.
- CPay remains the only production money-movement adapter unless an explicitly approved architecture change says otherwise.
- A provider acknowledgement is not accounting finality.
- Secrets remain service-scoped and are never exposed to web or client builds.
- External KYC, CRB, MNO and third-party scoring results must be attributable to their source. Missing provider data must never be replaced with invented scores.

## Required verification

- API changes: formatting, tests, dependency audit, PostgreSQL/migration evidence where applicable.
- Web changes: dependency audit, typecheck, lint, tests and production build.
- Client changes: Flutter analyse/tests plus Android and iOS release compile gates.
- Shared financial/API changes: run all affected jobs and end-to-end contract tests.
- Customer-journey changes: verify App, WhatsApp and USSD use the same server-authoritative customer state and do not create conflicting financial logic.
- Accessibility changes: check large text, screen-reader semantics, reduced motion, focus order and touch-target usability on real supported devices before general availability.

## Launch customer-experience rules

- Keep the public launch navigation focused on `Home | Borrow | Activity | More`.
- Home must prioritise the customer's current state: setup task, amount due, available-to-borrow amount and next action.
- The sign-up sequence is `Phone → OTP → First/Other/Last names → 6-digit PIN → authenticated Home`. Do not reintroduce a mandatory long password for new mobile customers.
- A second phone is optional. It may improve profile confidence or add a wallet, but it must not be a condition for baseline scoring.
- KYC requires NIN, National ID front, National ID back and a photo of the customer holding the ID. Provider-backed NIN, liveness, face-match and NIN/phone-link checks must be recorded explicitly; failed or unavailable checks go to review rather than being treated as verified.
- Customer-facing scoring shows the OpFin Composite Score, band, available limit and plain-language explanations. Probability-of-default and other internal risk metrics are not customer UI.
- The composite score must remain decomposable into source components for authorised explanation and audit.
- Credit limit is profile-level. Adding phones or wallets must never multiply exposure.
- Money movement uses verified wallets. Pending provider requests must never be described as completed payments or disbursements.
- WhatsApp and USSD may initiate or explain journeys, but high-impact commitments use authenticated step-up. Never request a customer's OpFin PIN in WhatsApp or USSD.
- Hide provider-gated or regulator-gated products from primary launch navigation until genuinely activated. Architecture may remain ready behind feature/capability gates.
- Account deletion remains available in-app and preserves only legally required records.

## Inclusive-design rules

- Sophisticated scoring and compliance logic belongs behind the interface. Each customer screen should normally ask for one clear action.
- Use plain language, short instructions, icon plus text, meaningful error recovery and resumable journeys.
- Never rely on colour alone to communicate status.
- Respect operating-system text scaling, VoiceOver/TalkBack semantics and reduced-motion preferences.
- Interactive controls should meet the configured minimum touch target of 48 logical pixels unless a platform control provides an equivalent accessible target.
- Customers with disabilities may use assisted identity verification. A trusted helper may help position a device or enter non-secret information, but PINs and OTPs remain private to the customer.
- Accessibility assistance must not weaken KYC, consent, credit or financial-control standards and must not create a separate lower-assurance account type.
- Never advertise a provider-gated or regulator-gated capability as live.
