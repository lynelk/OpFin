# OpFin B2C Financial Companion: Decisions, Daily Reliance and Wellness

Status: Proposed canonical B2C delivery extension; review and acceptance required before claiming implementation
Date: 24 October 2026
Language: British English
Scope: Direct-to-consumer OpFin Web, Flutter, Financial Life API and authorised assisted channels
Authority: [Product Blueprint](OPFIN_PRODUCT_BLUEPRINT.md), [P0–P1 Universal Financial Life](OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md), [Financial Life Continuity and Dignity](../architecture/FINANCIAL_LIFE_CONTINUITY_AND_DIGNITY.md)
Non-goal: change OpFin's North Star, brand promise, independent add-on architecture, provider-of-record policy or regulated product permissions.

## 1. Standing identity

**North Star:** Every financial life deserves a future worth looking forward to.

**Customer promise:** Your next step, clearer.

**B2C positioning:** Know your money. Plan your future. Make your next move with confidence.

OpFin is a continuing, personal, provider-neutral financial-life companion. It helps a person observe, understand, plan, decide, act, monitor and adjust throughout changing circumstances. Credit, deposits, investments, insurance and specialist add-ons are optional extensions, never prerequisites for core financial wellbeing.

### Two equal design goals, not opposing choices

1. **Better decisions:** explain the person's position, uncertainty, trade-offs and one attainable next action.
2. **Earned, voluntary reliance:** become the dependable place the person returns to because their history, commitments, priorities, learning and decisions persist and are increasingly useful.

Do not attempt to eliminate reliance. Build **functional, trusted, reversible reliance** through continuity, reliability, low effort, relevance and outcomes. A user should lose convenience when they leave, not access to their own data, dignity, fundamental rights or lawful financial services. No coercive lock-in, shame, artificial streak pressure, unwanted notifications, invented urgent risks or pressure to borrow.

The relationship must remain useful if no financial providers, employer, SACCO, group, insurer or add-on is connected. No customer must buy a regulated product to obtain core value.

## 2. Product contract: the complete personal financial-life loop

**Recognise need → Obtain a first useful answer → Record/confirm facts → Plan a manageable next step → Follow through → Review real outcomes → Adapt the next plan.**

This is one loop that works across App, Web and supported low-data/assisted channels; it is not seven competing navigation tabs. Reuse Financial Compass, existing next-best-action, Financial Spaces, goals, calendar and money records. Do not duplicate authoritative finance state in a client-side AI layer.

| Phase | Consumer question | Required customer value | Evidence boundary |
| --- | --- | --- | --- |
| Understand | Where does my money stand? | Simple Financial Compass with obligations, uncertainty and context | Known/user-reported/estimated/provider-confirmed distinct |
| Decide | What should I do about this challenge? | Explanations, scenarios, trade-offs and one useful action | Calculations deterministic; advice eligibility and disclosure checked |
| Organise | What have I committed to? | Money diary, debts, goals and bill/income calendar | A planned event is not a paid transaction |
| Anticipate | What might happen next? | Forward-looking cash-flow and commitment scenarios | Forecasts labelled with assumptions and missing data |
| Follow through | What needs attention today? | User-controlled reminders and actionable check-ins | Notifications based on real events, not engagement manipulation |
| Progress | Am I improving on my own terms? | Personal review and milestone history; revise/pause plans | Self-reported progress not falsely presented as verified funds |
| Continue | What matters in my next life stage? | Durable identity, records and preferences through lawful transitions | Space and consent boundaries remain enforced |

### Customer-facing modes

- **Today:** where I stand, what matters now and one next action.
- **Progress:** my actual/recorded changes and user-defined milestones, not an arbitrary wealth ranking.
- **Ahead:** my upcoming commitments, goals and optional planning scenarios.
- **Simple / Detailed / Advanced:** depth preferences over one authoritative set of facts; never status, class or wealth categories.

These are experience patterns inside existing routes where possible, not mandatory additional tabs.

## 3. Acquisition and first-value journey

Acquire through concrete problems: salary running short, debt repayments, school fees, informal income, emergency preparation, confusing spending or a decision about affordability. Public calculators and education may be used anonymously where appropriate. Do not require KYC or consent for credit simply to view educational content or make a permitted personal plan.

