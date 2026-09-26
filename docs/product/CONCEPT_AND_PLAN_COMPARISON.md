# OpFin: achievements against the concept and delivery plan

Status: Management and implementation comparison  
Reviewed: 24 September 2026  
Language: English (United Kingdom)  
Implementation baseline: `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`

## Executive assessment

OpFin has progressed from the integrated financial-wellbeing concept to a substantial multi-channel implementation: the canonical API/Web/Flutter monorepo, Financial Spaces, financial-life and responsible-credit services, inclusive-finance programme delivery, commercial reporting, investment-club treasury and statements, and Essentials lender orchestration.

The overall plan is not fully accepted. Three categories remain separate: internal software/control defects; end-to-end, device and operational acceptance not demonstrated; and genuine external provider/legal/commercial activation. Essentials was merged with unresolved financial-control findings. The latest observed API and Web deployment attempts failed their build gates.

No completion percentage is assigned. The plans do not supply a consistently weighted accepted-requirements denominator. Counting routes, files or passing tests would not measure completed customer journeys or business outcomes.

## 1. Sources and interpretation

**C2:** `OpFin_Updated_Concept_Note_v2.md`, OpFin Integrated Financial Wellbeing & Access Ecosystem, version 2.0, 20 August 2026, reference implementation OP44. Reviewed areas include principles, customer journey, product modules, channels, accounting/operations, revenue, delivery phases and success measures.

**B5:** `OpFin_CPay_Full_Build_Specification_and_Implementation_Instructions_v5.md`, OpFin + CPay Build Specification, Implementation Instructions & Full Development Roadmap, version 5. Reviewed areas include the OpFin/CPay boundary, Financial Compass, Spend & Budget, Financial Calendar, Money Autopilot, Financial Passport, Credit Builder, Borrow, Financial Shock Centre, Repayment & Hardship, Save, Protect, Grow, Bills, Household, Microbusiness, routing, security and data rights.

The owner's supplied library documents are the original comparison basis. Their private originals are not reproduced in this repository. Filenames and section names identify the source; a current implementation does not retrospectively change what the original concept requested.

The [Product Blueprint](OPFIN_PRODUCT_BLUEPRINT.md), [programme framework](INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md), [partner reporting standard](PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md), [Essentials specification](OPFIN_ESSENTIALS.md) and [club treasury specification](INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md) describe subsequent decisions. Those are identified below as evolution or extension.

### Evidence language

Implemented means source support exists, not that every path passes. Partial means some requested scope exists but full acceptance is not established. Review-blocked means unresolved findings prevent acceptance of the affected path. Activation-dependent means genuine external arrangements are needed. Not established means this review lacks sufficient evidence; it does not prove absence.

Named build results and deployment identifiers are in the [dated evidence record](../operations/DELIVERY_EVIDENCE_2026-09-24.md). Old successful releases remain evidence for their own commits only.

## 2. Original delivery phases

| Original C2 phase | Original intent | Achievements in current implementation | Acceptance still required |
| --- | --- | --- | --- |
| Phase 1: Financial foundation | Identity/profile/KYC/consent, credit/risk, budgeting, financial health, origination/servicing, payments, CRB, savings foundations, admin/audit | Canonical services and client surfaces exist. The observed API build executed 263 tests, with 257 passing across established domains. | Six tests failed. Existing loan-path assurance does not automatically cover Essentials. Exact-release financial integrity, provider and customer acceptance remain required. |
| Phase 2: Financial wellbeing expansion | Linked accounts, savings automation, employer benefits, insurance, investment partners, improved recommendations, rewards/referrals | Financial-life and enrichment services, employer/partner foundations and savings/protection lifecycles exist. Programme delivery and commercial reporting extend the concept. | Live feeds, each provider's full lifecycle, automated customer rules and settled rewards/referrals require evidence. This review does not certify their production outcomes. |
| Phase 3: Marketplace and asset ecosystem | P2P, secondary markets, group/ROSCA, advanced asset/device finance, GPS/geofencing and partner marketplace | Community/long-range foundations and catalogue exist. Club treasury adds cashbook, CSV import/reconciliation and issued statements. Essentials adds bill/rent lender orchestration. | Club capital ledgers, NAV/unitisation, distributions and full investment performance are not supplied by treasury alone. Secondary-market/asset-control acceptance is not established. Essentials remains review-blocked. |
| Phase 4: Intelligence, scale and portability | Fraud analytics, personalisation, improved modelling, partner telemetry, regional scale, Base44 independence, mature reconciliation and high-volume operations | Laravel/Next.js/Flutter source, separate runtime services, deterministic services and commercial/programme analytics show material portability progress. | Latest API/Web builds failed. Exact-version production parity, recovery, independent security, load/SLO, regional-policy and sustained business-outcome evidence remain necessary. |

