# OpFin current state

Status: Current product and delivery evidence index  
Reviewed: 24 September 2026  
Language: English (United Kingdom)  
Implementation baseline: `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`

## Assessment

OpFin remains a financial operating platform with embedded financial services, not a lending-only application. Current source contains identity and consent, Financial Spaces, financial-life services, responsible credit, provider-gated savings/protection/investment foundations, inclusive-finance programmes, commercial reporting, investment-club treasury/statements and Essentials third-party lender orchestration.

Implementation is not the same as accepted customer delivery. The latest observed API and Web deployment attempts failed build checks. Essentials was merged with explicitly unresolved financial-control review findings. Neither a documentation publication label nor the presence of a route certifies that a feature is safe for financial activation.

Read the [concept and plan comparison](product/CONCEPT_AND_PLAN_COMPARISON.md) for the original requirements, achievements, changed scope and remaining acceptance. Read the [dated delivery evidence](operations/DELIVERY_EVIDENCE_2026-09-24.md) for test counts, deployment identifiers and review findings.

## Achievements and boundaries

| Area | What the current source provides | Boundary that remains important |
| --- | --- | --- |
| Canonical platform | Laravel API, Next.js Web and Flutter client in one repository; separate API, worker and scheduler responsibilities | A common repository does not prove identical running versions or complete cross-channel acceptance |
| Identity and Financial Spaces | Phone/OTP/names/PIN onboarding; consent; memberships, roles and personal/household/group/organisation contexts | A membership must not expose private Personal Space data; exact-Space partner authorisation remains a current Essentials review finding |
| Financial wellbeing and credit | Financial-life records, budgeting/goals foundations, composite credit profile, affordability, offers, repayment and established ledger/reconciliation services | Financial health and programme measurement are not credit scores; the established loan path's accounting assurance must not be assumed for a new financial path |
| Inclusive-finance programmes | Instruments, follow-ups, reviewed localisation, assisted capture, dedicated programme-partner access and privacy-suppressed exports | Real programme agreements, reviewed questionnaires/translations and field acceptance remain separate from source implementation |
| Commercial evidence | Acquisition attribution, cost/revenue and service-economics reporting; programme-to-commercial analytics | Unknown amounts remain unknown; customer capital is not revenue; a dashboard does not establish profitability |
| Investment clubs and treasury | Treasury accounts and cashbook, mapped CSV statement imports, reconciliation review/confirmation, frozen statements and currency-separated reporting | This is not complete member-capital, unitisation/NAV, distribution or investment-performance accounting. Historical-date/baseline regression failures currently block API build acceptance |
| OpFin Essentials | Named third-party lender quotes, purpose-bound bill/rent settlement, Cito lending and CPay clients, partner permissions and servicing surfaces | Internal accounting, deletion, authorisation, concurrency and reservation findings remain unresolved. Do not describe Essentials as financially launch-certified |
| Channels and accessibility | App/Web and assisted-channel mechanisms, accessibility preferences and explicit English fallback | The club CSV import/reconciliation workflow is deeper on Web. Full mobile completeness, physical-device accessibility and every supported channel require acceptance evidence |

## Customer and provider rules

New App customers follow phone verification, OTP, names and a six-digit PIN, then progressive verification appropriate to the selected activity. Existing Web password-compatible access does not redefine this onboarding contract.

OpFin owns its product/customer state and financial evidence. Cito is the preferred third-party gateway, CPay the preferred payment route, and explicitly certified direct-provider adapters remain governed exceptions. The current Essentials direction requires gnuGrid access through Cito; a general fallback policy does not authorise direct gnuGrid integration.

Essentials financing belongs to the named participating lender. A successful lender or provider request must not be presented as settled money, an issued utility token or confirmed repayment before its authoritative final state. Pending lender funding and reversal states must continue reserving exposure until safely resolved.

Stolets remains a separate SME operating product. Shared infrastructure and consented integration do not make POS, inventory or merchant operations part of OpFin.

## Deployment snapshot

For baseline `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`, the observed production API build failed with six tests failing and 257 passing. The Web build compiled but failed TypeScript checking. Worker and scheduler deployment records reported success. This is not a fully aligned production release.

The latest successful API deployment returned by the reviewed production query belonged to earlier commit `aa19a53481b2094530616340b8f54aaaabfc6172`. A successful historical deployment record is not a new live-health check. No new infrastructure or provider activation is implied by this documentation update.

GitHub Actions remains disabled under the owner's instruction. Retain equivalent candidate-specific local/build, security and operational evidence; do not re-enable Actions or call missing evidence a pass. Production updates must preserve the existing test, audit, migration, health and reconciliation gates.

## Publication and maintenance

The [user](manuals/OPFIN_USER_MANUAL.md), [training](manuals/OPFIN_TRAINING_MANUAL.md), [operational](manuals/OPFIN_OPERATIONAL_MANUAL.md) and [UAT](manuals/OPFIN_UAT_MANUAL.md) manuals must be read with the [current capability supplement](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md). API consumers should use the [new-capability contract](../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md) with the existing endpoint and client references.

Original concept requirements, later approved decisions, observed implementation and acceptance evidence are different authorities. A code defect does not authorise silently rewriting the original requirement. Historical audit, migration and release records retain their original dates; this review does not retrospectively certify them.

Remaining work includes internal defects, missing acceptance evidence and external activation. Provider credentials, legal approvals, programmes, translations, app-store publication and device certification are external gates only where the underlying implementation already satisfies its own controls.
