# OpFin Frontend

OpFin-FE contains the production web portal and mobile application for OpFin:

- repository root `src/`: Next.js 16 + TypeScript customer, operations and administration web experience;
- `opfin-frontend/`: Flutter mobile application with persistent offline-aware sync support.

The frontend is a client of the Laravel OpFin backend. It does not own financial pricing, balances, repayment allocation, ledger accounting, provider finality or other authoritative money calculations. Those remain backend responsibilities. Human beings have already invented enough ways for two screens to disagree about money without adding duplicated financial formulas.

## Product navigation

The customer information architecture is:

```text
Home | Borrow | Save | Grow | More
```

The current experience includes identity and activation, KYC/consent, borrowing and loan servicing, savings, protection, investments/growth surfaces, financial wellbeing, security/support, linked accounts, household and microbusiness finance, community finance, asset finance, participatory finance and other long-range platform capabilities where their backend contracts are active.

Some capabilities remain externally gated by genuine provider credentials, licensed products, contracts, mandates or regulatory approvals. The UI must represent those states truthfully rather than substituting fixtures or simulated success in production.

## Financial client rules

- Monetary API values use integer minor units. For current UGX production configuration, the exponent is `0`.
- Do not reproduce credit pricing, fee, repayment-allocation, savings-balance, P2P funding or asset-finance formulas in browser code when the backend provides the authoritative result.
- Display backend disclosures and immutable offer values exactly as returned.
- Never infer provider success from a submitted request. Processing, successful, failed, reversed and reconciled states are distinct.
- Financial actions requiring step-up must follow the server-side OTP verification flow. Verification tokens remain server-side and are not exposed to browser JavaScript.
- Client-generated idempotency keys must be stable for an intentional replay and new for a different money instruction.
- Production builds must not enable mock API behavior or demo shortcuts.

## Production financial handoff

High-risk long-range financial actions use a server-action handoff:

```text
customer creates approved source instruction
→ frontend creates backend financial intent with idempotency key
→ server requests OTP for the registered customer phone
→ customer confirms OTP
→ server obtains short-lived verification token
→ verification token remains server-side
→ backend confirms the financial intent
→ backend calls CPay
→ UI follows backend/provider state without inventing settlement
```

Examples include approved asset-finance deposits and participatory-finance commitments.

## Next.js setup

```bash
npm ci
cp .env.example .env.local
npm run dev
```

Environment:

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

Only browser-safe values may use the `NEXT_PUBLIC_` prefix. CPay credentials, OTP/SMS secrets, CRB credentials, private keys, callback secrets and all other provider secrets belong exclusively in backend services.

## Production deployment boundary

The canonical Railway frontend service is `opfin-web`, built from the repository root. Production requires:

```text
NODE_ENV=production
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
NEXT_PUBLIC_OPFIN_API_URL=<production OpFin API /api base>
```

The web service is stateless and must not be used as a storage location for customer financial records, provider secrets or authoritative transaction state.

## Quality gates

Run the full web checks before merge:

```bash
npm ci
npm run typecheck
npm run lint
npm run test
npm run build
npm run check
```

Flutter changes must additionally pass:

```bash
cd opfin-frontend
flutter pub get
flutter analyze
flutter test
flutter build apk --release
```

Production CI also contains guards against mock/demo behavior leaking into builds.

## Web application areas

The route set evolves with the platform, but the current production surface includes customer and operations areas such as:

- authentication and activation;
- home/dashboard and financial wellbeing;
- KYC and consent;
- borrowing, decision/offer review, loan account and repayment schedule;
- savings and protection;
- growth/investment surfaces;
- security and support;
- employer/community capabilities;
- linked accounts;
- household and microbusiness finance;
- asset finance;
- participatory-finance marketplace, commitments and step-up confirmation;
- operations/admin queues, credit review, audit and governance workflows.

Do not maintain a hand-written route list here as if it were a router. The application source is authoritative for exact paths; this README documents product boundaries and implementation rules.

## Mobile and offline-aware behavior

The Flutter application includes an offline sync service for permitted offline-aware events. Offline batches use stable batch references and backend idempotency/conflict detection. Offline mode does **not** authorize offline money movement or permit the app to manufacture provider finality.

Conflicts and rejected sync batches must remain visible for review rather than being silently overwritten.

## Demo and mock behavior

Demo endpoints and sandbox fixtures still exist for isolated development, demonstration and regression testing where explicitly labelled. They are not production financial rails.

Production must keep:

```text
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

No UI should claim that a live KYC, CRB, savings, protection, investment, employer, CPay or other external integration is active solely because a mock fixture can render the screen.

## Backend contract

The backend is authoritative for:

- authentication and authorization;
- customer/product state;
- financial formulas and pricing snapshots;
- affordability policy outcomes;
- offer disclosures;
- money-movement and reconciliation state;
- loan/savings/protection obligations;
- financial ledgers and audit evidence;
- external-integration readiness.

When frontend behavior and backend financial state disagree, fix the contract or the client. Never compensate with a second independent financial calculation in the UI.

For detailed API, CPay, accounting and production controls, consult the current documentation in `lynelk/OpFin-BE`, especially its root `README.md`, `AGENTS.md`, `docs/README.md`, `docs/integrations/mobile-money.md` and `docs/production/financial-controls-review.md`.
