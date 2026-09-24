# OpFin Essentials

Status: Product and implementation contract with unresolved acceptance findings  
Reviewed: 24 September 2026  
Language: English (United Kingdom)  
Implementation baseline: `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`

## Current acceptance position

The Essentials source capability has been merged, but it is not financially launch-certified. The merge retained unresolved control findings. Required immutable accounting, exact Financial Space partner authority, concurrent repayment protection, open-advance account deletion, pending lender-funding/reversal exposure and usable approved capital mandates require verification/remediation.

The [delivery evidence](../operations/DELIVERY_EVIDENCE_2026-09-24.md) records those findings and current API/Web build failures. The sections below define the product model, implemented source surface and required controls. They must not be read as proof that every control already passes. External credentials, contracts and provider certification are additional gates, not the only remaining work.

## 1. Product position

OpFin remains a financial operating platform with embedded financial services. Essentials is one capability, not a redefinition as a utility lender, rent lender or lending-only application.

Essentials addresses a timing gap for verified household or business essentials using the customer's financial picture, responsible-credit headroom, consented eligibility information, approved third-party lenders and purpose-bound settlement.

OpFin is not the primary Essentials lender. Each offer must name the third party supplying credit. OpFin provides the experience, orchestration, servicing, controls, reconciliation and partner integration.

## 2. Categories and settlement

| Category | Initial examples, subject to actual approval/activation | Required settlement boundary |
| --- | --- | --- |
| Electricity | UEDCL and approved electricity billers | Verified meter/account; provider payment |
| Water | NWSC and approved water billers | Verified account; provider payment |
| Internet | Approved fixed/mobile internet billers | Verified subscriber; provider payment |
| Television | DStv, GOtv, StarTimes and approved billers | Verified subscriber; provider payment |
| Household energy | Approved LPG/energy providers | Verified provider/customer reference |
| Rent | Verified landlord/property beneficiary | Reviewed rental/beneficiary evidence and purpose-bound beneficiary payment |

These names are catalogue examples, not evidence of active commercial arrangements. New categories use governed configuration. Cash-out is not an Essentials fulfilment route.

## 3. Intended customer journey

Capture the service account or rental beneficiary; verify it or await manual rental review; refresh approved lender eligibility; request an amount for a specific verified essential; review lender identity, provider amount, interest, fees, total repayment and term; explicitly accept or reject; track authoritative provider fulfilment; repay through the approved verified-wallet/phone path; view the obligation in the relevant Space; and control/revoke embedded-platform permissions.

Ordinary borrowing, savings, growth, protection, employer and Compass journeys remain intact. A source UI or this guide is not authorisation for live financial execution before acceptance.

## 4. Overall responsible-credit headroom

Eligible lender limits are not added together. Availability must be bounded by overall OpFin headroom and a lender line capable of serving the requested category. All outstanding and reservation-bearing Essentials states must reduce headroom appropriately.

Three UGX 200,000 lender lines must not become UGX 600,000 simultaneous capacity. Current review specifically requires lender-funding-pending and lender-reversal-pending states to retain exposure until authoritative resolution.

## 5. Third-party lender model

Each lender requires an approved partner, active product, regulatory/licensing evidence, eligibility/pricing/term rules, supported categories and an approved funding/decision route. OpFin lender-code/name configurations are prohibited by the intended business model; a later change needs explicit product, legal and accounting approval.

### Capital-mandate route

An approved active lender-linked mandate must have sufficient unreserved committed capital. Acceptance reserves it; confirmed provider settlement deploys it; confirmed principal repayment restores capacity according to policy. Failed/reversed states require explicit release/reversal evidence.

The current review identifies mandate-usability and lifecycle acceptance work. Do not infer that an existing pool or a successful established loan test proves the Essentials route works.

### Cito-managed lender route

The lender exposes its approved eligibility/credit-line capability through Cito. OpFin supplies the applicable lender/product reference, profile snapshot and valid credit-processing consent reference. The participating third party remains the lender.

## 6. External data and payments

Cito is the preferred gateway. All gnuGrid services used by OpFin must be accessed through Cito under the current integration direction; a generic direct-adapter fallback does not authorise bypassing that restriction.

CPay is the preferred execution/reconciliation route. Essentials has configuration surfaces for service-account lookup, utility/biller payment, verified-beneficiary payment including rent, lender-repayment collection and transaction-status reconciliation. Do not invent a provider endpoint because a configuration slot exists.

Repayment wallet/phone ownership and contractual allocation must be verified by the applicable policy. The current customer controller allows a nullable wallet identifier; that is not permission to assume an unsafe fallback is acceptable. See the [field-level contract](../../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md).

A missing required provider path must fail closed. An ambiguous request must remain pending/error until reconciled rather than silently executing again through another provider. Genuine certification, credentials, contracts, operating ownership and recovery exercises remain required.

## 7. Financial Space obligations and accounting

