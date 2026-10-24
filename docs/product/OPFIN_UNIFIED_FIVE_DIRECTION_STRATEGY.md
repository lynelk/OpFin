# OpFin Unified Financial-Life and Institutional Relationship Strategy

**Status:** Proposed canonical strategy and technical alignment contract; only source-evidenced capabilities are implemented  
**Prepared:** 24 October 2026 • English (United Kingdom)  
**Business scope:** **B2C + B2B + B2B2C + C2B + C2B2B** in one coherent, independently usable OpFin platform.  
**North Star:** **Every financial life deserves a future worth looking forward to.**  
**Brand promise:** **Your next step, clearer.**

## 1. Executive operating decision

OpFin is **one person-centred financial-life operating platform with institution-complete operating environments and optional authorised financial services**. The five relationship directions describe **who initiates, supplies, distributes or executes a service**, not five apps, identities, databases, loan ledgers or independent brands.

A direct consumer must obtain useful value without financial-provider integration or institutional membership. A SACCO, Investment Club, Savings Group, employer, lender, fund manager or other institution must obtain an independently useful management service without subscribing to other OpFin institutions or the OpFin marketplace. When they voluntarily interact, there is one governed customer/institution relationship model, with explicit contracts, scope, consent, economic attribution and actual legal providers.

**One platform, five routes, independent value.** No mandatory cross-sell, coercive reliance, institution-level privacy override or unconfirmed financial status.

### The five relationship directions

| Direction | Who initiates and with whom | End-user/institution value | Concrete OpFin examples | Implemented/source boundary |
| --- | --- | --- | --- | --- |
| **B2C** | OpFin provides a service directly to an individual; individual opts in | Personal finances, decisions, financial memory, planning, borrowing/saving/investing/protection where genuinely available | Offline guest money check; Personal Financial Life; Financial Compass and Next Step; eligible provider products | Personal/financial life source; offline money-check candidate copied from separately passing draft PR #221; full secure offline journal *not* complete |
| **B2B** | OpFin contracts to supply institution-owned operating software/infrastructure | Independent club treasury, SACCO operations, VSLA cycles, lender servicing, People employer, regulated partner administration | Investment Club capital/treasury, SACCO member/loan administration, independent HR, reporting and migrations | B2B blueprint accepted into main; maturity differs by institution/product; no blanket production acceptance |
| **B2B2C** | Institution contracts with OpFin for a member/customer experience delivered through authorised OpFin channels | Branded or contextual self-service, membership, products, customer servicing, reports | SACCO member portal/app Space; employer staff People; club member statement; approved partner-specific distribution | Financial Space member routes/brand requests/source; fully certified white-label and product-level execution vary |
| **C2B** | An individual proactively asks an institution for a service, value exchange or action | Agency: customer-selected financial request, contribution, premium, purchase finance, savings or other lawful service, with transparent institution and contract | Customer creates a `FinancialIntent` and reviews approved matches; member initiates dues/loan request; insurer quote request | Direct customer `/api/financial-intents` source exists; eligibility, provider decisions and actual money action are independently governed |
| **C2B2B** | An individual gives institution A a specific mandate; A asks separate institution B to supply/execute a contracted capability | Customer-initiated service spanning two businesses with traceable purpose, authority and consent at **each hop** | Person requests finance via merchant/association; first business sends consented request to OpFin; an authorised lender handles actual product. A SACCO member requests eligible partner insurance via SACCO, insurer separately underwrites | Partner-originated `/api/partner/financial-intents/{customer}` and customer `confirm` form a source foundation for **one consented referral → customer decision → independent provider**. Generic arbitrary two-business delegation, multi-hop credential propagation and all financial lifecycles are **NOT yet built or certified** |

**Terminology:** The letters indicate relationships, not custody, lender status, contractual agency or who legally owns assets. One journey may legitimately traverse multiple relationship types at different steps. Assign a separately provable actor and purpose to each transition rather than inferring that a C2B2B request makes both institutions authorised lenders.

## 2. The unified customer journey

### Customer entry without a provider (B2C)

1. Guest education and **ephemeral offline money check** immediately, without mandatory account registration, lender partnership or network.
2. Optional customer registration with a single person identity; Personal Financial Life is the default Space.
3. Record personal income, expenses, goals and obligations with honest `reported / estimated / confirmed / provider-confirmed` provenance.
4. Show financial position, a useful next action, upcoming commitments and meaningful opt-in reminders. Earn reliance through continuity and reliable help; users retain data export, consent controls and ability to disengage.
5. Enter customer-selected appropriate services with actual provider identity, source-specific KYC, capability, jurisdiction, pricing and risk gates; never treat finance approval as an onboarding requirement for planning.

### Customer starts a financial-service relationship (C2B)