The original concept permits later-phase code before a phase is operationally mature. A later feature merge therefore does not certify earlier phases.

## 3. Capability comparison

| ID | Original requirement | Achievement and assessment | Next completion or proof |
| --- | --- | --- | --- |
| CP-01 | Financial progression, not credit dependency | Current proposition aligns Manage, Save, Protect, Borrow and Grow around one financial picture. Essentials is a capability, not the whole product. | Verify actual Home/navigation and activated services preserve this intent. |
| CP-02 | One identity, progressive verification and consent | Phone/OTP/names/PIN and Financial Spaces distinguish identity, membership, role, capability, entitlement and eligibility. | Release-level onboarding, recovery, consent and cross-Space denial evidence. |
| CP-03 | Financial Compass, budgets, calendar and financial position | Financial-life/wellbeing routes provide recorded accounts, assets, obligations, receivables, budgets and calendar foundations. | Demonstrate all original forecast/category jobs, incomplete-data behaviour and complete customer workflows. |
| CP-04 | Financial Passport; health separate from credit risk | Attributable credit components, non-score reputation and wellbeing snapshots are distinct; programme measurement remains outside underwriting. | Source-ageing, correction, purpose and explanation evidence for each provider/model. |
| CP-05 | Responsible credit, affordability, disclosures, hardship | Established loan, repayment and UMRA implementations have observed passing regression evidence. Essentials adds third-party lender quotes. | Full hardship outcomes and new-path acceptance; resolve Essentials accounting and reservation findings. |
| CP-06 | Money Autopilot and Financial Shock Centre | Operations/experience/long-range services exist, but all personal-money allocation rules and non-credit shock alternatives were not established by this review. | Map each rule to consent, UI, versioned policy, execution, pause/override and recovery tests. A scheduler is not proof of complete customer autopilot. |
| CP-07 | Savings pockets, goals and automation | Savings goal/movement and partner-custody lifecycles exist with observed tests. | Activated partner custody, withdrawal and recovery; all planned automation rules and customer outcomes. |
| CP-08 | Protection, policy explanations and claims | Policy/premium/claim and partner-confirmation mechanisms exist. | Genuine insurer activation, coverage/exclusion wording, renewal and claim/dispute acceptance. |
| CP-09 | Staged investment and advanced market access | Suitability/order and long-range foundations are documented. | Real custody/settlement, redemption, corporate actions, secondary markets and investor suitability evidence; no live-return claim follows from code. |
| CP-10 | Bills and recurring payments | Essentials implements a purpose-bound financing path for utility/service bills and rent with a named third-party lender. | Internal control closure, biller/rental verification, lender funding, collection, reversal and reconciliation evidence before activation. |
| CP-11 | Household/group privacy and delegated authority | Financial Space roles and membership foundations exist. | Exact-Space Essentials partner grants and all negative authorisation scenarios; a grant in one Space must not authorise another. |
| CP-12 | Group/club financial management | Treasury accounts, cashbook, statement import/review, frozen statements and separate-currency views materially extend group finance. | Member capital, calls/schedules, unitisation/NAV, distributions, valuation/performance and member statements remain distinct scope. |
| CP-13 | Complete normal Individual/Group mobile journeys | App supports financial-life and statement access; detailed treasury import/reconciliation remains a deeper Web workflow. | Accept the channel boundary explicitly or complete essential mobile administration; do not claim every group action is already mobile-complete. |
| CP-14 | Employer services with a minimal employment-data boundary | Employer capabilities and positive-only behaviour enrichment exist. | C2 excludes routine performance, disciplinary and attendance information. Reconcile permitted fields/purposes explicitly; a neutral-negative rule alone does not prove compliance with the original boundary. |
| CP-15 | Microbusiness/side-hustle financial needs | Current architecture separates OpFin financial wellbeing from Stolets merchant operations. | Treat POS, inventory and purchasing as separate-product functions, not achieved OpFin modules. Record this scope evolution against B5 Microbusiness requirements. |
| CP-16 | CPay canonical execution infrastructure | Cito/CPay preference and explicit certified direct adapters are current decisions. Essentials has dedicated Cito lending/CPay clients. | B5 sought to avoid duplicating a gateway inside OpFin. Preserve an explicit architecture decision and canonical intent/accounting/reconciliation on every route; Essentials findings prevent unconditional conformance claims. |
| CP-17 | Inclusive channels, accessibility and poor-connectivity support | App/Web/verified WhatsApp/USSD/assisted mechanisms, accessibility preferences and reviewed translation/fallback controls exist. | Physical-device, low-literacy, interrupted-connectivity and language acceptance; configured language slots are not completed translations. |
| CP-18 | Data rights and account deletion | Established consent/deletion framework exists. | Current Essentials review says open advances are absent from deletion obligations. Prove pending/active/overdue and settled cases before extending deletion assurances. |
| CP-19 | Immutable accounting, exact replay and reconciliation | Established production-ledger, loan and savings/protection services exist. | Essentials activation/repayment lacks required accounting according to the current review; concurrent collections and pending exposure also require closure. Never create balancing entries solely to silence an exception. |
| CP-20 | Complaints, governance and regulatory evidence | Consent/reporting, complaints, guarantor controls, NPL/default-interest, receipts and governed changes have observed regression evidence. | Verify the actual licensed entity/product and operational process; generated evidence is not external filing. |
| CP-21 | Inclusion and measured outcomes | Programme instruments, follow-ups, dedicated partners, suppressed CSV/XLSX/ZIP exports and non-credit measurements are a substantial subsequent extension. | Real programme instruments, agreements, field validation and an evaluation design for any causal claims. |
| CP-22 | Commercial sustainability | Attribution, costs, revenue/service economics, partner reports and graduation analytics provide measurement infrastructure. | Reconcile operating revenue/cost/settlement datasets. No profitability, inclusion outcome or portfolio-quality figure was established here. |
| CP-23 | Accessible developer documentation and contracts | Root/API references, route search, publication checks and manuals exist. | Field-level schemas, permissions, examples and recovery must remain complete and tested. A route list or placeholder scan is not exhaustive API certification. |
| CP-24 | Reliable production and portability | Separate application services and a canonical source repository exist. | Restore API/Web builds and prove running-version parity, migrations, health, heartbeats and recovery. One successful service does not mean the release is aligned. |