Accounts, lines, quotes and advances must resolve to the correct Space. The intended settlement lifecycle creates the appropriate third-party-lender obligation and reduces it through confirmed repayments, settling it when fully paid.

An obligation record is not a substitute for expected immutable accounting or canonical payment evidence. Current review says new activation/repayment transitions lack the required ledger events. Accounting, obligations, provider finality and reconciliation must agree before financial acceptance.

Account-deletion checks must include pending, active and overdue Essentials obligations. Deleting optional customer context must not orphan a live financial obligation or remove necessary repayment/servicing access.

## 8. Rental finance

Capture the tenancy/landlord or property reference, landlord/property-manager identity, beneficiary name and payment channel, then obtain operations verification. The customer does not receive rental cash. Approved settlement uses the verified beneficiary. Limits, eligibility, pricing and tenures remain product-configurable and subject to applicable approval.

## 9. SME and embedded distribution

Stolets and other approved platforms may distribute Essentials without becoming part of OpFin's merchant-operations domain. They require due diligence, an active distribution account, product authorisation and a customer-granted permission for the exact target Space.

Customer scopes are `eligibility`, `account_write`, `quote_create` and `status_read`. API access alone does not authorise creating debt. Quote completion requires separate short-lived customer authorisation after disclosure review. Revoke permissions when authority ends.

Current review identifies a gap where omitted/different Space context can satisfy authority from another Space. Exact target resolution and negative tests are acceptance requirements. A `programme_partner` aggregate-reporting account is not an interchangeable `partner_api` credential.

## 10. Offer selection and commercial economics

The intended selection favours the eligible category/channel offer with the lowest total customer repayment, not OpFin commission. Validity, expiry, consent, headroom and lender funding must all hold.

Possible contracted economics include lender origination/servicing revenue, biller commissions, platform distribution arrangements and API servicing fees. Customer interest/fees follow the approved lender product and active pricing policy. Platform revenue is separate; principal and pass-through provider amounts are not automatically revenue.

Current source logic and later acceptance tests must verify these requirements; a configurable commercial agreement does not prove live pricing or settled revenue.

## 11. Store-distributed credit

The repository's current store-channel policy uses at least 61 days for full repayment and prefers eligible 90-day-plus terms. Product/legal classification and the submitted distribution channel must be verified. Test omitted/spoofed channel inputs as well as ordinary Android requests; a client field must not bypass a required policy.

This document does not independently certify current store or legal compliance. A short loan must not be renamed to evade a distribution rule.

## 12. State handling

Account verification includes pending, pending manual review, verified and failed states. Lines have validity/expiry controls; quotes have offered, accepted and expired states.

Advance handling must distinguish reservations, lender funding, provider fulfilment, active/overdue debt, settlement, failures and reversals. Current review specifically references `lender_funding_pending` and `lender_reversal_pending`; integrations must not ignore them because an earlier short list omitted them. Repayment handling distinguishes pending/provider-confirmation, successful and failed records.

Treat this as a lifecycle explanation, not an exhaustive generated enum. Read the current service state and provider references. The customer controller's 201 acceptance/repayment response is not an assertion of economic finality.

## 13. Required security and financial controls

Acceptance requires no cash-out; verified beneficiaries; named lender and immutable disclosure hash; explicit customer confirmation; overall headroom; locked capital reservations and collection limits; payload-bound idempotency; expected balanced immutable accounting; encrypted sensitive account evidence and masked normal responses; exact-Space partner scopes; pending-state reconciliation; correct obligation/repayment synchronisation; lawful deletion handling; attributable audit events; and redaction of tokens/secrets from provider evidence.

These are requirements, not a claim that every control has passed. Sequential replay tests do not prove concurrent repayment safety, and a successful response does not replace provider reconciliation.

## 14. API reference

The registered customer, partner and operations maps and inspected field/status contracts are maintained in [Current capability contracts](../../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md), linked from the [API index](../../apps/api/docs/README.md). They include service accounts, eligibility, quotes, quote authorisation/acceptance, advances/repayment, platform permission revocation, operator queues and reconciliation.

Use `python3 scripts/search-api.py "essentials"` from repository root with local API dependencies installed to inspect actual registration. Do not substitute a route index for request validation, ownership, retry or accounting tests.

## 15. Production acceptance sequence

First resolve the current internal findings and failing release checks with candidate-specific tests and required independent financial review. Then verify the named non-OpFin lender's legal evidence, approved product/pricing, usable funded mandate or certified Cito route, certified CPay lookup/payment/repayment/reconciliation, verified-beneficiary rent settlement, signed biller agreements, approved customer disclosures, recovery/reconciliation exercises, assigned operations owners, server-enforced store policy and operational monitoring/complaints/audit.

Affected financial routes must remain unactivated until both internal acceptance and external gates are satisfied. A merged source commit or updated manual is not activation approval. See the [concept comparison](CONCEPT_AND_PLAN_COMPARISON.md) and [manual supplement](../manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) for delivery priorities and safe training boundaries.