1. Customer chooses a purpose, recipient institution or approved eligible marketplace product and a Financial Space.
2. Customer sends a canonical `FinancialIntent` or institution-specific request with bounded fields, actor and purpose, not an automatic accepted financial contract.
3. OpFin discloses available institution/product options, costs, decisions, expiry, human support/decline alternatives and permitted information transfers.
4. Institution accepts/reviews according to its own authority and rules. Any offer and payment remain independently confirmed and journalled.

### Institution runs itself independently (B2B)

1. Contract/KYB → Financial Space setup → membership and roles → optional historical data migration → signed off opening balances and governance rules.
2. Institution works end to end, without needing an OpFin-funded loan or another institution: Investment Club cashbook/NAV, SACCO member services, VSLA cycle, People employee lifecycle, lender servicing and so forth.
3. Institution chooses its own activated providers and approved operational/commercial modules; the platform never forces one partner as a runtime dependency.
4. Institution invoices, support, audit, data export, and service metrics derive from its actual contract and recorded costs.

### Institution delivers to its members (B2B2C)

1. Institution invites or lawfully enrols member in its own Space. Member does not surrender their separate Personal Financial Life.
2. Member changes to that specific Space, sees **only** role/contract/entitlement-approved tasks and accurate product statuses.
3. Institution can use contextual branding or verified domain while legal provider, custody and identity/record ownership remain truthful.
4. Member can exit or change role without another institution or employer acquiring their private history. Own retained history is governed by applicable obligations and access rules.

### Customer-initiated, two-business handoff (C2B2B)

1. Customer explicitly initiates a need in institution A or an approved partner surface, selecting/confirming receiving Space and allowed destination/purpose.
2. Institution A generates a **bounded** referral using its own approved partner contract and proof of customer authorisation; source partner reference and idempotency are mandatory. No unrestricted Personal Space records flow to A or B.
3. OpFin gives the customer a chance to verify, amend supported preferences, confirm or decline the request into an authorised Space.
4. A distinct institution B is selected/accepted only after actual product/provider eligibility, customer disclosure, independent lender/custodian/insurer authority and any additional destination-specific consent.
5. Institution B responds with decision/offer/execution evidence. Each hop has independent status, audit, expiry, commercial allocation, complaint and reversal path.
6. If institution B fails or its outcome is unknown, **preserve original request/reference and reconcile**; never silently replay another financial transaction or re-route sensitive customer data. Institution A sees only contractually permitted referral/servicing status, never hidden underwriting or unrelated personal records.

**Scope caveat:** Current source supports selected customer-confirmed partner finance handoffs, not arbitrary institution-to-institution API fan-out. Build the missing second-hop authorisation only after defining an actually contracted use case; no generic consent token forwarded to a second institution.

## 3. One authoritative technical architecture, five typed relationships

| Layer | Authoritative domain and responsibility | Forbidden shortcut |
| --- | --- | --- |
| Person and Identity | Single OpFin person, authentication, protected KYC, revocable/dated purpose consent, guardian where lawful | Second user ID per business or institution |
| Financial Spaces | Separate Personal, Household, Savings Group, Club, SACCO, Business, Fund and Partner identity/membership context | Group/employer membership opens Personal finances |
| Relationship/intent | Canonical customer `FinancialIntent`, source partner referral with source attribution, permission and time limits, distinct destination institution/service record | Treat referral as application, approval, lender mandate or accepted terms |
| Institution operations | Independent institution-specific member/capital/share/loan/treasury/payroll/claims/portfolio records and approvals | One identical generic financial model for every institution |
| Product Factory | Actual country, institution licence, commercial entitlement, member role, provider/capital and product status | A paid plan or a brand logo confers regulator permission |
| Finance and ledger | Immutable proper journal, correct economic owner, funding/custody, provider finality and reconciliation | Internally approved request equals real funds/cover |
| Provider adapter | Audited external calls via verified Cito capability or certified direct-provider route where permitted; idempotent recovery | Silent failover after ambiguous payment or provider response |
| Engagement | Provider-independent education, planning, Compass, support, opt-in reminders, low-data member journeys | Mandatory lending, fake confirmations or behavioural lock-in |
| Channels | Same authoritative API across Flutter App, Web Workspace, partner API and approved assisted channels | Web-only essential Savings Group operations or offline money finality |
| Commercial and reporting | Real contracts, effective service pricing, costs, tax, attribution, partner shares, source-evidence-based KPIs | Gross principal, premiums, investment capital recorded as OpFin revenue |

### Boundary of one account

One human identity can act as personal customer, Savings Group member, Club official, SACCO member, employee, borrower or investor **through separately authorised Spaces and services**. Each role may have distinct user permissions; financial-company responsibilities are institution-by-institution. Same phone/identity does not create shared consent, a second member's approval rights, a lender mandate or a cross-institution data licence.

### Canonical transaction vs relationship events

