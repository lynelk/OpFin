# OpFin Financial Life Architecture: Continuity, Dignity and Aspiration

**Status:** Canonical target architecture proposal. Architectural requirements approved for implementation planning; no claim of deployed capability, regulatory approval or completed migration.
**Date:** 17 October 2026
**Owner:** OpFin product, risk, safeguarding, architecture and engineering
**Applies to:** Laravel API, Next.js Web, Flutter App, Financial Spaces, approved assisted/low-data channels and external services through Cito.

## North Star

> **Every financial life deserves a future worth looking forward to.**
>
> OpFin exists to help everyone understand their finances, make confident decisions and pursue what matters to them, at every age, every stage and every financial scale.
>
> **We don't define success for people. We help them make progress towards their own.**

Customer brand promise remains **Your next step, clearer.**

## Architectural mandate

Financial continuity, dignity and aspiration are **domain and release invariants**, not optional copywriting. The same person should remain able to learn, understand, plan and receive appropriate assistance when an individual regulated product is unavailable. Access to financial action always remains constrained by real eligibility and safeguarding.

These requirements extend the established `One person, one identity`, Personal Space ownership, Financial Space permission/entitlement/eligibility separation, channel continuity, progressive verification and advice independence. They **do not** replace those controls.

### Financial continuity invariants

1. A person retains a stable OpFin identity and personal financial relationship across channels and authorised life transitions. Changes in age, employment, income, wealth, phone number, provider, household or Space membership do not silently create a new financial identity or erase historical evidence.
2. Personal Space records belong to the person; group or institutional records belong to their authorised contexts. A guardian, employer, group treasurer, partner or former Space membership does **not** inherit access to someone else's private financial history.
3. Every saved financial event, source and balance must retain its real provenance and state: user-reported, verified, estimated, scheduled, pending, unavailable or reconciled/confirmed as applicable. Pending acknowledgement is never payment or custody finality.
4. Identity, financial records, customer aspirations, guardian authority, product eligibility, commercial entitlement and source evidence are **distinct concepts**, connected through explicit grants and scopes rather than one `is_eligible` flag.
5. Transition from youth to adult requires a new policy check for consent, authority, privacy and provider relationships. It never automatically upgrades credit limits, changes custody, exposes childhood records or allows unsupervised financial actions.
6. Migration and import preserve attribution, auditability, source periods, currencies and owner/Space boundaries. Conflicting history is reconciled, not flattened or overwritten.
7. Financial Passport export/reuse requires explicit permitted purpose, consent where required, freshness, recipient scope, revocation/expiry and a lawful retention strategy. Past learning, sensitive characteristics and participation in assistance programmes must not silently become underwriting features.

### Dignity invariants

1. Regulatory ineligibility is **service-specific**. A declined, suspended, unavailable or age-restricted transaction must not, by itself, terminate access to appropriate financial information and safe guidance.
2. The server's eligibility, authority, consent and financial decisions are authoritative. UI visibility, coaching messages, age declaration or friendly language cannot authorise money movement or a regulated financial product.
3. Each denial and material failure has a stable reason code, plain-language explanation, safe next-step alternatives, dispute/help route where applicable, and the original legal/contractual disclosure.
4. Guided journeys present no shame language, public financial comparisons, exploitative countdowns, punitive streaks or hidden opt-ins. A recovery plan must not default to another loan.
5. A financial-distress indicator cannot be repurposed for advertising, cross-selling or credit decisioning without an independently valid purpose, approved policy and lawful processing.
6. The advice-ranking objective is customer benefit, not revenue, partner commission, transaction frequency or daily active usage. No speculative claim or hallucinated provider activation, balance or savings yield is permitted.
7. The coach can recommend and explain, not override financial policy, approve credit, move money, accept disclosures or make a binding decision for the person.
8. A customer can request human support, challenge an outcome and choose not to pursue a suggested action. Assistants never collect PINs or OTPs.

### Aspiration invariants

