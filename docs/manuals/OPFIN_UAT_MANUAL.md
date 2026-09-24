# OpFin user acceptance testing manual

Status: Controlled acceptance requirements, not a completed test certificate  
Version: 24 September 2026  
Language: English (United Kingdom)

Record tester/role, exact source and build, environment/channel/device, date, fixture, actual result, non-sensitive evidence, defect reference and accountable sign-off for every case. Use synthetic data unless a separately authorised production exercise requires otherwise.

Existing UAT identifiers 01–66 are retained. New treasury/Essentials/release cases use `DEL-` identifiers in the [current capability supplement](CURRENT_CAPABILITY_SUPPLEMENT.md), which forms part of this manual. No expected result below is a claim that the test has passed.

## Current acceptance position

The [24 September delivery record](../operations/DELIVERY_EVIDENCE_2026-09-24.md) reports six API regression failures and a Web TypeScript failure at the inspected baseline. Essentials also has unresolved financial-control review findings. Do not certify affected journeys from documentation, a source merge, or tests that cover only the established loan path.

## Existing acceptance scenarios

| ID | Scenario | Procedure | Expected result |
| --- | --- | --- | --- |
| UAT-01 | Register person once | App: phone → OTP → names → PIN | Account created; Personal Space available; no duplicate persona |
| UAT-02 | Web sign-in boundary | Open public site and select Web sign-in | Existing/authorised access is clear; password-compatible Web login is not the preferred new App registration flow |
| UAT-03 | Progressive verification | Use basic feature, then regulated feature | Verification requested when the selected activity requires it |
| UAT-04 | Multi-Space | Create/join Group and Business | Same identity sees authorised Spaces with distinct context |
| UAT-05 | Isolation | Attempt Personal data access from Group/Employer context | Denied unless explicitly authorised |
| UAT-06 | Individual mobile completeness | Complete supported everyday-money journeys in App | Normal essential Individual journey does not require Web |
| UAT-07 | Savings Group mobile completeness | Create/join/manage group context in App | Essential group journey completes in App; any Web-only treasury administration gap is recorded, not waived |
| UAT-08 | Financial position | Add asset, debt and receivable | Recorded values determine position consistently |
| UAT-09 | Safe to spend | Add money and commitments | Guidance reflects recorded inputs and is not a cash guarantee |
| UAT-10 | Web enhancement | Open Spaces/Workspace on Web | Same server-authoritative context and ownership |
| UAT-11 | Employer | Enable employer on Business Space | Capability without automatic employee Personal Space access |
| UAT-12 | Partner catalogue | Load activated products | Only applicable, active, territory-appropriate entries |
| UAT-13 | Subscription/entitlement | Activate plan | Entitlement separate from role and product eligibility |
| UAT-14 | Commercial idempotency | Record same source event twice | No duplicate economic event |
| UAT-15 | CPay reconciliation | Reconcile payment/reference | Provider reference and final state attributable |
| UAT-16 | Provider failure | Interrupt request/callback and retry | No duplicate movement; pending/error recoverable |
| UAT-17 | Offline | Capture supported action and reconnect | Synchronises once; no silent conflict overwrite |
| UAT-18 | Low literacy | Complete core App journey | Understandable labels and minimal typing |
| UAT-19 | Accessibility | Text scale/high contrast/screen-reader test | Operable journey with actual device evidence |
| UAT-20 | Permissions | Member attempts admin action | Denied; authorised official succeeds |
| UAT-21 | Documentation | Compare routes, clients, website and manuals | Terminology, fields, availability and recovery agree |
| UAT-22 | Programme measurement opt-in | Save measurement before/after consent | Rejected without required consent and programme state |
| UAT-23 | Programme consent withdrawal | Withdraw measurement consent | New measurement stops; ordinary financial use remains |
| UAT-24 | Protected signal boundary | Attempt risk eligibility for protected field | Blocked; no protected field enters credit risk |
| UAT-25 | Governed provider signal | Exercise consent/provenance/policy requirements | Only separate approved pathway can grant eligibility; ingestion alone is not underwriting |
| UAT-26 | Programme eligibility | Match, incomplete and non-matching profiles | Deterministic programme state; credit decision unchanged |
| UAT-27 | Programme privacy | Create cohorts below/above threshold | Small counts and values suppressed |
| UAT-28 | Outcome window | Activity before/during/after participation | Programme reporting respects participation window |
| UAT-29 | Alternative collateral | Submit with/without issuer evidence | Verification requires evidence and does not itself approve credit |
| UAT-30 | Cito/direct routing | Simulate ambiguous Cito failure | No silent duplicate fallback; reconcile before approved route change |
| UAT-31 | Positive employer enrichment | No/negative/verified-positive signal | No/negative neutral; permitted positive benefit capped; privacy purpose preserved |
| UAT-32 | Service economics | Unknown cost, then evidenced enrichment | Unknown remains null until known; principal/premium is not revenue |
| UAT-33 | Partner reports | Capital, insurance, savings and service reports | Reconciled sources and visible incomplete fields/exceptions |
| UAT-34 | Provider certification | Unconfigured/uncertified then certified fixture | Fails closed until required gate satisfied |
| UAT-35 | Partial identity evidence | NIN/phone succeeds without biometric provider | Partial evidence is not falsely completed KYC |
| UAT-36 | Ambiguous identity failure | Primary timeout with direct adapter configured | Explicit pending/error; no silent second enquiry |
| UAT-37 | Explicit KYC route change | Reconcile first, then select approved route | Route, source and provider references retained; no prohibited direct gnuGrid bypass |
| UAT-38 | Inclusion Space isolation | Write evidence to another person's Space | Denied; no cross-Space write |
| UAT-39 | Voluntary programme exit | Enrol, record, leave twice, record later | Idempotent exit; no post-exit inclusion in the closed participation period |
| UAT-40 | Programme partner isolation | Access ungranted/granted programme | Only granted aggregate reporting available |
| UAT-41 | Programme instruments | Create typed instrument and due baseline | Only due and channel-enabled instrument shown |
| UAT-42 | Programme localisation | Reviewed locale present/absent | Reviewed translation or explicit English fallback |
| UAT-43 | Programme channels | App/Web/verified WhatsApp/USSD/assisted response | One governed response model; assisted actor separate |
| UAT-44 | Partner invitation/revocation | Verify phone, activate, revoke | Dedicated identity; revocation removes access |
| UAT-45 | MEL exports | Generate CSV/XLSX/ZIP | Aggregate-only with suppression and notices |
| UAT-46 | Commercial dashboard | Attribution, costs and revenue | Metrics reconcile; unknown remains incomplete |
| UAT-47 | Commercial graduation | Evaluate graduation | Transparent analytics, not score/price/limit change |
| UAT-48 | Health enrichment | Use recorded evidence | Source provenance retained; result non-credit |
| UAT-49 | Adapter activation | Attempt without configuration/legal evidence | Required gates fail closed |
| UAT-50 | Adapter allow-list | Unknown and allowed signal keys | Unknown rejected; accepted evidence non-risk by default |
| UAT-51 | Website claims | Review current public copy | No unsupported provider availability or fabricated production preview |
| UAT-52 | Release evidence | Compare source, builds and running services | Deployment and acceptance distinct; equivalent candidate evidence retained while Actions is disabled |
| UAT-53 | Location optionality | Use core money/credit without permission | Baseline financial use remains available |
| UAT-54 | Approximate discovery | Search services with coarse permission | Discovery works where configured; query coordinates not retained as a hidden profile |
| UAT-55 | Precise purpose | Add asset/risk location | Precise permission requested for the explicit task only |
| UAT-56 | Background tracking | Inspect permissions/background behaviour | No background location permission or continuous tracking |
| UAT-57 | Manual location fallback | Provider disabled; add manually | Manual task works; provider-dependent action fails safely |
| UAT-58 | Place search | Select a place with provider enabled | Server returns permitted result; no provider key in client/payload |
| UAT-59 | Static map | Authorised and unauthorised preview | Authorised map; unauthorised denial |
| UAT-60 | Location Space isolation | Member/admin/removed-member | Appropriate reads/writes only; removed member denied |
| UAT-61 | Location purpose | Mismatched subject/purpose/consent | Rejected; retained purpose remains accurate |
| UAT-62 | Credit/location separation | Add/remove location and refresh credit | No location-driven score, limit or pricing change |
| UAT-63 | Insurance location | Insured-risk and claim location | Separate evidence; insurer remains claim authority |
| UAT-64 | Partner location network | Partner opens service network | Own permitted service points, not customer pins |
| UAT-65 | Aggregate geography | Small/large cohorts | Small cohorts suppressed; individual contexts excluded |
| UAT-66 | Location deletion | Delete eligible account with optional location | Optional personal location removed through governed closure |

