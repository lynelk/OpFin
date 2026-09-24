# OpFin training and user-guide foundation

Status: Controlled internal source for publication materials  
Updated: 24 September 2026  
Language: English (United Kingdom)

This document is the canonical reusable source for customer help, staff training, FAQs, onboarding scripts and facilitated learning. Published customer material should use plain language and the labels visible in the current product.

## Training principles

- teach one task at a time;
- use familiar words before technical terms;
- distinguish recorded facts, estimates and unavailable information;
- never ask for a customer's PIN or OTP;
- treat accessibility as normal service design;
- explain pending versus completed money movement;
- never promise approval, a particular limit, provider success or regulatory activation;
- explain that programme participation is separate from credit decisioning;
- use synthetic/training records for demonstrations.

## Core product story

OpFin helps people understand, manage, plan and improve their financial position.

A single identity may participate in multiple Financial Spaces such as Personal, Household, Savings Group and authorised organisation contexts. Membership in another Space does not expose the person's private Personal Space.

## Create an account

Canonical new-customer App journey:

1. enter phone number;
2. confirm OTP;
3. enter first name, optional other name and last name;
4. create a six-digit PIN;
5. enter Home.

The Web password-compatible sign-in route is for existing/authorised access and should not be taught as the preferred new-customer registration path.

## Progressive verification

Additional identity or institutional verification is requested when the selected activity requires it. Never coach users to send identity evidence through an unauthorised channel.

## Financial Spaces and everyday money

Teach users to confirm which Space they are in before recording or approving an action.

For Individuals and Savings Groups, normal everyday journeys should remain complete in the App. Web adds deeper analysis and institutional productivity.

Teach money/accounts, money in/out, budgets, upcoming commitments, assets, debts/payables, receivables, goals and financial-health guidance. Safe-to-spend and health guidance depend on recorded information and are not guarantees.

## Responsible credit

Explain the current credit profile/score where available, available loan limit as a maximum current exposure signal rather than guaranteed approval, amount due/next due date, affordability/eligibility, formal offer disclosures, verified-wallet payout/repayment, pending versus final provider state, and receipts after confirmed finality.

Never teach internal probability-of-default telemetry as a customer score.

## Savings, investment and protection

Only train on services genuinely activated in the environment. Explain the provider, eligibility, suitability, pricing and custody/settlement boundaries relevant to the service. Do not present source-code readiness as provider availability.

## Financial resilience and programmes

Teach financial-health status and financial-reputation stage as guidance, not substitute credit scores.

Programme measurement is voluntary and separate from underwriting. Teach consent, programme eligibility as a programme result rather than a credit decision, due check-ins, reviewed translations with explicit English fallback, voluntary exit, aggregate partner reporting, protected/programme attributes remaining outside underwriting, and the distinction between measured outcomes and proven causality.

## Alternative credit-support evidence

Explain the lifecycle:

**submitted → independently verified/rejected → recognised by the applicable product policy**

Evidence submission or verification alone does not approve credit.

## Location and maps

Teach location as an optional task aid, not as continuous tracking.

Customer-facing guidance should explain:

- OpFin does not track location in the background;
- approximate location is enough for finding nearby participating services;
- precise location is requested only for a physical asset, insured risk or claim incident when the task genuinely needs it;
- a user may search for a place or enter an area/address manually instead of using device location;
- Saving Groups, Investment Clubs and SACCOs can record an operating area and meeting place without revealing member home locations;
- a map preview is only a convenience and Google Maps is not the source of financial truth;
- partner geographic reports show aggregate coverage or the partner's own service network, not individual customer pins; and
- location is not part of the OpFin credit score by default.

When training staff, distinguish user-declared, device-reported, Google-place-confirmed, partner-confirmed and verified location provenance. Staff must not manually upgrade a customer location merely to make a record appear more trustworthy.

## Channels

App, Web, verified WhatsApp, USSD and authorised assisted capture use server-authoritative backend state. High-impact financial commitments require the appropriate authenticated/step-up path. PINs and OTPs are not requested in support conversations.

## Accessibility and assisted use

Teach supported large-text, simple-language, reduced-motion and high-contrast preferences. Physical TalkBack/VoiceOver and representative assisted-verification journeys remain release/UAT evidence, not assumptions.

## Publication rule

Before turning this foundation into a customer guide, FAQ, leaflet or training pack:

1. confirm current application labels;
2. confirm which providers/features are activated;
3. remove internal-only implementation detail;
4. run `make publication-check`;
5. obtain required legal/compliance/brand approval for the intended audience.