Initial experience:
1. Let a person choose their present need: manage money, manage debts, plan a goal, understand financial health or explore.
2. Ask only the information necessary for a useful first answer; allow approximate amounts and 'I do not know'.
3. Show a result with source/confidence language and a practical, non-credit next step.
4. Offer to save the plan in the existing phone → OTP → names → PIN journey.
5. Invite a small follow-through action or opt-in reminder; leave non-essential verification to the regulated product gate.

**Activation event:** the person receives a supported, comprehensible and actionable money insight and records or selects a next step. Account creation, home-page views, KYC and app installs alone are not activation.

Research targets, not existing results: time to useful answer ≤3 minutes; routine expense capture ≤10 seconds on a tested low-end device. Confirm realistic thresholds in pilot testing.

## 4. Routines that create earned reliance

### Income / payday routine
On user-provided or verified income events, show recorded money, upcoming commitments, room for essentials, optional goal allocation and the impact of alternative plans. Support irregular, seasonal and fortnightly income without presuming a monthly salary. Never automatically move money without a separately authorised mandate.

### Everyday money routine
Fast manual record of cash, mobile-money and bank activity; recent-category reuse; optional receipt/statement-assisted draft capture; clear review before accepting imported entries; offline capture only where bounded, encrypted and safely reconciled. No unapproved generic SMS scraping or notification harvesting. Financial data completion is progressive, not a prerequisite for helpful guidance.

### Weekly review
Two-minute opt-in summary of new/changed records, upcoming commitments, progress on user-owned goals and one optional action. Missing records are explicitly missing. Avoid treating lack of data as zero expense.

### Commitment protection
Opt-in due-date and cash-gap reminders based on actual customer-authorised events, with meaningful lead time and user-configurable frequency, quiet hours, snooze and pause. Do not fire repetitive or fear-based notifications to inflate activity counts.

### Goals and recovery
User-owned milestones including emergency resilience, school fees, debt reduction, education or any other ambition. Customers can edit, pause or abandon goals without penalty. Hardship pathways provide budget reviews, realistic options and support rather than defaulting to a fresh loan.

### Learning while doing
Short, contextual lessons linked to current actions (e.g. annualised loan cost when comparing repayments, emergency-fund principles during goal setup). A lesson leads to a permitted optional action, not a content-consumption obligation.

## 5. Dependency and consumer agency invariants

**Keep/reliance dimension:** The user trusts OpFin as their financial memory, calendar, planning companion and progress history. Convenience compounds through accuracy, continuity, useful reminders and personalised guidance.

**Non-negotiable limits:**
- One durable person identity; Personal Space remains private from employers, groups and providers.
- Customer may export records, review permissions, revoke permitted sharing and disconnect optional integrations without losing their core account.
- No penalties for missed logging, broken streaks, low funds or goals revised because life changed.
- No dark patterns, compulsory retention prompts, hidden defaults, fee-driven product ranking or retention-target manipulation.
- No continuous location tracking or unapproved SMS reads. Protect sensitive notifications on shared devices.
- No fabricated balances, artificial money movement, assumed repayment, invented provider outcome or implicit custody.
- Financial wellbeing and user-reported life goals must not silently become credit scores or underwriting inputs.
- Core functionality must tolerate provider outages and remain useful with entirely manual records.
- B2C customer relationship, add-on business records and regulated providers remain separately owned/authorised.

## 6. Existing-source alignment and gaps

| Existing source family | Preserve and extend | Known release caution |
| --- | --- | --- |
| Flutter Home + Web dashboard | Financial Compass, one next action, goals and personal-first hierarchy | Real-device, cross-channel and accessibility acceptance still required |
| FinancialWellbeingService, Compass, calendar and goals | Reuse calculations, provenance and state; extend useful foresight only with explainable assumptions | Source implementation is not customer outcome evidence |
| Personal Money experience | Close the loop between recorded spending and budget actuals, fast entry, debts and obligations | Some mobile budget views present planned limits, not reconciled actual expense |
| Financial Spaces | Individual continuity and independently governed relationships | No cross-Space leakage or implicit employer data access |
| Learning and next-best-action | Contextual education and non-credit default pathways | Do not rank actions by lender commission or force credit setup |
| Savings / payment / lender / insurance integrations | Add optional authorised execution after planning | Provider record, funding, legal authority, custody and settlement remain distinct |

## 7. Delivery decisions (P0/P1/P2)

