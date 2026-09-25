# Lending platform implementation — 25 September 2026

Owner decision: Lionel Kabenge. This is the development scope for the current implementation, not a scheduled management review.

## Decisions

OpFin is a Core Synergies product and operates the financial orchestration platform. Independent lenders and Core Synergies, as an affiliated lender, supply credit under their respective authority. Lionel reports that Core Synergies holds an UMRA lending licence; its exact entity details, licence reference, scope and validity must be entered from the actual evidence, not invented by a migration. An affiliated lender uses the same institution, product, decision, funding, ledger and audit lifecycle as an independent lender. No separate partner login is required for platform staff.

## Execution scope

1. Replace hardcoded mobile tenures and blanket UMRA assumptions with country, lender, product and distribution configuration.
2. Identify the actual lender in options, offer disclosures and immutable financial records.
3. Support Google Play, Apple App Store and Huawei AppGallery as distinct channels. Keep product definitions independent of publication eligibility. Record evidence, effective dates, versions and explanations for channel decisions.
4. Add platform-admin controls for affiliated credit: withhold, external-first fallback, or explicitly prioritise affiliated credit. Apply selection consistently to catalogue, automatic routing, manual selection and new offers. Preserve servicing of existing accepted obligations.
5. Restrict affiliated credit management to platform administrators and explicitly delegated operations staff. Apply the same funding provenance, capacity reservation and accounting controls as other lenders.
6. Add lender country/currency/regulatory-basis metadata and document country-pack readiness. Do not imply that Uganda integrations, KYC or payment rails are already certified abroad.
7. Document a national, regional and continental research-to-development process. Convert evidence into prioritised country/product work; no scheduled task is created or expanded for this implementation.

## Acceptance

- A short-tenure product can remain configured while unavailable on a particular store.
- Channel decisions cannot be weakened by changing a client-side filter or accepting an already rejected term directly.
- Missing Huawei/product-class policy produces an explicit review reason, not invented approval.
- Under external-first fallback, an eligible independent lender takes precedence; withhold suppresses affiliated origination; affiliate-first requires an explicit administrator decision.
- Pausing origination does not block repayment or alter existing accepted disclosures.
- Unauthorised operations/customer/partner accounts cannot change deployment strategy or affiliated lender settings.
- Offer lender identity comes from the selected institution; no fallback claims that OpFin itself holds a licence.
- Cross-border configuration does not silently activate unsupported currency/payment/KYC paths.

Implementation and actual validation evidence are recorded in the accompanying delivery note before the pull request is published.

## Delivered in this change

| Work | Implementation | Delivery boundary |
| --- | --- | --- |
| Lender authority and identity | Institution profiles, actual lender disclosure, generic approval evidence and scoped financial policy | Exact Core Synergies evidence must be entered by authorised staff |
| Affiliated deployment | Three strategies, per-loan cap, effective periods, delegated operations access, funding linkage | Withhold is the initial state; no live capital created |
| Product/channel flexibility | Versioned scoped rules, forward schema migration, Google/Apple/Huawei build channel mapping | Store publication remains a separate evidence-backed decision |
| Admin and borrower journeys | Platform lending workspace; lender identity in options and offers; Essentials integration | Mobile binaries require release compile and device UAT |
| Cross-border foundation | Market/currency metadata and activation separation | Foreign country packs and rails remain development backlog |
| Continuous improvement | Evidence-to-change process and regional/continental backlog | No new weekly review or follow-up task |

Execution means code, migration, documentation and tests in the implementation branch today. Lionel subsequently deferred GitHub Actions. Merge and production readiness must therefore be assessed using direct verification and independent financial-control review; unavailable build evidence is not treated as a pass. Validation is recorded in [delivery evidence](../operations/LENDING_DELIVERY_2026-09-25.md).
