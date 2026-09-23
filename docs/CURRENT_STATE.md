# OpFin current state

Updated: 23 September 2026  
Canonical branch: `main`  
Reviewed source commit: `1a580e490ccb4cd5cc55f06ea9d09fad6619fd7e`

## What OpFin is now

OpFin is a financial operating platform with embedded financial services. Lending remains important, but it is no longer the product boundary. The platform supports one identity across Personal, Household, Savings Group and authorised organisation Financial Spaces, with mobile-complete individual and group journeys and deeper Web Workspaces for institutional use.

Current implemented product areas include:

- identity, consent, KYC and permissions;
- Financial Spaces, memberships and roles;
- everyday money, budgets, goals, assets, liabilities, receivables and financial-health guidance;
- responsible credit, formal offers, verified-wallet disbursement, repayment, receipts and credit-information controls;
- savings, investment and protection foundations behind provider/capability gates;
- employer-linked financial wellbeing;
- inclusive-finance programmes, programme instruments, follow-ups, localisation, partner identities and privacy-suppressed MEL exports;
- programme-to-commercial graduation analytics, commercial funnel/unit-economics reporting and governed cost/revenue evidence;
- partner/provider routing through Cito where configured, with governed direct-provider fallback;
- CPay-preferred money movement;
- programme/provider evidence boundaries that keep programme measurement and protected attributes outside underwriting.

Stolets remains a separate SME automation, digitisation and commerce product. OpFin may consume explicitly consented and governed external evidence from Stolets, but does not absorb POS, inventory, purchasing or merchant operations.

## Customer channels

The canonical new-customer mobile journey remains:

`Phone → OTP → names → 6-digit PIN → Home → progressive verification → financial position / eligible service → disclosed action → confirmed outcome`

The marketing website is an information and Web Workspace entry point. Its sign-in screen is not the canonical new-customer registration journey. Do not document the web password-compatible sign-in route as the preferred onboarding route for new mobile customers.

App, Web, verified WhatsApp, USSD and authorised assisted capture use server-authoritative backend state. Capability, provider, product and regulatory activation gates remain explicit.

## Website position

The current website communicates OpFin as a connected financial operating platform for individuals, savings groups, businesses/employers, SACCOs and partners. Public claims must distinguish:

- implemented capability from activated provider service;
- illustrative product previews from production screenshots;
- eligibility guidance from guarantees;
- programme outcomes from causal-impact claims;
- source deployment from release certification.

The Web service public setup address is `https://opfin-web-production.up.railway.app`. The API setup address is `https://opfin-production.up.railway.app`. These generated Railway addresses are operational endpoints, not a claim that a custom public domain has been cut over.

## Deployment evidence

At reviewed `main` commit `1a580e490ccb4cd5cc55f06ea9d09fad6619fd7e`, GitHub commit statuses report successful Railway deployments for:

- OpFin API;
- opfin-web;
- opfin-worker;
- opfin-scheduler.

No GitHub Actions workflow run is associated with that exact head commit. Therefore:

- source deployment evidence exists;
- the full repository CI/release gate must **not** be described as passed for that exact head;
- production/provider activation still depends on genuine credentials, agreements, certification, licences, store release and physical-device acceptance where applicable.

## Documentation hierarchy

For current operational use, apply this order:

1. source code, migrations, registered routes and automated tests;
2. `docs/product/OPFIN_PRODUCT_BLUEPRINT.md` and current domain models;
3. this current-state record and `docs/product/CANONICAL_IMPLEMENTATION_STATUS.md`;
4. current API references under `apps/api/docs/`;
5. current manuals under `docs/manuals/`;
6. specialist lending, regulatory, release and deployment guides;
7. dated audit, demo, migration and historical evidence.

Historical files are preserved as evidence. They should not be silently rewritten to look current.

## External activation gates

The repository cannot manufacture:

- provider credentials or contracts;
- regulatory approvals/licences;
- Cito/gnuGrid/CPay production certification where still pending;
- reviewed translations that have not actually been supplied;
- physical-device accessibility certification;
- app-store review/publication evidence;
- partner programme agreements or lawful data-processing authority.

Where these are absent, documentation must say `pending`, `unverified` or `not activated`, rather than filling the gap with optimism wearing a tie.