A relationship state (`invited, requested, referred, customer_confirmed, institution_review, declined, expired`) is **not** a money state (`quoted, approved, submitted, unknown, provider_confirmed, posted, reconciled, reversed`). Systems must track both without coercing one into the other.

Store/transport the minimum:
- stable actor/Financial Space/institution and exact relationship/product route;
- source system and source ID; purpose and bounded consent recipient, expiry and evidence reference;
- original idempotency/external reference and current status/version;
- optional financial intent amount/currency, separate legal offer/quote and accepted terms;
- real provider-of-record, funding/custody and settlement/reconciliation IDs where such activity is genuinely activated;
- commercial agreement, charge/cost allocation and tax evidence only as known.

Current source fields differ by domain; this is a **logical contract**, not permission to add a parallel identity or ledger table.

## 4. Consumer, institution and service functional completeness

Every OpFin end user keeps the full [B2C capability inventory](OPFIN_B2C_COMPLETE_END_USER_SCOPE.md): personal records, money, budgets, Compass, aspirations, debt, assets, lending, Essentials, Save, Invest/Grow, Protect, payments, documents/statements, Household, Savings Groups, Investment Clubs, SACCOs, People, business, Marketplace, premiums and lawful digital finance where genuinely activated.

Every institution keeps the full [12-type institutional register](OPFIN_INSTITUTIONAL_CAPABILITY_REGISTER.md) and [end-to-end B2B blueprint](OPFIN_B2B_INSTITUTIONAL_OPERATING_BLUEPRINT.md). The menu is role- and Space-aware; the actual institution cycle must work independently from marketing/up-sell.

### Five-mode functional test contract

| Mode | Minimum expected working test | Functional/current classification | Blocker to broader release |
| --- | --- | --- | --- |
| B2C | Guest offline money check calculates scenarios; authenticated user manages permitted Personal life; no fake provider balance | First-value candidate implemented; complete secure offline, finance parity and real-device acceptance incomplete | Cross-channel/device, encrypted journals, full B2C source and provider-specific tests |
| B2B | Active organisation officer creates/preserves/submits a lawful draft onboarding case; ordinary member cannot see KYB; institution operates only its own management records | Institutional onboarding code/control hardening and tests proposed in this combined branch; separate institution products have partial foundations | Full club/SACCO/group/People/regulated acceptance and contracts |
| B2B2C | Institution member views own authorised Workspace and source-labelled statements; wrong Space/ex-member denied | Financial Spaces and group interfaces source-based | Mobile role/access UAT, branded domain evidence and financial-provider activation |
| C2B | Customer creates an authorised `FinancialIntent`; institution/product matching and separate financing application follow governed domain logic | Direct intent, matches/application source exists | Actual licensed products, lender funding, borrower affordability, offer, provider finality |
| C2B2B | Partner submits bounded request; exact customer confirms into selected Space; provider assessment is separate; partner sees limited referral state | Selected partner referral source with confirmation/decline and replay controls exists | Arbitrary second-hop agency, downstream contract, provider certification, receipts and full end-to-end UAT |

**Never call the entire strategy 'fully functional' merely because the five letters fit on a diagram.** Each route is individually accepted as source-implemented, tested, staged, provider-activated and operations-accepted. The product must fail closed only for the unavailable service, not deny lawful planning, records, learning or independent institution operations.

## 5. One commercial and growth model, not five duplicate funnels

- **B2C:** first useful provider-independent session, quality of financial decisions, chosen goal progress, trust, voluntary repeat use and retained customer control. Essential core stays accessible.
- **B2B:** value/renewal of independently useful institutions, verified end-to-end administrative cycles, reconciled accounting, time saved, customer support cost and contribution margin.
- **B2B2C:** activated institutions, authorised member adoption, successfully completed member tasks, service response and member trust, without double-counting a multi-Space person.
- **C2B:** customer-initiated valid intent, informed choice, response time, decision/outcome quality, provider conversion where appropriate, declined/unavailable fairness and support.
- **C2B2B:** correctly consented forwarded cases, confirmation rate, no source/destination privacy leakage, institution B execution time, source-linked settlement/recovery and legally allocated fee per service.

Common reporting dimensions: date, jurisdiction/currency, person (privacy safe), originating institution, receiving institution, Financial Space, product, customer/institution contract, source version, channel, finality state and economic owner. Unknown costs/prices/results stay unknown; stage-to-stage leakage or double-counting across directions is forbidden.

## 6. Shared readiness and compliance conditions

