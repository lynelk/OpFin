# OpFin

OpFin is the canonical monorepo for the OpFin personal-finance platform. The launch customer experience is intentionally simple even though identity, credit, ledger, reconciliation and provider controls remain sophisticated behind it.

## Launch borrower journey

The supported launch journey is:

`Phone → OTP → names → 6-digit PIN → Home → identity verification → credit profile → available limit → loan request → formal offer → verified-wallet disbursement → repayment`.

Key product rules:

- A second phone is optional.
- KYC captures NIN, National ID front and back, and a photo of the customer holding the ID.
- CRB, MNO, approved third-party and internal behaviour inputs remain separate score components and feed a decomposable OpFin Composite Score.
- The customer sees the composite score, understandable explanations, available loan limit, amount due and next payment date. Internal probability-of-default values remain internal.
- Limits are profile-level, not multiplied by wallets or phone numbers.
- Positive/negative credit performance is staged through the consent-bound outbound reporting register; missing consent/provider configuration fails closed.
- Provider-confirmed disbursements and repayments generate immutable e-receipts.
- UMRA NPL recovery ceilings, complaint SLAs, guarantor confirmations and governed term variations are first-class controls.
- App, WhatsApp and USSD use the same server-authoritative profile and financial state.
- High-impact financial actions require authenticated confirmation; a PIN is never requested in WhatsApp or USSD.
- Launch mobile navigation is `Home | Borrow | Activity | More`; non-launch products remain capability-gated rather than crowding the primary experience.
- Accessibility is part of the core journey: large text, screen readers, reduced motion, simple language and assisted identity verification are supported without lowering assurance.

See `docs/LAUNCH_CUSTOMER_JOURNEY.md` for the complete cross-channel contract and `docs/UMRA_DIGITAL_LENDING_COMPLIANCE_MATRIX.md` for the January 2024 UMRA control mapping.

## Layout

- `apps/api`: Laravel API, queue worker, scheduler and financial-domain source.
- `apps/web`: Next.js web/customer and operational experience.
- `apps/client`: Flutter Android/iOS client.
- `packages/contracts`: shared API-contract home.
- `infrastructure/railway`: Railway service-boundary documentation.
- `docs`: current cross-platform launch, architecture, security and migration documentation.

The historical source imports remain in Git history. This repository is the current working source of truth.

## Financial and security boundaries

- `apps/api` owns identity, consent, eligibility, decisioning, obligations, provider finality, ledger posting and reconciliation.
- CPay is the production money-movement boundary unless deliberately changed and revalidated.
- Provider acknowledgement is not financial finality.
- External scoring/KYC sources may be unavailable; OpFin records that state instead of inventing data.
- KYC evidence belongs on private persistent/object storage in production.
- Store-distributed personal-loan terms retain the repository's 61-day minimum full-repayment rule and preference for eligible 90-day-plus routes.

Read `SECURITY.md`, `AGENTS.md` and `apps/api/docs/README.md` before changing authentication, KYC, credit, money movement or customer-facing financial state.

## Local verification

Run the affected project gates or the aggregate suite:

`make api-test`, `make web-test`, `make client-test` or `make test`.

The exact release commit must also pass the repository release gate, security gate and deployment contract. A passing build is not proof that provider credentials, store publication, real-device accessibility or production operations are activated.
