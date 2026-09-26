# OpFin Essentials

Status: Product contract with tested authority/closure remediation and outstanding financial acceptance  
Reviewed: 25 September 2026  
Language: English (United Kingdom)  
Authority/closure verification candidate: `e336daf4962cb735d1ff0b2360690b1eaffab83e`

## Current acceptance position

Essentials source capability has been merged, but the whole capability is not financially launch-certified. PR #118 now implements exact Financial Space partner authority, a minimal eligibility response, open-advance account-deletion protection and a shared customer-operation mutex. Its integrated candidate passed 358 tests and 2,362 assertions on both SQLite and PostgreSQL 18. These are source and test results, not a merged/deployed financial-release approval. See [authority and closure](../../apps/api/docs/api/ESSENTIALS_AUTHORITY_AND_CLOSURE.md) and the [25 September verification record](../operations/ESSENTIALS_AUTHORITY_VERIFICATION_2026-09-25.md).

Separate expected-accounting, durable provider-instruction, pending-exposure, full repayment/reversal and capital-mandate acceptance remain open until their own evidence closes them. Serialising an in-flight method does not reserve a provider collection still pending after that method returns. Independent financial approval and production operating acceptance remain required.

The [24 September delivery evidence](../operations/DELIVERY_EVIDENCE_2026-09-24.md) is historical. Its then-failing API/Web checks must not be described as the latest results. Preserve that record and use the later exact-candidate verification record for this remediation. Provider credentials, contracts and certification are additional gates, not the only remaining work.

The original product requirements below remain distinct from later implementation decisions. This authority/closure update does not silently approve a different lender model, selection objective or store-distribution policy; reconcile any separately approved change through the concept/plan record.

## 1. Product position

OpFin remains a financial operating platform with embedded financial services. Essentials is one capability, not a redefinition as a utility lender, rent lender or lending-only application.

Essentials addresses a timing gap for verified household or business essentials using the customer's financial picture, responsible-credit headroom, consented eligibility information, approved third-party lenders and purpose-bound settlement.

OpFin is not the primary Essentials lender under this product contract. Each offer must name the third party supplying credit. OpFin provides the experience, orchestration, servicing, controls, reconciliation and partner integration.

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

Three UGX 200,000 lender lines must not become UGX 600,000 simultaneous capacity. Lender-funding-pending and lender-reversal-pending states must retain exposure until authoritative resolution. The partner-response projection preserves the existing overall ceiling and does not sum lender limits; that projection is not a replacement for complete domain exposure accounting.

## 5. Third-party lender model

Each lender requires an approved partner, active product, regulatory/licensing evidence, eligibility/pricing/term rules, supported categories and an approved funding/decision route. OpFin lender-code/name configurations are prohibited by the intended business model in this contract; a later change needs explicit product, legal and accounting approval. This remediation does not supply or infer that approval.

### Capital-mandate route

An approved active lender-linked mandate must have sufficient unreserved committed capital. Acceptance reserves it; confirmed provider settlement deploys it; confirmed principal repayment restores capacity according to policy. Failed/reversed states require explicit release/reversal evidence.

Mandate-usability and lifecycle acceptance remain separate from this authority/closure remediation. Do not infer that an existing pool or a successful established loan test proves the complete Essentials route works.

### Cito-managed lender route

The lender exposes its approved eligibility/credit-line capability through Cito. OpFin supplies the applicable lender/product reference, profile snapshot and valid credit-processing consent reference. The participating third party remains the lender.

## 6. External data and payments

Cito is the preferred gateway. All gnuGrid services used by OpFin must be accessed through Cito under the current integration direction; a generic direct-adapter fallback does not authorise bypassing that restriction.

CPay is the preferred execution/reconciliation route. Essentials has configuration surfaces for service-account lookup, utility/biller payment, verified-beneficiary payment including rent, lender-repayment collection and transaction-status reconciliation. Do not invent a provider endpoint because a configuration slot exists.

A provider outage must not disable unrelated internal record keeping or account access. A dependent financial action must still preserve honest pending/unavailable state rather than inventing verification or settlement. An ambiguous request must not be silently sent through another provider.

Repayment wallet/phone ownership and contractual allocation must be verified by the applicable policy. A nullable wallet identifier in controller input is not a guarantee that every omission is safe. See the [field-level contract](../../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md).

Genuine certification, credentials, contracts, operating ownership and recovery exercises remain required. The separate internal NIN-evidence reuse capability does not establish a direct NIRA contract or complete every KYC check.

## 7. Financial Space obligations and accounting

Accounts, lines, quotes and advances must resolve to the correct Space. The intended settlement lifecycle creates the appropriate third-party-lender obligation and reduces it through confirmed repayments, settling it when fully paid.

An obligation record is not a substitute for expected immutable accounting or canonical payment evidence. Accounting, obligations, provider finality and reconciliation must agree before financial acceptance. The current authority/closure change does not create the remaining lifecycle controls by itself.

PR #118 includes pending, active, overdue, unknown and inconsistent settled Essentials states in account-deletion checks. Pending collections also preserve servicing access. A consistently settled zero balance can proceed through the existing closure process. The shared customer mutex serialises covered Essentials mutation methods and deletion without wrapping a provider call in a new database transaction. Its PostgreSQL fixture tests are described in the [verification record](../operations/ESSENTIALS_AUTHORITY_VERIFICATION_2026-09-25.md).