1. Users define their own financial ambitions through voluntary, revisable goals and planning priorities. No platform-set wealth tier, age label, gender, location or presumed income determines what someone is 'supposed' to aspire to.
2. Goals may include stability, school fees, emergency reserves, enterprise working capital, home ownership, protection, investment diversification or retirement; user-defined text is not an automatic offer or eligibility signal.
3. Goal progress draws from attributed and consented information. Distinguish **plan**, **recorded progress**, **verified financial outcome** and **estimate**. A simulated saving is not deposited money.
4. Optional milestones celebrate useful progress, including recovery, without public rankings or behaviour incentives to borrow, trade or pay more often.
5. Customers select their preferred presentation depth (Simple, Detailed or Advanced) independent of financial status. Financial figures, legal costs, rights and material risk are never omitted merely for a simplified display.
6. Users may pause, archive, change or end goals without losing appropriate financial record integrity; deletion/retention must follow legal and accounting duties.

## Domain ownership and boundaries

| Bounded context | Responsible for | Explicit non-responsibility |
| --- | --- | --- |
| Person and Identity | Stable person ID, account access, verified identity evidence, consent provenance | Not a person's wealth class or personal ambitions |
| Financial Life | Personal user preferences, voluntary life intentions, continuity events, evidence links and lawful transition states | No transfer of custody, underwriting override or cross-Space record ownership |
| Financial Spaces | Separate Personal/Household/Group/Organisation context, membership, delegated authority | No implied access to a member's Personal Space |
| Financial Compass | Evidence-aware finances, available money, obligations, cash flow and goal snapshots | Not a verified net worth or guaranteed safe-spend number when evidence is incomplete |
| Aspiration and Goals | User-authored purpose, plan, target, progress attribution and revisions | No fabricated returns, automatic underwriting or presumed aspiration |
| Guidance and Support | Explainable recommended actions, respectful alternatives, learning and hardship/referral path | Never approves contracts, creates a loan offer or hides material risk |
| Capability and Eligibility | Service rules, provider activation, age/capacity, guardian authority, consent, KYC, jurisdiction and risk/suitability | An eligible service does not imply a member's right to access a Space |
| Financial Passport | Purpose-bound export of permissioned, attributable records | Not an unrestricted universal credit file |
| Channels | Consistent rendering in Web, Flutter, assisted and supported low-data channels | No independent financial decision logic; Cito handles approved external-service orchestration |

Existing `apps/api/app/Services/FinancialWellbeingService.php` provides an evidence-aware Compass and `next_best_action`. Existing Financial Space entities and `SavingsGoal` are valuable foundations. They are **not** proof that the entire life-continuity or protected-youth model is implemented.

## Proposed lifecycle event model (future schema; do not migrate without review)

**financial_life_preferences**
- Person owner ID, preference version, display depth, language/accessibility choices, communication and notification preferences, timestamps.
- Separate from regulated service consents. A display preference must never authorise sharing or transaction execution.
- Explicit consent and child-protection rules govern any personalised stored data.

**financial_aspirations**
- Immutable public reference, person owner, optional exact Space reference and scope, chosen goal/purpose, selected priority, status, target currency/amount/date where applicable, source and edit/revision timestamps.
- A goal record is a plan. It is *not* a bank account, regulated deposit, investment order or externally guaranteed balance.

**aspiration_progress_evidence**
- Aspiration reference, attributed financial source and revision, observed date, currency, data confidence, calculation version, reversible/confirmed status and recorded actor.
- Keep plan/estimate and verified outcomes distinct. Never count duplicate or pending events as realised progress.

**financial_life_transitions**
- Person ID, transition type (age-of-majority, guardian authority, account access, service graduation, Space membership), before/after policy version and effective date, consent evidence/reference, verification state, actor and audit reference.
- Revalidate grants rather than copy adult permissions or expose a minor's history by default.

**guidance_decisions**
- Purpose, user/Space context, evaluated source references and timestamps, rule version, eligibility decision reference (if applicable), selected action reason, alternatives, explanation, financial-state certainty, accessible wording variant and audit reference.
- Do not store a behavioural vulnerability label for convenience. Retention and training usage require explicit reviewed purposes.
- If generated suggestions are used, deterministic safety policy and service gates still choose which actions may execute.

### Request/decision flow

```text
Authenticate / access appropriate public learning
 -> identify person and authorised Personal/Financial Space (if account journey)
 -> load consent/purpose and data minimisation rules
 -> load recorded evidence with freshness/confirmation
 -> load user-selected goals and permitted experience preference
 -> run separate capability, permission, entitlement and eligibility gates
 -> determine guidance or an allowed alternative
 -> return evidence-labelled financial view + reason + one achievable action
 -> client renders Simple/Detailed/Advanced without changing authority
 -> high-impact financial action requires its own transaction-authoritative confirmation
 -> record compliant audit and human-help/dispute route
```

