# OpFin

OpFin is the canonical monorepo for the OpFin personal-finance platform. The launch customer experience is intentionally simple even though identity, credit, ledger, reconciliation and provider controls remain sophisticated behind it.

## Launch borrower journey

The supported launch journey is:

`Phone → OTP → names → 6-digit PIN → Home → identity verification → credit profile → available limit → loan request → formal offer → verified-wallet disbursement → repayment`.

Key product rules:

- A second phone is optional.
- KYC captures NIN, National ID front and back, and a photo of the customer holding the ID.
- CRB, MNO, approved third-party and internal behaviour inputs remain separate score components and feed a decomposable OpFin Composite Score. Verified positive employer behaviour may add a small capped benefit; missing or negative employer-behaviour data is neutral.
- The customer sees the composite score, understandable explanations, available loan limit, amount due and next payment date. Internal probability-of-default values remain internal.
- Limits are profile-level, not multiplied by wallets or phone numbers.
- App, WhatsApp and USSD use the same server-authoritative profile and financial state.
- High-impact financial actions require authenticated confirmation; a PIN is never requested in WhatsApp or USSD.
- Launch mobile navigation is `Home | Borrow | Activity | More`; non-launch products remain capability-gated rather than crowding the primary experience.
- Accessibility is part of the core journey: large text, screen readers, reduced motion, simple language, high contrast and assisted identity verification are supported without lowering assurance.
- Financial resilience adds contextual capability guidance, a non-score financial-reputation pathway, optional inclusion measurement, inclusive-finance programmes and alternative credit-support evidence without converting demographic attributes into underwriting inputs.

See `docs/LAUNCH_CUSTOMER_JOURNEY.md` for the complete cross-channel contract and `docs/UMRA_DIGITAL_LENDING_CONTROLS.md` for the implemented digital-lending compliance controls.

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
- Cito is the preferred third-party integration gateway and CPay is the preferred production money-movement route. OpFin remains independently operable; a direct production adapter is allowed only when explicitly configured, genuinely contracted/certified and reconcilable.
- Provider acknowledgement is not financial finality.
- External scoring/KYC sources may be unavailable; OpFin records that state instead of inventing data.
- KYC evidence belongs on private persistent/object storage in production.
- Store-distributed personal-loan terms retain the repository's 61-day minimum full-repayment rule and preference for eligible 90-day-plus routes.
- Credit-information reporting, complaint SLA, NPL/default-interest caps, transaction receipts, guarantor confirmation and governed term changes are controlled/auditable backend responsibilities.

Read `SECURITY.md`, `AGENTS.md` and `apps/api/docs/README.md` before changing authentication, KYC, credit, money movement or customer-facing financial state.

## Local verification

Run the affected project gates or the aggregate suite:

`make api-test`, `make web-test`, `make client-test` or `make test`.

The exact release commit must also pass the repository release gate, security gate and deployment contract. A passing build is not proof that provider credentials, store publication, real-device accessibility or production operations are activated.


## Documentation and developer discovery

Start at `docs/README.md`.

Useful commands:

```bash
python3 scripts/search-docs.py "credit reporting"
python3 scripts/search-docs.py "complaint" --api
python3 scripts/search-api.py "umra"
python3 scripts/search-api.py "receipts"
```

Training manuals and user guides should be derived from `docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md` plus the current application labels and API contracts.

CI checks documentation drift when backend routes/contracts or customer/admin workflows change.


## Current product documentation (21 September 2026)

- [Canonical Product Blueprint](docs/product/OPFIN_PRODUCT_BLUEPRINT.md)
- [Financial Spaces domain model](docs/architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md)
- [Inclusive Finance and Programme Delivery Framework](docs/product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md)
- [Partner Financial, Compliance and Service Reporting Standard](docs/product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md)
- [User Manual](docs/manuals/OPFIN_USER_MANUAL.md)
- [Training Manual](docs/manuals/OPFIN_TRAINING_MANUAL.md)
- [Operational Manual](docs/manuals/OPFIN_OPERATIONAL_MANUAL.md)
- [UAT Manual](docs/manuals/OPFIN_UAT_MANUAL.md)
- [Current API endpoints](apps/api/docs/api/current-endpoints.md)
- [API quick reference](apps/api/docs/api/API_QUICK_REFERENCE.md)

These documents describe the current Financial Spaces and inclusive-finance architecture and supersede April-era prompt packs/architecture drafts for operational and training use. Stolets remains a separate SME automation/digitisation product; any cross-product use is an explicit, consented integration rather than a merged product boundary.
