# OpFin web application

Updated: 21 September 2026

The Next.js web application provides customer and operational/admin experiences for the OpFin monorepo. It is a client of `apps/api`; it does not own authoritative credit calculations, balances, ledger state, provider finality or regulatory reporting logic.

## Customer experience

The launch customer information architecture mirrors mobile intent:

**Home | Borrow | Activity | More**

The web experience supports account/profile, KYC/consent, borrower state, loan application/offer/account flows, support, receipts and selected financial-wellbeing capabilities.

Savings, investments, insurance, participatory finance, SACCO/community capital and other long-range capabilities remain capability/provider gated and must not be presented as live merely because code exists.

## Admin and operations

Current operational surfaces include:

- credit review;
- reconciliation and immutable ledger review;
- customer support/complaints;
- governance and regulatory reports;
- UMRA compliance controls and evidence packs;
- security/audit and platform operations.

The Compliance Centre surfaces credit-reporting queues, complaint SLA state, NPL/default-interest controls, receipts, guarantor confirmations and governed term changes.

## Frontend authority rules

- Monetary API values are integer minor units.
- Do not reproduce backend pricing, interest, fee, repayment-allocation or credit-limit formulas in TypeScript.
- Display immutable offer disclosures exactly as returned.
- Pending provider requests are not completed financial events.
- High-impact actions keep verification material on the server side.
- Production must not use mock API behaviour or demo shortcuts.

## Setup

```bash
npm ci --legacy-peer-deps
cp .env.example .env.local
npm run dev
```

Typical local values:

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

Only browser-safe values may use `NEXT_PUBLIC_`. Provider credentials belong to the API/backend.

## Quality gates

```bash
npm run typecheck
npm run lint
npm run test
npm run build
```

## API discovery

Use the canonical API documentation, not an old hand-written frontend route list:

```bash
python3 scripts/search-api.py "credit"
python3 scripts/search-docs.py "credit offer" --api
```

Start with `../api/docs/api/API_QUICK_REFERENCE.md` through `apps/api/docs/api/API_QUICK_REFERENCE.md`, then `apps/api/docs/api/current-endpoints.md`.

## Documentation rule

When a web screen changes a customer/admin workflow, update the relevant web/current documentation in the same PR. Historical demo/audit documents remain evidence of an earlier state and do not override current contracts.


## 20 September 2026 product-surface update

Financial Spaces are now part of the canonical OpFin experience. One person may access Personal, Household, Savings Group and authorised organisation contexts without creating separate identities. Individuals and Savings Groups remain mobile-complete; Web provides enhanced analysis and institutional workspace capabilities. The customer proposition is to understand, manage, plan and improve money, with borrowing as one capability rather than the product boundary.

## Inclusive-finance operations — 21 September 2026

The **Inclusion & programmes** operations surface reports configured inclusive-finance programmes, enrolments, credit outcomes, NPL state, capability/programme events and consented cohorts. Cohorts smaller than five are suppressed. Voluntary programme-measurement attributes remain outside credit-risk decisioning.

Stolets remains a separate product. This dashboard does not make Stolets data part of OpFin; any future cross-product signal must arrive through an explicit, consented and governed provider contract.

The Inclusion & programmes surface also provides programme creation with explicit participation rules for age cohort, gender, disability inclusion, refugee/displacement status, geography category, employment category, first-time formal borrower status and KYC state. These rules govern programme participation only and are not written into the credit-scoring model.

## Impact framework and programme-partner portal

Platform operators now have an **Impact framework** workspace under Inclusion & programmes. It supports:

- versioned programme theories of change;
- reusable impact indicator definitions;
- outcome domains, units, methodologies, sources and verification requirements;
- programme targets/reporting frequencies;
- privacy-safe outcome summaries;
- explicit programme-partner access grants.

A dedicated `programme_partner` role has a separate **Programme impact** navigation group. Partner accounts:

- see only programmes explicitly granted to them;
- receive aggregate delivery and outcome evidence;
- never receive individual participant records from the partner surface;
- cannot alter credit policy through the impact workspace.

The operator must configure a programme's partner before granting access, and the target account must already be a dedicated programme-partner identity. Do not repurpose an ordinary customer's account as a partner login.