## Treasury and Essentials additions

Execute `DEL-TR-01` through `DEL-TR-05` for stable opening baselines, duplicate imports, ambiguous matching, reasoned variance/issued-snapshot preservation and separate-currency statements.

Execute `DEL-ES-01` through `DEL-ES-06` for expected immutable accounting, concurrent repayments, exact-Space partner grants, open-obligation deletion, pending lender funding/reversal exposure and approved capital-mandate lifecycle.

`DEL-REL-01` verifies exact running sources, migrations and health; `DEL-DOC-01` checks original requirements, later decisions, API and manual consistency. Procedures and expected evidence are in the [supplement](CURRENT_CAPABILITY_SUPPLEMENT.md). Keep existing test IDs stable when extending a suite.

Essentials acceptance must additionally cover biller/rental verification, non-stacking overall headroom, rejection of OpFin as the primary lender, server-enforced distribution-channel terms, immutable disclosures, failed/ambiguous provider outcomes, repayment schedules, reconciliation and customer-controlled embedded-platform scopes. No financial exercise may create an unauthorised real obligation.

## Causality and privacy

Programme reports describe measured/observed change only, unless an appropriate evaluation design supports causal attribution. Financial-health and programme measurement remain distinct from credit scoring. Record privacy and financial-control failures as defects, not missing provider credentials.

## Release decision

Do not sign off unresolved Critical/High issues in identity, authorisation, Space isolation, accounting, privacy, money movement, replay/concurrency, reconciliation or required mobile completeness. Record lesser exceptions with accountable owner and accepted disposition.

A build or deployment result is not sufficient by itself. Use equivalent candidate-specific test/security/operational evidence while GitHub Actions remains disabled; do not disable existing tests, audits or financial controls to obtain a release.
