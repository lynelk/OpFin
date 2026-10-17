# OpFin P0–P1: One Universal Financial Life Programme

**Status:** Approved strategic direction; implementation backlog and acceptance gates **proposed**, not delivered.
**Prepared:** 17 October 2026 | **Language:** British English | **Canonical repository:** `lynelk/OpFin`

## The North Star

> **Every financial life deserves a future worth looking forward to.**
>
> OpFin exists to help everyone understand their finances, make confident decisions and pursue what matters to them, at every age, every stage and every financial scale.
>
> **We don't define success for people. We help them make progress towards their own.**

Retain the existing customer brand promise: **Your next step, clearer.**

### Outcome

**One OpFin for everyone**, with protected access to appropriate financial learning and planning, contextual guidance, consistent Financial Spaces, customer-owned ambitions and more sophisticated features on demand. Financial continuity, dignity and aspiration are mandatory **domain invariants**. Product restrictions may prohibit an action but should never be mistaken for rejection of the person's broader financial future.

### P0 and P1 are one scope, sequenced safely

P0 identifies blockers and foundations. P1 delivers the value that depends on those foundations. Both belong to the **same approved priority programme**; do not postpone P1 conceptually to unrelated future product phases. Never ship a consumer promise before its relevant P0 gate is accepted.

| ID | Workstream | P0 foundations | P1 customer capability | Proof of acceptance |
| --- | --- | --- | --- | --- |
| FL-01 | **OpFin Today** | Audit existing Home/Web/Flutter and build role-safe responsive one-screen hierarchy, Financial Compass evidence labels, one action | Contextual Today view, accessible preferences for Simple/Detailed/Advanced and efficient Space selection | 1366x768 + 390x844 real render, task success and readability without hidden essential disclosures |
| FL-02 | **Universal access** | Separate anonymous educational access from protected financial accounts; document country/age/guardian consent; enforce provider eligibility server-side | Learning and planning alternatives when credit/investing/savings are restricted; supported child and guardian journeys only following sign-off | Denial and no-data matrix; no bypass through client, API or assisted channels |
| FL-03 | **Financial continuity** | Specify person ID, Personal/Space ownership, history provenance, custody boundaries and life-stage transition/re-authorisation | Consistent account/Space continuity, governed imports and age-transition journeys | Cross-Space, recovery, migrations, guardian revocation, consent and audit tests |
| FL-04 | **Aspiration and goals** | Model user-owned goal priorities, evidence source, timestamps, versioning and plan-vs-realised states separately from credit | OpFin Ahead and Progress: editable goals, verified milestones and voluntary progress history | Pause/revise/export controls; no false deposit/return or underwriting side effects |
| FL-05 | **Dignity and recovery** | Versioned guidance reason codes, permitted alternatives, complaints/hardship paths and customer-interest ranking | Encouraging next-step explanations, meaningful fallback on declined services, safe hardship workflows | Human-reviewed copy + adverse decision/shortfall tests; no hidden fees or coercive nudges |
| FL-06 | **Adaptive Compass** | Single API-owned evidence-aware position; consistent null/estimated/confirmed states and provenance; preserve current controls | Simple, Detailed and Advanced views of same financial facts, with asset/debt depth where truly available | Channel parity, evidence confidence, screen reader and 200% zoom |
| FL-07 | **Passport and consent** | Purpose/recipient/freshness scoped exports, lawful consents, retained audit and revocation boundaries | Permissioned use of eligible histories for genuinely useful experiences, with user visibility and control | Consumer consent tests; disallow hidden underwriting inputs, child learning data and Space leakage |
| FL-08 | **Inclusive operations** | Low-data budgets, accessible mobile, security, safeguarding and exact deployed-provider truth | Fluent Flutter/Web/authorised assisted journeys, local-language pilots after review, resilience under low connectivity | Supported real-device tests, privacy/security review, measured cost/traffic and customer comprehension |

## Parallel high-priority Finance workstream

OpFin Finance prioritises evidence-led, permissioned Stolets merchant finance and durable portfolio controls **in parallel with** the universal financial-life foundation. The [Finance Evidence-Led Credit Programme](OPFIN_FINANCE_EVIDENCE_LED_CREDIT_PROGRAMME.md) preserves fourteen required technical/risk measures and the 0–3, 3–6, 6–12 month validation and activation horizons. It is not permission to launch or a decision to steer every customer into borrowing. The strongest dependencies are FL-02 (independent eligibility), FL-03 (attributed business/Personal Space ownership), FL-05 (explainability and hardship), FL-06 (affordability and financial truth), FL-07 (consented provenance) and FL-08 (channel parity and evidence).

## Implementation order (release slices, not separate initiatives)

### Slice A: Trustworthy foundation and compact clarity

