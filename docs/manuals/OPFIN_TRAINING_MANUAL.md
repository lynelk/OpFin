# OpFin Training Manual

Version: 21 September 2026
Audience: customers, savings-group members and facilitators

## Training outcome
A learner should be able to register/sign in, understand Home, switch Financial Spaces, record everyday financial information, manage a savings-group context, use regulated services safely and obtain support without needing a computer for normal Individual or Savings Group journeys.

## Module 1 — One identity
Demonstrate phone → OTP → name → PIN. Explain progressive verification: extra information is requested when an activity requires it. Exercise: sign in and identify the Personal Space.

## Module 2 — Understand Home
Show the next action, money picture and simple language. Explain that estimates are not confirmed cash. Exercise: identify available money, upcoming commitments and safe-to-spend where data exists.

## Module 3 — Financial Spaces
Open **My money & spaces**. Explain Personal, Household, Savings Group, Business and SACCO contexts. Exercise: switch Space and confirm the title/context changes. Reinforce that Personal Space remains private.

## Module 4 — Everyday money
Record money/accounts, a budget and an upcoming event. Exercise: explain the difference between money in, money out, an asset and a debt.

## Module 5 — Assets and obligations
Add one item under **I own**, one under **I owe**, and one **Owed to me**. Explain how these affect the financial position.

## Module 6 — Savings groups
Create a training group, invite a member, review membership and record group information. Emphasise that officials act only within authorised group roles.

## Module 7 — Financial services
Demonstrate Save, Borrow, Protect and Grow/Invest. Teach learners to review provider, price/cost, total repayment or premium, risks, due dates and disclosures before confirming.

## Module 8 — Poor connectivity and support
Demonstrate retry/offline states. A learner must know that an initiated payment is not complete until confirmed. Show Support and receipts/history.

## Facilitator checks
Use plain language; demonstrate rather than lecture; never collect a learner's PIN; allow repetition; use icons and amounts before technical terminology; verify understanding by asking the learner to perform the task unaided.


## Module 9 — Financial resilience
Open **Build financial resilience** from Home. Explain the financial-reputation stage, current credit position and contextual next steps. Reinforce that the reputation stage is not another credit score and does not replace affordability checks.

## Module 10 — Voluntary inclusion and programmes
Show the optional programme-measurement switch. If the learner chooses to participate, demonstrate the voluntary inclusion form and the ability to choose **Prefer not to say** where available. Explain that these details may support programme eligibility and aggregate reporting but do not become credit-risk inputs.

Demonstrate an active programme. An eligible customer can join; an incomplete or ineligible result must be explained as a programme-participation result, not a credit decline. Demonstrate **Leave** for an enrolled customer and explain that exit is voluntary and idempotent, preserves prior audit evidence and stops later events being counted inside that programme’s participation window. An exited participation is not silently re-enrolled.

## Module 11 — Alternative credit support
Demonstrate adding support evidence such as a salary undertaking, guarantee, receivable or warehouse receipt. Explain the three separate states: **submitted → independently verified/rejected → recognised by a relevant product policy**. Submission or verification alone does not approve credit.

## Module 12 — Accessibility preferences
Demonstrate larger text, simple wording, reduced movement and high contrast. Where available on the test device, demonstrate VoiceOver/TalkBack against the same core journey. Never claim certification for an assistive device that has not completed physical-device UAT.

## Training module: Impact and outcome measurement

### Learning objectives

After this module, a trainee should be able to:

- distinguish OpFin financial health from credit scoring;
- explain why programme measurement is optional;
- identify when programme-linked observations may be recorded;
- explain small-cohort suppression;
- describe the difference between measured change and proven causal impact;
- explain the programme-partner reporting boundary.

### Practice exercise

1. Sign in as a customer and open Financial resilience.
2. Complete a financial-health check-in.
3. Confirm the result includes a transparent status and reasons.
4. Confirm the interface states that the result is not a credit score.
5. With programme measurement consent off, attempt a programme-linked outcome capture through the API/UAT fixture and confirm it is rejected.
6. Enable consent, enrol in an eligible test programme and repeat the authorised programme observation.
7. As an operator, configure a theory of change and assign an impact indicator.
8. Record fewer than five participant observations and confirm the partner outcome surface suppresses the participant count and values.
9. Record an institutional aggregate observation and confirm it can be reported without exposing participant records.

Never train users or partners to interpret a programme outcome as proof that OpFin caused that outcome unless the evaluation design independently supports causal attribution.

## Module 13 — Programme delivery across channels

Demonstrate one due programme instrument through the App or Web, then explain how the same server-authoritative instrument can be used through verified WhatsApp, USSD or assisted capture.

Training checks:

1. consent withdrawal removes programme-measurement prompts from customer channels;
2. a reviewed translation is shown where configured, otherwise English fallback is explicit;
3. assisted capture records the staff actor separately;
4. partner users are dedicated programme identities, not customer accounts;
5. exports remain aggregate and preserve small-cohort suppression;
6. provider-adapter evidence remains outside underwriting unless it separately passes the governed credit-data pathway.

Also demonstrate the Commercial performance dashboard to staff who manage the business. Explain that CAC and contribution are only as complete as the acquisition, cost and revenue evidence actually recorded.
