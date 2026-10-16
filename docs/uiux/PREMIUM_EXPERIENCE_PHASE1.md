# OpFin premium UI/UX transformation: Phase 1 audit and delivery record

Status: **Implementation candidate; not release-accepted**
Date: 16 October 2026
Canonical repository: `lynelk/OpFin`
Scope: `apps/web` customer Home and authenticated portal shell. Flutter/mobile native work is a separate acceptance track.

## 1. Source baseline and design direction

Reviewed: `README.md`, `brand/opfin.tokens.json`, `brand/v3/OPFIN_BRAND_SYSTEM_V3.md`, `docs/CURRENT_STATE.md`, `apps/web/src/app/(portal)/dashboard/page.tsx`, `apps/web/src/components/AppShell.tsx`, `apps/web/src/components/Screen.tsx`, `apps/web/src/lib/navigation.ts`, `apps/web/src/app/globals.css`, `apps/web/src/app/opfin-brand.css`, `apps/web/src/app/experience.css`, `apps/web/docs/frontend/screen-map.md` and representative customer Borrow screens.

The product is **OpFin**. Follow the approved v3 system: Indigo `#353B78`, Strong Indigo `#29305F`, Apricot `#F2B38D`, Warm Ivory `#FAF8F2`, Periwinkle `#E8EAF6`, Ink `#202436`, Inter, 8px rhythm, 12px primary radii and 48px minimum customer controls. Reuse the supplied symbol instead of inventing icons or new branding.

The design must express **personal-first financial wellbeing**, with Financial Spaces, the evidence-aware Financial Compass and one explicit Next Step. Borrowing is one service among many; do not organise Home exclusively around loans. Preserve distinction between recorded facts, estimates, pending transactions, unavailable services and external confirmation.

## 2. Prioritised findings (source inspection, not user-study findings)

| Priority | Finding | Evidence | Action / acceptance |
| --- | --- | --- | --- |
| P0 | Mobile portal sidebar expands above the entire page at widths <=900px; long operations menus can dominate first interaction. | `globals.css` media rule and `AppShell.tsx` uncollapsed role nav. | Responsive disclosure with accurate `aria-expanded`, desktop navigation unaffected, screen-specific links unchanged. |
| P0 | Navigation links did not identify the active route. | `AppShell.tsx` renders all links with identical `nav-link` class. | `aria-current="page"` and visible selected state, including nested routes. |
| P0 | Financial information may be mistaken for verified external payment confirmation; the current source already cautions against this. | `docs/CURRENT_STATE.md`, `README.md` and Home's Compass data. | Preserve evidence labels, pending/estimate disclosures and actual API sourcing. Never fabricate a balance. |
| P1 | Six equally weighted Financial Compass panels, two successive 3-column grids, weaken decision hierarchy. | Home `compass-grid` blocks. | Promote three key measures into one responsive financial position section; subordinate debt, savings, payment capability; retain all measures. |
| P1 | "Add balance" served as text for a missing/null value, implying a direct action that is not available. | Home `money()`. | Use explicit "Unavailable" to distinguish missing data from zero. |
| P1 | Setup progress has a visual bar but no progressbar semantics. | Home `setup-progress` markup. | Add named progress semantics and current percentage. |
| P2 | Design primitives exist but lack a focused portal-specific density and responsive composition layer. | `globals.css`, `opfin-brand.css`. | Introduce scoped experience CSS, use existing brand tokens and avoid rewriting core CSS. |
| P2 | Information-management and high-frequency journeys need reference-pattern approval before broader rework. | Financial Space index and Borrow journey. | Use three design references, then implement by vertical slice with role/flow tests. |

The older `apps/web/docs/audit/frontend-audit.md` includes historical scaffold observations and must not be treated as a fresh current-state audit.

## 3. Phase 1 implemented in this branch

- Created `ActiveNavLink.tsx`: active-route styling and `aria-current`. Server filtering in `AppShell.tsx` continues to control which links are exposed.
- Created `MobileSidebar.tsx`: accessible mobile navigation disclosure; closes on route changes; desktop retains existing navigation.
- Added `premium-experience.css`: scoped shell density, hover/focus patterns, layout tokens and responsive Home hierarchy. Brand colours come from the canonical generated tokens; this file does not replace or fork them.
- Updated Home presentation: three priority measures in a unified Compass, next recommended action prominent, Space access adjacent, three secondary measures beneath. Existing profile, activation, consent, KYC, capabilities, financial-wellbeing APIs and downstream links remain untouched.
- Corrected missing-balance text; added account-setup progress semantics.
- No financial calculations, underwriting, roles, endpoints, service availability rules, data persistence or deployment infrastructure intentionally changed.

## 4. Reference screens requiring design sign-off

**A. Personal Home / Financial Compass:** one financial position surface with evidence labels, one recommended action and Financial Space access; sample figures are illustrative only.

**B. Financial Space records / data management:** explicit active Space, permission-aware records, readable tables, search, filters, financial-state labels, horizontal-overflow handling and export only when authorised. Prototype controls are not working API features.

**C. Borrow request / high-frequency workflow:** step tracker, amount and purpose, clear draft/assessment/offer separation, consent reminder, named lender and costs only once sourced and disclosed. Prototype figures are not credit offers.

Concept desktop and mobile renderings are supplied separately for review. They are **illustrative reference prototypes, not screenshots of the built application**.

## 5. Next implementation phases

1. **Acceptance gate:** review actual deployed/current Home, Financial Spaces and Borrow journeys at desktop 1440/1280, tablet 768, phone 390/360, plus keyboard and screen readers. Capture before/after screenshots against the same commit and data state.
2. **Vertical slice:** align Financial Space list/detail, search/filter/table primitives and loading/empty/error states. Preserve exact-Space authority and financial-state provenance.
3. **Credit journey:** refine request, terms, lender selection, assessment, offer decision, acceptance, servicing and hardship with existing disclosures and explicit confirmation boundaries.
4. **Design-system consolidation:** add shared components only after the first slice is accepted; formalise state tokens, type/spacing/contrast rules, form and table patterns and visual regression snapshots.
5. **Cross-role and Flutter parity:** operations, employer, partner and mobile journey mapping; role-isolation checks before deployment; Flutter design tokens consume approved values through a reviewed generation path.
6. **Release control:** QA, security, accessibility, performance, API contract, production-data isolation and rollback sign-off before merge/deployment.

## 6. Verification record

**Source inspection performed:** files listed in section 1; implementation code and CSS written to a non-production feature branch.

**Not yet executed against the running OpFin application:** Next.js build/typecheck/lint/unit tests, Flutter build/device tests, API integration tests, authenticated role verification, real-browser journey regression, screen-reader testing, before/after production-route captures and performance benchmarks. Never infer these from successful GitHub writes.

**Required automated checks before merge:**

```bash
cd apps/web
npm ci
npm run typecheck
npm run lint
npm run test
npm run build
```

**Required browser test matrix:** customer (new setup, verified and unverified), operations, platform admin, support, employer admin and programme partner; at minimum 1440x900, 768x1024 and 390x844. Verify selected nav, mobile menu open/close, expected role-specific links, reduced motion, zoom to 200%, keyboard navigation, screen-reader announcement of active link and progress, empty/null Compass figures, API failure state, Space privacy and credit disclosure journey.

**Release decision:** HOLD. Reference mock-ups have been rendered separately, but no actual application render or end-to-end acceptance is established in this record.