- Adopt constitutional North Star and domain rules in product blueprint, engineering standards and life-continuity specification.
- Baseline Home screenshots and journeys in 1440/1366/768/390/360 widths; preserve major financial disclosures and permissions.
- Check identity/Space ownership, provider/country age rules, progress provenance and voluntary user preferences before building youth account flows.
- Make next-action messages supportive while retaining exact financial facts, correct route/authorisation, evidence and alternatives.
- Gate P0 acceptance: security, child protection, accessibility, API/source evidence and changes to existing users all reviewed.

### Slice B: Personal aspiration and safe access

- Add voluntary goal preference and progress models with ownership, currency, consent and revisions; use existing goals where possible.
- Deliver previewable OpFin Today, Progress and Ahead in a shared experience design system. Use existing routes instead of creating unnecessary navigation.
- Public educational access must not require credit eligibility. Any protected minor account requires jurisdictional review and verified guardian authority where applicable.
- Provide service-specific denial explanations with a safe alternative and re-evaluation/support route; do not render an ineligible product as available.
- Gate P1 acceptance: observed user journeys, backend rules and device/browser parity.

### Slice C: Portable continuity and richer scale

- Financial Passport evidence sharing and age transition/re-consent, preserving strict exact-Space isolation and audit.
- Advanced analysis for complex financial lives without a wealth-tier interface or replacing Simple mode.
- Optional low-bandwidth and assisted expansion subject to privacy, local language and provider approvals.
- Gate acceptance: independent review of financial-state consistency and actual customer wellbeing outcomes.

## Mandatory architectural contracts

See [Financial Life Architecture](../architecture/FINANCIAL_LIFE_CONTINUITY_AND_DIGNITY.md). The following are blockers for any purportedly 'complete' release:

1. Stable person identity and Personal/Space boundaries; no history loss or cross-owner inference.
2. User-owned aspiration records distinguish plans, self-reported progress, verified outcomes and unknown values.
3. Guidance is explainable and cannot execute a contract; legal eligibility remains API-authoritative.
4. Age and guardian access are context-specific, privacy reviewed, and never inferred from a UI selection.
5. Ineligible product access never implies ineligibility for appropriate education and guidance.
6. Commercial commissions, loan volume, daily screen time or wealth classification do not determine guidance priority.
7. No financial shortfall, declined credit, incomplete KYC or other setbacks trigger humiliating or intentionally manipulative product language.
8. All channels preserve the same accounting and eligibility rules; no misleading pending-payment finality.

## Owners and dependencies

- **Product & design:** one-screen journey maps, copy system, Simple/Detailed/Advanced patterns, voluntary goal model and customer testing.
- **API/domain engineering:** stable identity/Space policies, aspiration evidence, guidance decision contract, entitlement and eligibility boundaries, transaction controls and audit.
- **Flutter & Web:** evidence-aware rendering and accessibility, one-step actions, responsive/low-data implementation and no client-side eligibility bypass.
- **Risk, legal, safeguarding and privacy:** age, capacity, consent, recovery remedies, provider-country eligibility and use of history, including minors.
- **Operations & partners:** activation truth, authorised servicing/help, complaint recovery, support SLAs and Cito integration validation.
- **QA & research:** reference screens, regression and visual snapshots, independent device testing, customer comprehension and trust outcomes.

## Desired customer measures (initial research targets, not current results)

- Customer identifies their money position and next step within 10 seconds in supervised tasks.
- Main tasks reachable within 1–2 obvious steps from Home, with safe disclosures at decision time.
- Financially distressed customers can find meaningful support without needing another loan or revealing unnecessary information.
- No real financial cross-Space data leaks or regulatory age/consent bypasses.
- Users can revise their goals and change display depth without loss of identity, financial records or product eligibility.
- Low-end Android and limited-connectivity journeys meet accessibility and cost budgets set from measured baselines.
- Comprehension of actual credit costs, obligations and service status meets explicit risk-approved test standards.
- Retention and return usage measured as **voluntary useful outcomes**, not addictive screen-time optimisation.

## Evidence register

- **Existing foundations observed in source:** Personal/Financial Spaces and independent gates; Financial Compass and `next_best_action`; savings goals; role-specific web; Flutter app.
- **Already in separate draft UI work:** [PR #187](https://github.com/lynelk/OpFin/pull/187) contains a compact Home candidate and separate youth-access proposal. That PR is not proof of deployment or approval. Coordinate acceptance/rebase rather than copying unfinished UI changes into main.
- **This programme delivers first:** constitutional documentation, a testable source-backed guidance improvement and future structural contracts. It does not create or authorise youth financial accounts, migrations, provider savings products, or completed life-stage transitions.
- **Required before any declaration of completion:** actual CI, authenticated browsers, Flutter device tests, security/privacy, minor safeguarding and exact financial journey verification; capture before/after production-route screenshots on matching state. Do not merge solely on attractive prototypes.
