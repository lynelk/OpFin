# OpFin web application

Status: Controlled external developer/product reference  
Updated: 24 September 2026  
Language: English (United Kingdom)

The Next.js application provides the public marketing site, customer Web access, institutional Workspaces and authorised operational/admin surfaces. It consumes `apps/api`; it does not own authoritative credit calculations, balances, financial finality, ledger state, provider routing or regulatory truth.

## Website role

The homepage communicates the broad OpFin proposition:

- individuals;
- savings groups;
- businesses/employers;
- SACCOs and partners;
- responsible credit;
- inclusive-finance programmes and partner reporting.

Public copy must distinguish implemented capability from activated provider service. Illustrative product cards are not production screenshots. Programme outcomes are not causal claims. Web sign-in is not the preferred new-customer onboarding route.

Canonical new-customer onboarding remains phone → OTP → names → six-digit PIN in the mobile experience. Web sign-in is PIN-first for existing/authorised users and account-deletion verification, while retaining the existing legacy-password fallback during migration. The interface labels the credential field clearly without forcing six-digit validation on legacy Web accounts.

### Portal access model

Web uses one sign-in surface and routes the authenticated person to the correct authorised workspace:

- **Personal & Financial Spaces** for customer money, household/group and authorised organisation/SACCO contexts;
- **Employer Workspace** for `employer_admin` access;
- **Programme Partner Workspace** for `programme_partner` aggregate programme reporting; and
- **OpFin Operations** for `platform_admin`, `operations` and `support`, with modules filtered by role.

The legacy `/admin-login` URL is a compatibility redirect to the unified sign-in screen. Programme check-ins remain inside the Personal experience. Support, inclusion/programmes, impact, programme delivery and commercial performance are modules inside role-gated Operations rather than separate authentication portals.

## Customer and Workspace experience

Server-authoritative Financial Space context is shared across clients. Web enhances analysis and institutional operations; it must not become a hidden prerequisite for essential Individual or Savings Group journeys.

Current web surfaces include financial management, credit/customer state, programme delivery, inclusive-finance/impact operations, partner access, commercial performance, governance/compliance and operational administration.

Provider-gated savings, investment, protection and other regulated products must not be presented as live solely because source code exists.

## Authority rules

- monetary values remain integer minor units;
- do not reproduce backend pricing, interest, fee, repayment-allocation or credit-limit formulas in TypeScript;
- display offer disclosures as returned by the API;
- pending provider requests are not completed financial events;
- programme measurement/protected attributes remain outside underwriting;
- commercial economics remain downstream of customer need/eligibility/suitability;
- production must not use mock API behaviour or demo shortcuts;
- provider secrets never belong in browser-visible configuration.

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

## Production topology

The current Railway Web setup address is `https://opfin-web-production.up.railway.app`, consuming `https://opfin-production.up.railway.app/api`.

Generated Railway domains are operational setup endpoints, not proof of custom-domain cutover. At the reviewed 23 September `main` head, Railway commit statuses report successful web/API/worker/scheduler deployments, while no GitHub Actions workflow run exists for that exact head. Do not collapse those two evidence states.

## Quality gates

```bash
npm run audit
npm run typecheck
npm run lint
npm run test
npm run build
```

A release also needs the repository release/security/deployment gates and any required provider/physical-device acceptance.

## Documentation

Start with:

- `../../docs/CURRENT_STATE.md`
- `../../docs/product/OPFIN_PRODUCT_BLUEPRINT.md`
- `../../docs/manuals/OPFIN_USER_MANUAL.md`
- `../../docs/manuals/OPFIN_OPERATIONAL_MANUAL.md`
- `../api/docs/api/API_QUICK_REFERENCE.md`

When a Web workflow or public claim changes, update the relevant current documentation in the same PR.


## Location context

The Web Workspace displays authorised Financial Space location context with lightweight server-fetched Static Map previews when Google Maps is activated.

Operations has an aggregate **Location insights** view for Financial Space and partner-service-point coverage. Rows below five are suppressed and individual customer locations are excluded.

Programme partner users have a **Service network** view limited to service points associated with their own institution's partner records.

The Web app does not receive the Google server API key and does not perform background/customer location tracking.
