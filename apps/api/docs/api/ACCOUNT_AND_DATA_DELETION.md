# Account and selective data deletion

Status: implementation candidate  
Reviewed: 27 September 2026  
Language: English (United Kingdom)

## Policy

OpFin supports two distinct customer requests:

1. **Delete the whole account.** The customer must reauthenticate and explicitly confirm deletion. Before any destructive action, OpFin performs a server-authoritative obligation check under the same protected customer state used by financial workflows.
2. **Delete selected optional data.** The customer may remove supported optional data categories while keeping the OpFin account active. This does not delete regulated financial, identity, accounting, settlement, security or audit records that remain subject to an applicable retention requirement.

A whole-account deletion request is **rejected immediately with HTTP 409** when an active financial obligation remains. OpFin does not create a misleading pending-deletion state. The response identifies each blocker with its type, reference, status, amount where available, relevant date where available, and the recorded provider/counterparty contact details where available. The customer must resolve the obligations and submit a fresh deletion request.

The obligation check currently covers active credit, customer-recorded personal obligations, Essentials financing, peer borrowing and lending, savings positions, active protection policies, investment positions and active payroll-deduction arrangements. Unknown or unresolved financial states should fail closed rather than be silently treated as settled.

## Endpoints

All endpoints require auth:sanctum and normal API throttling.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | /api/account/deletion-readiness | Returns whether whole-account deletion is currently allowed, structured active obligation blockers and supported optional-data categories |
| DELETE | /api/account | Existing whole-account deletion route; requires current PIN/password and confirmation=DELETE; returns 409 with blocker details when obligations remain |
| DELETE | /api/account/data | Deletes selected supported optional data; requires current PIN/password, confirmation=DELETE_DATA and data_categories[] |

## Supported optional-data categories

- location_context
- financial_planning
- linked_accounts
- household_and_microbusiness
- offline_sync
- profile_preferences

Selective deletion keeps the account active and preserves required financial evidence. Whole-account deletion revokes active consents, removes optional active customer context, revokes access tokens, de-identifies the active user profile and then soft-deletes the user record while retaining only evidence that must remain for a legitimate legal, regulatory, accounting, security or fraud-prevention purpose.

## Customer experience

The mobile account-deletion screen performs a readiness check before destructive action. When deletion is blocked, it shows the obligation, provider/counterparty, reference, status, amount/date where known and recorded phone/email/address where available. Direct provider contact is never invented. If OpFin does not hold a direct contact field, the UI states that clearly and preserves the obligation reference for support and closure.
