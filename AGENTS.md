# OpFin monorepo engineering rules

Status: Controlled internal engineering standard  
Updated: 23 September 2026  
Language: English (United Kingdom)

## Boundaries

- `apps/api` owns identity, consent, eligibility, credit profiles, financial decisions, obligations, ledger postings, provider finality and reconciliation.
- `apps/web` and `apps/client` consume authenticated API contracts and never connect directly to PostgreSQL.
- CPay is the preferred production money-movement adapter. OpFin must remain independently operable and may use an explicitly configured, production-certified direct provider adapter as a controlled fallback. Never enable a direct provider without a real contract, credentials, certification and reconciliation path.\n- All OpFin access to gnuGrid services must route through Cito. A generic direct-provider fallback rule does not authorise direct gnuGrid access.
- A provider acknowledgement is not accounting finality.
- Secrets remain service-scoped and are never exposed to web or client builds.
- External KYC, CRB, MNO and third-party scoring results must be attributable to their source. Missing provider data must never be replaced with invented scores.

## Required verification

- GitHub Actions is the normal repository verification gate. Keep the configured workflows enabled; do not use `[skip ci]`, `[ci skip]` or equivalent bypasses unless the workspace owner explicitly suspends Actions again for a defined reason and period.
- API changes: formatting, tests, dependency audit, PostgreSQL/migration evidence where applicable.
- Web changes: dependency audit, typecheck, lint, tests and production build.
- Client changes: Flutter analyse/tests plus Android and iOS release compile gates.
- Shared financial/API changes: run all affected jobs and end-to-end contract tests.
- Customer-journey changes: verify App, WhatsApp and USSD use the same server-authoritative customer state and do not create conflicting financial logic.
- Accessibility changes: check large text, screen-reader semantics, reduced motion, focus order and touch-target usability on real supported devices before general availability.

## Launch customer-experience rules

- Migrate the universal financing entry from `Borrow` to `Finance` as part of FIN-010. Until App/Web/API compatibility is proven, preserve existing Borrow routes as compatibility paths rather than breaking current conventional-credit journeys. The target primary navigation is `Home | Finance | Activity | More`.
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


## Documentation discipline

Documentation is part of the implementation, not a post-release chore.

- Current documentation starts at `docs/README.md`.
- Every public/API/customer/admin workflow change must update the relevant current docs in the same PR.
- Route changes must update `apps/api/docs/api/current-endpoints.md`.
- Client contract changes must update `apps/api/docs/api/frontend-backend-contract.md` and the affected component README/journey guide.
- New operational/regulatory controls must update the operational runbook, readiness/UAT evidence and `docs/UMRA_DIGITAL_LENDING_CONTROLS.md` where applicable.
- Use `python3 scripts/search-docs.py "<term>"` and `python3 scripts/search-api.py "<term>"` for discovery.
- Dated audit/demo/checkpoint files remain historical evidence and do not override current docs.
- CI runs `scripts/verify-documentation-drift.py`; do not bypass it by weakening the check.
- Training manuals, staff guides and customer help content should be derived from `docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md` plus current application labels/contracts.

## Railway infrastructure consent

Railway infrastructure is controlled by `ops/railway/topology-policy.json`. Do **not** create a new Railway project, environment, service, database, volume, bucket, persistent validation workload, or increase replica counts unless the workspace owner has explicitly approved that specific infrastructure change. A request to build, test, validate, fix, deploy, synchronise or release software is not infrastructure-creation consent.

Use the existing approved services first. Run build, browser, migration, compatibility and release validation in ephemeral CI unless the owner has explicitly authorised a persistent Railway resource. Never use Railway Agent to work around this rule. Keep the workspace Railway Agent hard limit at USD 0 and respect the current compute hard limit. If an approved topology change is required, update the allow-list, cost impact and governance documentation in the same reviewed change before provisioning it.


## Integrated financing and delivery controls — 26 September 2026

- Read `docs/development/OPFIN_INTEGRATED_DELIVERY_PLAN_2026-09-26.md` before implementing financing, Essentials, Capital/assets, Islamic finance, Participatory Finance, protection/investment, legal/contract intelligence or public-site changes.
- Link material work to `docs/governance/OPFIN_DELIVERY_FEATURE_REGISTER.md` and the canonical `docs/product/IMPLEMENTATION_BACKLOG.md`.
- Treat `FinancialProduct` and `FinancingArrangement` as the target financing abstractions. Preserve legacy credit/loan contracts through compatibility adapters until migration acceptance proves they can be retired.
- Conventional and Islamic rails reuse Identity, KYC, Consent, Financial Passport, affordability, risk, payments, servicing, support and reporting, while contract, pricing/profit, funding, accounting, delinquency, disclosure and governance remain rail-aware.
- Never infer religion. A financial-principles preference is a product preference, not a religious identity field.
- Islamic products must not call conventional interest-pricing logic. Sharia status requires approved governance and cannot be created or overridden by AI, code flags or administrators.
- Every regulated product requires a valid Legal Product Passport and all required transaction-specific consents/mandates before activation.
- State-changing financial commands require idempotency, correlation and audit context. Reversals use compensating financial entries; never destructively rewrite posted financial history.
- The OpFin ledger remains the financial source of truth. Blockchain proofs, tokenisation or digital-asset rails never replace accounting entries and must remain optional/fail-closed until separately approved.
- Essentials is a bills-management/payment capability with optional responsible partner finance. It must remain useful without borrowing and must account for recurring future bills when assessing affordability.
- Durable assets belong to the shared Capital/Asset Registry architecture rather than Essentials.
- Remote device controls, public tokenisation, secondary transfer, stablecoin/virtual-asset settlement and crypto-backed finance remain disabled until their specific legal, security, finance and operational gates are approved.
- The public marketing site has one canonical source under `sites/opfin-public/` once host/source compatibility is proven. Do not create or maintain a second independently authored marketing website.
- A source merge, deployment, configured provider, saved Site version or successful HTTP response is never by itself production acceptance.