**Gate order is not a bypass:** when restricted, return appropriate *information* and support without issuing a financial product offer. Where law, safety or abuse prevents account access, the system may decline access and still offer appropriate public information or authorised support. 'Never turn anyone away' is a design aspiration, not a commitment to bypass controls.

## Minors, vulnerable users and safeguarding

- Financial education should be accessible through a low-data, privacy-preserving public path where practicable without forcing full KYC, wallet or a persistent child profile.
- Any personalised minor account requires jurisdictional analysis of legal capacity, children’s data processing, guardian authority/consent and permitted service use. Do not silently enable such accounts from this specification.
- Savings goal tracking differs from regulated savings products. The latter require lawful provider activation, verified guardian arrangement where required and accurate custody/settlement disclosure.
- Age thresholds must be country/product/purpose-specific and applied server-side; an unknown age must not be treated as adult. A user's choice of view, email or device never establishes age.
- Review coercion, domestic/financial abuse, shared devices and guardian visibility; age transition includes revocation/re-authorisation and safe transfer where lawful.
- Underage credit solicitation, behavioural scoring of children's lessons and speculative trading prompts are prohibited.

## Product experience and interface contract

**OpFin Today**: a one-screen *priority view* on a typical laptop where content permits: financial position, forthcoming commitments, user-selected aspiration and one useful next action. Hide no costs, risk or customer rights. Mobile presents the same priority information first, detail on demand.

**OpFin Progress**: verified and self-recorded milestones displayed with proper confidence and a private, non-competitive tone. Progress is not exclusively accumulation of wealth.

**OpFin Ahead**: user-owned planning and what-if exercises with clear assumptions. No imaginary return, assured credit or investment yield.

**Restricted service state**: explain the specific restriction; expose available, safe education or planning options; show authorised support and re-evaluation options. Never display 'no future' or treat missing data as personal failure.

**Data quality**: if a balance is unknown, say unavailable, not zero. 'Safe to spend' must explain the model, omitted commitments and limitations. Do not equate spending ability with overall wellbeing.

## Cross-channel, delivery and security

- The API owns decision reasons, evidence and eligibility outcomes; every channel consumes the same source-of-truth rather than recomputing age/product rules in the client.
- Low connectivity may cache explicitly permitted, tagged educational and draft data; never claim an offline financial instruction is settled. Sensitive offline caches require device threat modelling and expiry/clear-on-context-switch.
- Cito is the orchestration boundary for approved external providers and communications. OpFin remains independently operable for its core permitted management and learning functions, with unavailable providers represented accurately.
- Feature flags for life-stage, guest learning, aspirations and personalised guidance must default to a safe off/preview state until legal and product acceptance. A marketing claim cannot activate a regulated service.

## Acceptance tests and release blockers

1. A person can access appropriate foundational learning even when no credit product is permitted; where anonymous access is lawful, no permanent identity/profile is required.
2. Minor/unknown-age, revoked-guardian and mismatched-identity attempts cannot bypass regulated-service or account permissions on *any* channel.
3. Leaving or switching Financial Spaces never reveals another person’s Personal Space or changes the owner of historical financial records.
4. A person can freely edit or pause an aspiration without changing eligibility or credit risk; guidance does not infer wealth class or gender.
5. Pending transaction/estimated balances do not appear as confirmed goal progress, paid bills or safely spendable funds.
6. A shortfall, rejection or hardship event produces a truthful explanation, constructive allowed option and support route; pricing, penalties and rights remain visible when materially relevant.
7. The same authorised person and Space produce semantically equivalent decisions on App, Web and supported low-bandwidth channels.
8. Accessibility, low-end Android, data usage, legal/safeguarding, security, analytics and measured user comprehension are verified at agreed release thresholds.
9. A new schema or migration cannot merge children's learning records into credit scoring, group accounts into Personal Spaces or unconsented programme attributes into underwriting.

**Evidence rule:** This is a **target architecture**, not proof of source implementation, deployment, provider activation, legal authorisation or customer acceptance. Each gap needs an owner, planned change and dated test evidence. See [Consolidated P0–P1 programme](../product/OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md).