1. Actual institution/legal product/regulator/market authorisation. Evidence submission is **not** verification.
2. The person is the controller of voluntary choices; each institution's legal data-controller/processor responsibilities and rights are documented as applicable.
3. B2B administrative setup ≠ licence for deposits, insurance, custody, payments, capital pooling or lending.
4. B2B2C branding ≠ change of contractual lender, insurer or custodian.
5. C2B authorised intent ≠ offer/credit/marketplace eligibility.
6. C2B2B customer confirmation at first hop ≠ blanket authority for institution B; distinguish referral from destination underwriting and financial execution.
7. Financial statuses distinguish unverified plan/claim and actual provider-issued/settled facts; loss/fee positions are never made up.
8. Security and app performance stay intact: user+Space isolated data, encryption where offline sensitive records are approved, no credential leakage, context-switch hygiene, blocked duplicate money calls.
9. Accessibility and low-data completeness for individuals and groups; Web enhancements, not essential-feature hostage-taking.
10. No arbitrary programme-wide lending/investor growth freeze. Each already authorised service runs under its own funding, consumer, risk and financial controls.
11. No duplicate product businesses and no mandatory dependency on independent third-party apps.

## 7. Integration with existing source and active workstreams

The B2B strategy was merged into `main` through [institutional PR #223](https://github.com/lynelk/OpFin/pull/223). The B2C draft [PR #221](https://github.com/lynelk/OpFin/pull/221) passed its original exact-head CI but diverged from current main. This unified branch **selectively carries its B2C documents, guest money-check mobile UI and tests, and Web financial-priority enhancements forward**, while preserving the main B2B institutional blueprint; it must not claim the old PR is merged.

Relevant implemented control surfaces:
- `apps/api/app/Http/Controllers/Api/FinancingController.php` and `apps/api/app/Services/FinancingService.php`: direct customer Financial Intent → matching → application.
- `apps/api/app/Http/Controllers/Api/PartnerFinancialIntentController.php` and [partner referral contract](../../apps/api/docs/api/PARTNER_FINANCIAL_INTENTS.md): selected authorised partner-originated customer-confirmed request.
- `apps/api/app/Http/Controllers/Api/FinancialSpaceController.php` and `app/Support/FinancialSpaceRolePolicy.php`: one identity, active membership and institution financial permission boundaries.
- `apps/api/app/Http/Controllers/Api/OrganisationJourneyController.php`: owned institutional onboarding and restricted workspace (this branch adds confidentiality/submission protections).
- `apps/api/app/Services/FinancialSpaceActionService.php` and related settlement controls: separately governed money instructions and uncertain provider outcomes.
- `apps/client/lib/offline_money_check_screen.dart`: **temporary, unpersisted provider-independent guest estimate only**, not secure offline finance journal.
- [Complete B2C End User](OPFIN_B2C_COMPLETE_END_USER_SCOPE.md), [Light Offline Contract](../architecture/B2C_LIGHT_OFFLINE_FIRST_CONTRACT.md), [B2B Acceptance](../development/OPFIN_B2B_INSTITUTIONAL_ACCEPTANCE_PROGRAMME.md).

## 8. Unified delivery and acceptance

**P0 technical merge and safeguards:** adopt one canonical doctrine; preserve complete B2C + B2B scopes; port tested first-value journey; protect institutional KYB, preserve draft edits and submission review states; prevent cross-Space access, fake financial finality and unapproved C2B2B service hops. Run unchanged and new API/Web/Flutter tests on **this exact combined commit**.

**P0 operating journeys:** finish Savings Group mobile cycle, Investment Club capital/treasury/valuation/distribution acceptance, SACCO share/saving/lending ledgers and licensed institutional lender lifecycle independently. No one institutional product waits on a non-dependent one.

**P1 dedicated relationship tests:** assure first-party customer intent, customer-confirmed partner referral, member-branded experience, normal/denied institutional onboarding and multi-Space privacy with actual provider contracts. Record exact service-by-service technical, financial, regulatory and commercial evidence.

**P1 full customer health:** complete safe consented financial memory, offline approved storage and sync, financial Compass quality, support, payments/protection/credit/savings/investment journeys and low-end device UAT without shrinking the end-user scope.

**P2 commercial growth and specialised multi-hop:** contract-backed institution B provider integration, lawful customer-specific purpose and fee allocation, API versioning, returns/exceptions, proper institution migration/export, programmes/funds/insurers/payments and more.

### Non-negotiable release gate

No production activation, merge completion, white-label customer migration, real loan, custody, investment payout, insurance issuance, payroll remittance or licence claim without:
- exact-head green CI, security, docs and relevant API/Web/Flutter suites;
- financial test evidence from real or certified providers, immutable ledger/reconciliation and reversals where applicable;
- service-specific legal/regulator/country/provider/mandate acceptance;
- member/customer device and accessibility UAT; audited retention/support and incident recovery;
- commercial owner validation of real contract, cost/revenue and accurate marketing wording.

**Final doctrine:** *One OpFin. One person's durable financial life. Independently complete institutions. Five consented relationship directions. Shared controls, never shared unauthorised data or fabricated money.*
