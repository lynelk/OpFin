# Essentials partner authority and account closure

Status: Implemented remediation candidate; independent financial and production acceptance pending  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

## Evidence and scope

This document records the current source contract in PR #118, not a completed financial launch. The original candidate `f6173ebc0429190756554cd30d009fdf40937395` passed an isolated API build with 274 tests and 1,927 assertions. Subsequent independent review identified eligibility-response leakage and account-closure lock ordering; that earlier result did not close those findings.

The integrated candidate `085f4da2b79b6fe6ca9a58146de94cd90cb2ce85` added the response projection and customer mutex. Its full run had 350 passing tests and one failure in an older privacy mock that returned null where the current service contract requires an authorisation model. The revised fixture preserves the grant-first and identical-denial assertions. Exact later candidate results are recorded in the PR; neither test definitions nor this document represent unexecuted checks as passed.

The current [product specification](../../../../docs/product/OPFIN_ESSENTIALS.md), [capability contracts](CURRENT_CAPABILITY_CONTRACTS.md) and [manual supplement](../../../../docs/manuals/CURRENT_CAPABILITY_SUPPLEMENT.md) remain correct to withhold overall financial acceptance. Source remediation, independent approval, production-database behaviour and provider activation are separate requirements.

## Exact Financial Space authority

Partner eligibility, account creation, quote creation, status and completion resolve a concrete existing Financial Space. An omitted Space selects the customer's existing Personal Space; it never means all Spaces or whichever grant is available.

The customer, membership and Space must remain active and not deleted. The customer must have granted the correct scope to the approved partner account for that exact Space. Grant checks precede membership-detail checks where the target is explicit, and denial wording does not disclose whether a missing grant or an inactive membership caused the denial.

`GET /api/partner/essentials/customers/{customer}/status` returns `data.financial_space_id` and only that Space's permitted advances. It does not disclose every customer Space.

Partner eligibility returns a reduced view: `financial_space_id`, eligible `lines`, and `overall`. Line fields are limited to identifiers, lender/product identifiers, approved/available limits, currency, status and expiry. `overall` supplies the target Space, bounded available limit, currency and `limits_are_not_additive`. Customer account collections, other-Space advances, raw decision snapshots, provider payloads and credit-profile details are not returned. Mixed currencies do not produce a fabricated converted total.

A partner cannot relocate an existing service account from another Space through repeated account creation. Quote creation/completion rejects records without an explicit Space. Soft-deleted customers are unavailable to partner endpoints. Programme-partner reporting and Essentials partner-API authority remain distinct.

## Account closure and shared customer mutex

`AccountDeletionService` checks pending, reservation-bearing, active, overdue and unknown Essentials states and pending collections, in addition to the existing loan, savings, protection and participatory obligations. A settled record with positive remaining debt also requires review. These outcomes reuse the deletion support case and preserve servicing access.

A consistently settled zero-balance advance does not permanently prevent closure. A never-activated failed request is not automatically a permanent borrower debt.

The container now binds Essentials mutation entry points and account deletion through the same `EssentialsCustomerMutex`. Covered entry points are service-account creation, eligibility refresh, quote creation, partner completion authorisation, acceptance, repayment and deletion. The PostgreSQL writer session obtains a non-blocking advisory lock before these operations. Busy callers receive a conflict rather than waiting in an inverted set of row locks.

The mutex does not wrap the domain operation in a new database transaction. Existing reservation commits remain before external provider calls. It never automatically repeats a financial callback after a provider or unlock exception. A fresh active customer is reloaded after locking, so a stale user object cannot resume work after completed closure.

The PostgreSQL connection must be direct or session-affine. Do not introduce transaction-pooling middleware without replacing this lock design and repeating concurrency acceptance. The local/testing cache-lock fallback is not production PostgreSQL evidence. Tests cover container binding, nesting without changing transaction depth, contention rejection, exception release and stale-customer denial. Two-session PostgreSQL acceptance and the required independent APPROVED review remain outstanding until separately recorded.

This is not a claim that every unrelated financial service uses the same mutex or that the broader collection/accounting findings are resolved.

## Operator and developer handling

When `deletion_status=pending_obligations` is returned, retain the case reference and complete or lawfully transfer the genuine obligation. Do not edit debt values or statuses merely to make deletion succeed. Pending provider collections require reconciliation, not an invented successful collection or a second route.

For partner denials, confirm the intended customer, exact Space, current membership and granted scope. Do not grant wider access simply to suppress an error. For a busy/conflict response, inspect the original instruction's state before retrying; this change does not make ambiguous money retries safe.

The existing [developer index](current-endpoints.md) and [client contract](frontend-backend-contract.md) remain the entry points. Clients must consume the smaller partner eligibility response rather than depending on unrelated account/profile collections.

## Remaining financial acceptance

Expected immutable Essentials accounting, durable canonical provider instructions, full collection/reversal reconciliation, provider-reference uniqueness, pending exposure and approved capital-mandate lifecycle require their own implementation and tests. This source slice does not resolve them or the separately recorded credential-log incident.

Preserve all tests, audit checks and independent financial-review requirements. No live financing, new infrastructure or GitHub Actions enablement is introduced by this branch update.