### P0: useful with zero providers
- Preserve / finish clear evidence-labelled Today + Financial Compass and one supported next step.
- Make baseline financial-life guidance precede optional account/KYC/credit setup on consumer Home.
- Fast income/expense capture, recurrence and confirmed-vs-unknown actuals.
- Upcoming commitment calendar and income-cycle-aware planning.
- User-owned goal plans, revisable and separate from product custody.
- A meaningful first-value experience, basic event-triggered reminder controls and accessible low-data mobile journey.
- Tests: no provider, empty account, manual-only account, low connectivity, missed income, multiple debts, expense overrun and financial hardship.

### P1: intelligence and convenience
- Financial Foresight scenarios with explainable deterministic calculations.
- Weekly money review with user-initiated and optional reminder delivery.
- Receipt/statement assisted *draft* entry and duplicate checking.
- Contextual lessons, goal milestones and private progress history.
- Decision-focused coaching with regulated-advice boundaries and escalation.
- Consent and notification-preference controls with per-purpose visibility.

### P2: advanced optional depth
- Fully permissioned provider account aggregation where contracts and local permissions permit.
- Advanced planning, premium forecasts, richer reports and legally appropriate specialist advice.
- Add-on-enabled experiences, always separable and governed by explicit contracts.
- Never hold back the P0 personal-financial-life loop awaiting these partnerships.

## 8. Measurements: outcomes and reliance, separately

Measure distinct users, not app opens.

- **First-value completion:** share of eligible starts reaching a valid actionable result, with denominator and unknowns.
- **Financially helped users:** distinct users completing a meaningful action such as recording/correcting a commitment, reviewing a plan or adjusting a goal. Define rules before instrumentation.
- **Healthy reliance:** repeat meaningful use across actual income/commitment cycles; renewal after a real financial event; voluntary reminder usefulness and task success. Do not reward screen-time.
- **Wellbeing outcomes:** baseline and later self-reported confidence, preparedness, planning and money control; locally validate any published psychometric scale before adopting external benchmark scores.
- **Consumer safeguards:** complaints, unauthorised access, advice errors, notification opt-outs, incorrect financial classifications, data recovery and consent breaches.
- **Operating economics:** acquisition cost, support/contact load, cost per helped user, contribution margin after hosting, communications, support, onboarding and partner charges.

Segment analyses by financial rhythm (regular/irregular income), onboarding intent, low-data conditions and voluntary engagement. Do not infer protected characteristics, financial competence or eligibility from stereotypes.

## 9. Required acceptance journeys

1. **No-partner new user:** can complete personal financial check-up and save a plan without lender, insurer, payment service or non-essential KYC.
2. **Manual money user:** can add a real income/expense, see evidence-labelled financial position and receive a relevant next action; missing entries remain unknown.
3. **Irregular-income user:** can define unscheduled or periodic income, see genuine commitments and receive no fictitious monthly salary.
4. **Debt pressure:** can see recorded obligations and appropriate support without automatic additional-credit promotion.
5. **Habit through useful events:** review upcoming commitment, act, record outcome, see revised plan and return after the next real event; no streak pressure.
6. **Privacy/reliance:** can pause reminders, export history and disconnect integrations with personal records preserved.
7. **Offline failure:** can access permitted cached help/records and distinguish queued drafts from server-accepted money states; no duplicate submissions.
8. **Cross-device/channel:** equivalent truth for Financial Compass on Web and Flutter; role and Space permissions enforced server-side.

Evidence: device/viewport screenshots, user task tests, accessible focus order, API contract tests, server-policy tests, migration and negative-case testing. A document, navigation card or green CI check alone is not acceptance.

## 10. Proposed 90-day validation programme

**Days 1–30:** audit current experience; test a first-value financial check-up and fix the main non-credit Home priority. Instrument valid first actions and data/source labels.

**Days 31–60:** complete fast money capture, real planned-versus-actual expenditure, calendar and income-cycle planning. Validate common tasks on low-end Android and Web.

**Days 61–90:** test the weekly review and opt-in event reminders with salaried and irregular-income cohorts. Assess sustained useful activity, outcome trends, safeguards and operating cost, then decide on scaled acquisition. Numerical targets are hypotheses until actual cohort baselines exist.

**Executive acceptance:** OpFin improves customers' financial decisions *and* becomes a trusted, voluntarily relied-on companion over their real financial cycles. Neither must be achieved by making them borrow, buy a partner product, stare at a screen or surrender control of their records.