## 8. Rental finance

Capture the tenancy/landlord or property reference, landlord/property-manager identity, beneficiary name and payment channel, then obtain operations verification. The customer does not receive rental cash. Approved settlement uses the verified beneficiary. Limits, eligibility, pricing and tenures remain product-configurable and subject to applicable approval.

## 9. SME and embedded distribution

Stolets and other approved platforms may distribute Essentials without becoming part of OpFin's merchant-operations domain. They require due diligence, an active distribution account, product authorisation and a customer-granted permission for the exact target Space.

Customer scopes are `eligibility`, `account_write`, `quote_create` and `status_read`. API access alone does not authorise creating debt. Quote completion requires separate short-lived customer authorisation after disclosure review. Revoke permissions when authority ends.

The revised partner routes resolve an omitted Space to the existing Personal Space, not all Spaces or whichever grant is available. Active membership and the exact grant are both required. Eligibility responses exclude account/debt collections and raw decision snapshots; status is scoped to the resolved Space. Negative tests cover other-Space grants, removed members, deleted customers and service-account relocation. A `programme_partner` aggregate-reporting account is not an interchangeable `partner_api` credential. Independent approval and runtime rollout remain separate from the passing tests.

## 10. Offer selection and commercial economics

The intended selection in this product contract favours the eligible category/channel offer with the lowest total customer repayment, not OpFin commission. Validity, expiry, consent, headroom and lender funding must all hold. Any later approved routing priority must be explicitly reconciled with this requirement rather than silently changing the document to match implementation.

Possible contracted economics include lender origination/servicing revenue, biller commissions, platform distribution arrangements and API servicing fees. Customer interest/fees follow the approved lender product and active pricing policy. Platform revenue is separate; principal and pass-through provider amounts are not automatically revenue.

A configurable commercial agreement does not prove live pricing or settled revenue. This authority/closure change does not independently certify the broader commercial or lender-routing implementation.

## 11. Store-distributed credit

The earlier repository store-channel policy recorded at this contract's original baseline uses at least 61 days for full repayment and prefers eligible 90-day-plus terms. Product/legal classification and the actual submitted distribution channel must be verified against the currently approved policy. Test omitted/spoofed channel inputs as well as ordinary Android requests; a client field must not bypass a required policy.

This document does not independently certify current store or legal compliance, nor does this remediation silently change the original tenure requirement. A short loan must not be renamed to evade a distribution rule.

## 12. State handling

Account verification includes pending, pending manual review, verified and failed states. Lines have validity/expiry controls; quotes have offered, accepted and expired states.

Advance handling must distinguish reservations, lender funding, provider fulfilment, active/overdue debt, settlement, failures and reversals. Integrations must not ignore `lender_funding_pending` or `lender_reversal_pending` because an earlier short list omitted them. Repayment handling distinguishes pending/provider-confirmation, successful and failed records.

Treat this as a lifecycle explanation, not an exhaustive generated enum. Read the current service state and provider references. A 201 acceptance/repayment response is not economic finality. A busy-customer 409 means inspect the original instruction before retrying, not request a new economic action with another key.

## 13. Required security and financial controls

Acceptance requires no cash-out; verified beneficiaries; named lender and immutable disclosure hash; explicit customer confirmation; overall headroom; locked capital reservations and collection limits; payload-bound idempotency; expected balanced immutable accounting; encrypted sensitive account evidence and masked normal responses; exact-Space partner scopes; pending-state reconciliation; correct obligation/repayment synchronisation; lawful deletion handling; attributable audit events; and redaction of tokens/secrets from provider evidence.

These are requirements, not a claim that every control has passed. Sequential replay tests do not prove all concurrency cases, and a successful response does not replace provider reconciliation. The PostgreSQL customer mutex uses a direct or session-affine writer connection; transaction-pooling changes require renewed design and acceptance.

## 14. API reference

The registered customer, partner and operations maps and inspected field/status contracts are maintained in [Current capability contracts](../../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md), linked from the [API index](../../apps/api/docs/README.md). The [authority/closure supplement](../../apps/api/docs/api/ESSENTIALS_AUTHORITY_AND_CLOSURE.md) records the smaller partner eligibility response and customer-conflict handling.

Use `python3 scripts/search-api.py "essentials"` from repository root with local API dependencies installed to inspect actual registration. A route index is not a substitute for request validation, ownership, retry or accounting tests.

## 15. Production acceptance sequence

First close the remaining internal findings with candidate-specific tests and required independent financial approval. Then verify the named participating lender's legal evidence, approved product/pricing, usable funded mandate or certified Cito route, certified CPay lookup/payment/repayment/reconciliation, verified-beneficiary rent settlement, signed biller agreements, approved customer disclosures, recovery/reconciliation exercises, assigned operations owners, server-enforced store policy and operational monitoring/complaints/audit.

Affected financial routes must remain unactivated until internal acceptance and external gates are satisfied. A merged source commit or updated manual is not activation approval. See the [concept comparison](CONCEPT_AND_PLAN_COMPARISON.md) and [manual supplement](../manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) for priorities and safe training boundaries.
