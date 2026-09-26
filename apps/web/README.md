# OpFin web application

Status: Current developer and product reference  
Reviewed: 24 September 2026  
Language: English (United Kingdom)

The Next.js application provides marketing, customer Web, institutional Workspaces and role-gated operations. It consumes `apps/api`; it does not own authoritative credit calculations, balances, provider finality, accounting or regulatory state.

## Product and access model

The site describes individuals, savings groups, employers/businesses, SACCOs, partners, responsible credit and inclusive-finance programmes. Keep implemented capabilities distinct from activated services and illustrative previews distinct from real production evidence.

New App registration is phone → OTP → names → six-digit PIN. Web sign-in is PIN-first for existing/authorised users and deletion verification while retaining legacy-password compatibility. Do not force a six-digit constraint on a migrated password account merely to match the label.

One sign-in surface routes authorised users to Personal & Financial Spaces, Employer Workspace (`employer_admin`), Programme Partner Workspace (`programme_partner`) or OpFin Operations (`platform_admin`, `operations`, `support`). `/admin-login` remains a compatibility redirect, not an independent identity system.

Operations navigation groups Overview & automation, Finance & risk, Customer service, Governance & assurance and Programmes & growth. Explicit module role allow-lists apply; support must not inherit every admin route. Programme check-ins remain part of the personal experience, while programme operations and commercial reporting remain authorised modules.

## Current capabilities and limitations

Financial management, credit, programmes, impact, partner access, commercial performance, governance and compliance consume the same server-authoritative domain. Treasury adds detailed CSV import/reconciliation and statement workflows on Web; App statement access does not demonstrate complete mobile administration.

Essentials adds named-lender bill/rent orchestration and connected-platform permissions. Its current internal accounting, authorisation, deletion, concurrency and reservation findings remain acceptance blockers. A Web surface is not approval to activate financing. See the [current capability contracts](../api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) and [delivery evidence](../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md).

Optional Location Context uses lightweight authenticated server-fetched maps where configured. Operations geography suppresses small rows and excludes individual customer locations. Programme partners see their own permitted service network. Provider keys stay server-side; no background customer tracking is implied.

## Server authority and privacy

Use integer minor-unit amounts and API-supplied disclosures. Do not reproduce pricing, allocation, headroom or eligibility formulae in TypeScript. Pending funding, fulfilment, reversal or repayment is not final movement. Keep programme/protected attributes outside underwriting and commercial incentives downstream of customer need/suitability.

Production mock/demo shortcuts remain disabled. Provider credentials and database secrets never belong in browser-visible settings. Maintain secure cookies, nonce/CSP controls, role routing and safe internal redirects.

## Local setup

From `apps/web`:

```bash
npm ci --legacy-peer-deps
test -f .env.local || cp .env.example .env.local
npm run dev
```

Use `NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api`, `NEXT_PUBLIC_USE_MOCK_API=false` and `OPFIN_ENABLE_DEMO_SHORTCUTS=false` for the local API. Browser origins and CORS must match. A public build variable is not a place for a secret.

## Login build correction

The reviewed production build failed after compilation because the conditional login-error query object inferred an optional `context: undefined`, incompatible with `Record<string, string>`.

The accompanying correction adds an explicit `Record<string, string>` annotation to that existing object. It changes no runtime values, credential handling, cookie flags, role checks or redirect targets. An isolated TypeScript 5.8.3 strict check reproduced TS2345 before the annotation and passed afterwards. This is targeted evidence, not a claim that the repository's full dependency-specific Web build has passed. Record the actual production build result separately.

## Quality and deployment

```bash
npm run audit
npm run typecheck
npm run lint
npm run test
npm run build
```

GitHub Actions remains disabled at the owner's direction; equivalent candidate-specific build/security/operations evidence is still required. Do not disable type checking to resolve a build error.

Existing setup addresses are `https://opfin-web-production.up.railway.app` and `https://opfin-production.up.railway.app/api`. They do not prove custom-domain cutover or health. The [dated release evidence](../../docs/operations/DELIVERY_EVIDENCE_2026-09-24.md) records the previous failed API/Web attempts; verify running-version parity and supported API contracts before declaring production aligned.

Read [current state](../../docs/CURRENT_STATE.md), the [Blueprint](../../docs/product/OPFIN_PRODUCT_BLUEPRINT.md), [manuals](../../docs/manuals/OPFIN_USER_MANUAL.md), [operations](../../docs/manuals/OPFIN_OPERATIONAL_MANUAL.md) and [API index](../api/docs/README.md). Update affected current docs in the same PR as a Web workflow or public-claim change.

## Platform lending administration

`/admin/lending-platform` manages actual lenders, products/terms, affiliated-credit deployment, scoped distribution revisions and delegated operations access. It calls authenticated Laravel endpoints with no mock fallback and no cached configuration. Core Synergies uses an internal lender record under platform admin; no separate partner login is required. Offers show the snapshotted actual lender. See [the operating contract](../../docs/architecture/LENDER_ORCHESTRATION.md).

Local lint uses the TypeScript 6 compatibility procedure already present in `.github/workflows/ci.yml`; restore lockfile dependencies before the normal TypeScript/build gates. Do not disable lint rules to hide compatibility errors.
