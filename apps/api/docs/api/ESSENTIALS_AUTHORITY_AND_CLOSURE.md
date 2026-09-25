# Essentials partner authority and account closure

Status: Tested remediation; merged/deployed revision must be recorded separately  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

## Scope and verification

Candidate `f6173ebc0429190756554cd30d009fdf40937395` passed the existing isolated API build procedure: **274 tests, 1,927 assertions**, dependency audit and asset build. Nine new tests cover the boundaries below. The verifier deliberately exited before runtime deployment. These results do not certify PostgreSQL concurrency, external provider activation or unrelated Essentials accounting.

## Exact Financial Space authority

Partner customer eligibility, account creation, quote creation, status and completion now resolve a concrete existing Financial Space before checking the customer grant. An omitted Space selects the customer's existing Personal Space; it never means all Spaces or whichever grant is available.

The customer and membership must remain active and not deleted; the Space must be active and not deleted. The customer must have granted the correct scope to the approved partner distribution account for that exact Space. A permission for a Household cannot authorise the Personal Space or another Household. A removed member's earlier partner grant does not override removal.

`GET /api/partner/essentials/customers/{customer}/status` now returns `data.financial_space_id` and only `data.advances` for that resolved Space. The former optional filter that could return every customer Space is removed.

Partner account creation preserves the resolved Space. A matching service account already held in another Space cannot be moved by the embedded platform through repeated account creation. The request is rejected and the original account remains unchanged. Quote creation/completion rejects records with no explicit Space rather than guessing a target.

Soft-deleted customers cannot be loaded through the partner endpoints. Partner roles still require customer grants; programme-partner aggregate reporting remains a separate authority. Existing eligibility validation/conflict responses are retained; forbidden and not-found responses must not be presented as provider outages.

## Customer account deletion

`AccountDeletionService` checks Essentials advances and pending collections in addition to existing loan, savings, protection and participatory obligations.

Reservation-bearing, pending, active, overdue and unknown future advance states prevent destructive closure. A settled advance with a positive remaining balance also requires review. A pending repayment collection prevents closure even when another record labels the advance settled. These outcomes record or reuse the account-deletion support case and preserve the user's wallet, profile, Personal Space and servicing access.

A consistently settled zero-balance advance does not permanently prevent closure; required financial records remain retained. A failed, never-activated advance does not create permanent debt merely because the rejected quote's amount remains as historical evidence.

The deletion transaction acquires the existing credit-profile lock used by Essentials acceptance before checking obligations and deleting optional context. It rechecks the current credential against the fresh locked account. Production-database concurrency acceptance is still required; the SQLite suite is not proof of PostgreSQL interleaving.

## Operator procedure

When `deletion_status=pending_obligations` is returned, retain the case reference and complete or lawfully transfer the genuine obligation. Do not edit status or outstanding amounts solely to make deletion succeed. Pending provider collections require reconciliation, not a new collection or fabricated finality.

For partner errors, verify the customer, exact target Space, active membership, partner account and granted scope. Do not grant broader access simply to remove a forbidden response. An omitted Space is the existing Personal Space, not unrestricted account-wide authority.

## Remaining financial acceptance

These changes address partner scoping and closure; they do not by themselves add the expected immutable Essentials ledger events, canonical provider intents, safe collection concurrency, provider-reference uniqueness, trusted store-channel rules or every pending-exposure/capital-mandate fix. Refer to the current remediation record before claiming full launch readiness.

Existing tests and audit gates remain enabled. No live debt, provider activation, new infrastructure or GitHub Actions enablement is introduced by this repair.
