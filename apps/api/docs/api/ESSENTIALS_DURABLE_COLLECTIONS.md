# Durable Essentials collection and servicing contract

Status: Implemented working branch; integrated release acceptance outstanding  
Reviewed: 26 September 2026  
Language: English (United Kingdom)

## Scope

This change implements durable **repayment collection** identity, capacity reservation, original-allocation reversal and lender-servicing movement evidence. It does not yet replace the separate lender-drawdown, biller-settlement and lender-release orchestration in `EssentialsOrchestrationService`. Do not describe a collection-only implementation as complete provider-instruction coverage.

The runtime binding uses `SerialisedEssentialsOrchestrationService` and the existing customer mutex. PostgreSQL uses the same session-affine writer advisory lock as before. Preparation commits before CPay submission; provider observations commit before schedule/accounting application. No database transaction is deliberately stretched across the external HTTP call.

## Customer request

The existing `POST /api/essentials/advances/{advance}/repay` retains amount, body idempotency key and optional wallet selection. The selected or default wallet must be active and verified. An unverified profile phone is no longer used as an automatic collection source.

The draft implementation requires a stable 8–160-character key using letters, digits and `:._-`. This is stricter than the previous controller's maximum-length-only rule and requires compatibility review before release. The key is tied to the customer, advance, amount and explicit wallet-selection semantics. It cannot be reused with a changed payload. A global-key collision does not disclose another customer's repayment.

The server freezes the resolved wallet and payer, lender/product/beneficiary, amount, currency, request reference, approved route/environment and allocation policy in an encrypted instruction. Credentials are not copied into this record. Replaying the same request retrieves its original financial identity rather than inventing another provider request.

## State and capacity

| State | Meaning | New execution |
| --- | --- | --- |
| `prepared` | Durable instruction exists, no provider submission recorded | Original authorised request may resume; owner may cancel before submission |
| `submitting` | Submission was claimed before HTTP | Do not resend; query original status |
| `pending` | Provider outcome is incomplete or the response was lost | Reserve capacity and reconcile |
| `confirmed_unapplied` | Attributable provider success saved, financial application outstanding | Apply the saved observation, not another collection |
| `applied` | Allocation, journal, schedules and balances committed | Idempotent read/reconciliation only |
| `failed` | Definitive failed or cancelled-before-submission instruction | No collection effect applied |
| `reversed` | Explicit provider reversal observed | Restore saved allocation atomically; unresolved application remains a closure blocker |
| `exception` | Contradictory, reused or unattributed provider evidence | Keep restricted; no automatic status override |

Pending capacity does not expire merely because a request is old. The existing one-in-flight rule is retained, with a database uniqueness backstop for actively processed states. Arithmetic also checks total reserved collection capacity against the outstanding obligation. Unknown future states are treated conservatively.

## Provider finality and reversal

A provider result must carry attributable finality before debt is reduced. Returned amount, currency and request identity are compared when present. Provider references cannot be reused across collection instructions in the same merchant/environment scope. PostgreSQL uniqueness failures use a savepoint so they do not corrupt the surrounding transaction.

A stale pending response does not undo success. Success followed by failure is a reconciliation exception, not an assumed refund. An explicit reversal restores the **original saved allocation** using its accepted policy. Reading a later policy does not change an earlier repayment's meaning.

The current accepted allocation order is oldest due item, interest, fees, then principal. A fees-first variant exists only as an explicitly selected version in the pure rules; it is not silently applied to existing obligations.

The collection application and reversal update the schedule, advance, lender principal, obligation, repayment record, immutable servicing journal and audit evidence within one transaction. Invalid totals or insufficient lender deployment are not clamped. A provider success survives an application rollback as evidence for later reconciliation.

These are lender-servicing **movement control journals**, not OpFin corporate revenue. Complete activation/opening control accounting and adoption of older advances remain separate work. Existing third-party principal, interest and fees must not be represented as OpFin corporate income by inference.

## Recovery endpoints

`GET /api/essentials/repayments/{repayment}/collection-status` returns the current customer's repayment/instruction states and recovery indicators. It does not return the encrypted payer or provider configuration snapshot.

`POST /api/essentials/repayments/{repayment}/cancel-unsubmitted` takes a required reason. Only a truly prepared, unsubmitted instruction can be cancelled. Submitted, ambiguous or confirmed transactions cannot be relabelled as never sent.

Both use Sanctum, the existing API throttle and exact customer ownership. They do not grant partner access to another customer's collection history. Administrative reconciliation retains its existing independent role check.

Retained financial reconciliation can handle a verified late reversal after login closure; it does not restore authentication or authorise a new payment. An unresolved collection, application or reversal blocks destructive customer closure. Deleting a settled wallet removes the live wallet relationship but retains the original encrypted financial evidence.

## Exposure and policy

The synchroniser counts production loan schedules before any legacy counterpart, avoiding duplicate representation of the same loan. It includes pending Essentials funding/reversal reservations separately from due debt. Reconciliation can reduce available headroom; it cannot grant a new limit or override a zero imposed by scoring policy. A normal authorised profile refresh is still responsible for approving a limit increase.

The existing lender-mandate origination/status compatibility and complete cross-lender exposure acceptance still need review. A conservative profile cap is not a replacement for those controls.

## Tests and operator exercises

The pure rule harness covers exact allocation/restoration, overflow, capacity, invalid dates, purpose-specific finality, stable identity and explicit policy selection. Thirteen Laravel integration tests cover successful application, replay, wallet changes, timeout recovery, missing attribution, duplicate provider references, route changes, rollback/retry, cancellation, closure and late reversals.

The new Laravel tests have been authored but not run in the available local environment. They must pass with the existing suite on SQLite and isolated PostgreSQL, including concurrent writers and repeated schema installation, before this branch is accepted.

Operators should exercise a synthetic timeout, locate the original instruction, verify unchanged reserved capacity, reconcile once, and confirm exactly one allocation/journal. Repeat with an explicit reversal and a deliberately inconsistent funding balance. Never repair a difference by changing a provider status or inventing a financial reference.

## Remaining release work

Run the environment preflight, full existing/new API suites, migration upgrades/rebuilds, PHP formatting, dependency audit, documentation checks and independent financial review. Native API compatibility, legacy repayment adoption, activation accounting, durable lender/biller instructions and complete post-fulfilment refund handling are not closed by this repayment slice. The credential-log incident remains independently unresolved.