## 4. Material evolution from the original concept

Repository consolidation replaces the former separate-repository/Base44 delivery context with `apps/api`, `apps/web` and `apps/client`. This is evolution, not missing compliance with an obsolete repository name.

Essentials places credit with the named participating lender. This does not independently determine the legal lender for every legacy product. gnuGrid access remains Cito-only under the current Essentials direction; a general direct-provider fallback rule must not override that specific restriction.

The original payment-infrastructure boundary and later direct-adapter policy need an explicit architecture decision, not an assertion of unchanged literal conformance. Likewise, positive-only employer enrichment requires reconciliation with the original minimal-employment-data exclusions.

Microbusiness financial wellbeing remains relevant, while merchant operating functions belong to Stolets or another separate platform. Programme delivery and club treasury are later extensions; they do not close unrelated secondary-market, asset-finance or scale requirements.

## 5. Recommended next delivery sequence

These priorities are this review's recommendation, not the original plan's wording.

| Priority | Work package | Accountable function | Closing evidence |
| --- | --- | --- | --- |
| R0 | Restore API/Web build reliability | Engineering/release | Existing failures explained and corrected without removing tests or guards; candidate-specific results retained |
| R1 | Close Essentials financial controls | Backend, Finance, Risk, independent reviewer | Expected ledger events, canonical intent, exact-Space grants, locked collections, deletion obligations, pending exposure and usable capital mandates |
| R2 | Accept treasury history and statements | Finance/backend/operations | Stable opening baseline, historical import/cashbook correctness, one-to-one matching, explicit exceptions and immutable currency-separated statements |
| R3 | Prove channel completeness | Product/QA/support | Screen-level happy/failure/recovery tasks, devices, accessibility, offline boundaries and permission denial |
| R4 | Activate contracted services | Partnerships/Compliance/Operations | Genuine providers, agreements, configured capabilities, approved terms and controlled settlement/recovery exercises |
| R5 | Complete wider phase-2/3 scope | Product/engineering | Remaining original jobs and club extensions delivered as accepted vertical slices |
| R6 | Demonstrate outcomes and scale | Management/Finance/data/platform | Adoption, wellbeing, portfolio, service-level and commercial evidence; load/recovery and regional acceptance |

## 6. Success measures not yet established by this review

C2's success measures cover registration/profile/KYC completion, monthly active use, linked-account activation, multi-product use and retention; savings growth, goals, emergency funds and debt resilience; approval quality, first-payment default, PAR30/PAR90, losses, recoveries and hardship; employer activation; provider reliability, settlement, complaints, CRB disputes, ledger exceptions and service levels; revenue, contribution, acquisition cost, lifetime value and cost per active customer.

This review examined source, documentation and build/deployment evidence, not a reconciled operating business dataset. No numerical commercial or customer-wellbeing outcome is asserted. The presence of a reporting endpoint is progress towards measurement, not proof of the measured outcome.

## 7. Maintenance

Keep original requirements, later decisions, implementation evidence and acceptance results separate. Update an assessment only when new source or accepted evidence supports it. A defect is not authority to silently rewrite the requirement. Historical records retain their dates and are not retrospectively certified by this review.
